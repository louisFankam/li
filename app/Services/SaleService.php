<?php

namespace App\Services;

use App\Models\InsuranceClaim;
use App\Models\InsuranceRule;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SaleService
{
    public function __construct(
        private AuditLogger $audit,
        private StockService $stock,
    ) {}

    /**
     * Crée une vente. Les prix sont TOUJOURS rechargés depuis le catalogue :
     * le prix envoyé par le client est ignoré (anti-falsification).
     *
     * @throws ValidationException
     */
    public function create(User $user, Pharmacy $pharmacy, array $data, ?int $discountApprovedBy = null): Sale
    {
        return DB::transaction(function () use ($user, $pharmacy, $data, $discountApprovedBy) {
            $clientRequestId = $data['client_request_id'] ?? null;

            if ($clientRequestId) {
                $existing = Sale::query()
                    ->where('client_request_id', $clientRequestId)
                    ->where('pharmacy_id', $pharmacy->id)
                    ->first();
                if ($existing) {
                    return $existing;
                }
            }

            $lines = $this->prepareLines($data['items']);
            $subtotal = array_sum(array_column($lines, 'line_total'));

            [$discountPercent, $discountAmount] = $this->resolveDiscount($data, $subtotal);
            $this->assertDiscountAllowed($user, $pharmacy, $discountPercent, $discountAmount, $subtotal, $data, $discountApprovedBy);
            $total = $subtotal - $discountAmount;

            [$clientPart, $insurerPart] = $this->splitInsurance(
                isset($data['insurance_company_id']) ? (int) $data['insurance_company_id'] : null,
                $lines,
                $total
            );

            $payments = $this->validatePayments($data['payments'] ?? [], $clientPart);

            // Contrôle et décrément atomique du stock (fusion des lignes identiques).
            $quantities = [];
            foreach ($lines as $line) {
                $quantities[$line['product_id']] = ($quantities[$line['product_id']] ?? 0) + $line['quantity'];
            }

            foreach ($quantities as $productId => $quantity) {
                $stock = StockLevel::query()
                    ->where('pharmacy_id', $pharmacy->id)
                    ->where('product_id', $productId)
                    ->lockForUpdate()
                    ->first();

                if (! $stock || $stock->quantity < $quantity) {
                    $name = collect($lines)->firstWhere('product_id', $productId)['name'] ?? 'Produit';
                    throw ValidationException::withMessages([
                        'items' => "Stock insuffisant pour {$name} (disponible : ".($stock->quantity ?? 0).').',
                    ]);
                }
            }

            $sale = Sale::create([
                'sale_number' => $this->number($pharmacy),
                'client_request_id' => $clientRequestId,
                'pharmacy_id' => $pharmacy->id,
                'user_id' => $user->id,
                'customer_id' => $data['customer_id'] ?? null,
                'insurance_company_id' => $data['insurance_company_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'discount_reason' => $data['discount_reason'] ?? null,
                'discount_approved_by' => $discountApprovedBy,
                'discount_approved_at' => $discountApprovedBy ? now() : null,
                'total' => $total,
                'client_part' => $clientPart,
                'insurer_part' => $insurerPart,
                'status' => 'completed',
            ]);

            foreach ($lines as $line) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $line['product_id'],
                    'quantity' => $line['quantity'],
                    'unit' => $line['unit'],
                    'unit_price' => $line['unit_price'],
                    'total' => $line['line_total'],
                ]);
            }

            foreach ($quantities as $productId => $quantity) {
                StockLevel::query()
                    ->where('pharmacy_id', $pharmacy->id)
                    ->where('product_id', $productId)
                    ->update([
                        'quantity' => DB::raw('quantity - '.$quantity),
                        'version' => DB::raw('version + 1'),
                    ]);

                StockMovement::create([
                    'pharmacy_id' => $pharmacy->id,
                    'product_id' => $productId,
                    'type' => 'vente',
                    'quantity' => -$quantity,
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'user_id' => $user->id,
                ]);
            }

            foreach ($payments as $payment) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $payment['method'],
                    'amount' => (int) $payment['amount'],
                    'reference' => $payment['reference'] ?? null,
                ]);
            }

            if (! empty($data['insurance_company_id'])) {
                InsuranceClaim::create([
                    'sale_id' => $sale->id,
                    'insurance_company_id' => $data['insurance_company_id'],
                    'total_amount' => $total,
                    'client_part' => $clientPart,
                    'insurer_part' => $insurerPart,
                    'status' => 'a_facturer',
                ]);
            }

            $this->audit->log('sale.created', Sale::class, $sale->id, [], [
                'sale_number' => $sale->sale_number,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $discountPercent,
                'discount_reason' => $data['discount_reason'] ?? null,
                'discount_approved_by' => $discountApprovedBy,
                'total' => $total,
            ]);

            return $sale;
        });
    }

    /**
     * Annule une vente et remet les quantités en stock.
     *
     * Idempotent : une vente déjà annulée est renvoyée telle quelle. Utilisé
     * par l'écran de vente comme par la synchronisation offline.
     */
    public function cancel(User $user, Sale $sale, string $reason): Sale
    {
        return DB::transaction(function () use ($user, $sale, $reason) {
            $sale = Sale::query()->whereKey($sale->id)->lockForUpdate()->firstOrFail();

            if ($sale->status === 'cancelled') {
                return $sale;
            }

            foreach ($sale->items as $item) {
                $this->stock->apply(
                    $sale->pharmacy_id,
                    $item->product_id,
                    $item->quantity,
                    'annulation_vente',
                    $reason,
                    $user->id,
                    'sale',
                    $sale->id,
                );
            }

            $sale->update([
                'status' => 'cancelled',
                'cancelled_by' => $user->id,
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $sale->claim?->update([
                'status' => 'annule',
                'notes' => trim(($sale->claim->notes ?? '').' Annulé avec la vente : '.$reason),
            ]);

            $this->audit->log('sale.cancelled', Sale::class, $sale->id, ['status' => 'completed'], [
                'status' => 'cancelled',
                'reason' => $reason,
                'cancelled_by' => $user->id,
            ]);

            return $sale;
        });
    }

    /**
     * Aperçu serveur (autoritaire) des totaux d'un panier, sans écriture.
     * Utilisé par l'écran de vente pour éviter de recalculer l'assurance côté client.
     *
     * @param  array<string, mixed>  $data
     * @return array{subtotal: int, discount_percent: ?int, discount_amount: int, total: int, client_part: int, insurer_part: int}
     */
    public function preview(array $data): array
    {
        $lines = $this->prepareLines($data['items'] ?? []);
        $subtotal = array_sum(array_column($lines, 'line_total'));

        [$discountPercent, $discountAmount] = $this->resolveDiscount($data, $subtotal);
        $total = $subtotal - $discountAmount;

        [$clientPart, $insurerPart] = $this->splitInsurance(
            isset($data['insurance_company_id']) ? (int) $data['insurance_company_id'] : null,
            $lines,
            $total
        );

        return [
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
            'client_part' => $clientPart,
            'insurer_part' => $insurerPart,
        ];
    }

    /**
     * Contrôle des remises côté serveur (appelé aussi par la synchronisation
     * offline, qui ne passe pas par SaleController).
     */
    private function assertDiscountAllowed(
        User $user,
        Pharmacy $pharmacy,
        ?int $percent,
        int $amount,
        int $subtotal,
        array $data,
        ?int $approvedBy,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if (! $user->hasPermission('apply_discount', $pharmacy)) {
            throw ValidationException::withMessages(['discount' => 'Vous n’êtes pas autorisé à appliquer une remise.']);
        }

        $absoluteMax = (int) config('discounts.absolute_max_percent', 50);
        if (($percent ?? 0) > $absoluteMax) {
            throw ValidationException::withMessages([
                'discount' => "La remise ne peut pas dépasser {$absoluteMax} %.",
            ]);
        }

        $role = $user->roleFor($pharmacy);
        $cap = config('discounts.caps.'.$role, config('discounts.default'));
        $exceedsCap = ($percent ?? 0) > (int) $cap['percent'] || $amount > (int) $cap['amount'];

        if (! $exceedsCap) {
            return;
        }

        if ($approvedBy === null) {
            throw ValidationException::withMessages([
                'discount' => 'Remise supérieure au plafond : validation d’un gérant requise.',
            ]);
        }

        if (empty($data['discount_reason'])) {
            throw ValidationException::withMessages([
                'discount_reason' => 'Un motif est obligatoire pour une remise au-delà du plafond.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function prepareLines(array $items): array
    {
        $productIds = collect($items)->pluck('product_id')->map(fn ($id) => (int) $id)->unique();
        $products = Product::query()
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'items' => 'Un ou plusieurs produits sont introuvables ou inactifs.',
            ]);
        }

        $lines = [];
        foreach ($items as $item) {
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($quantity < 1) {
                throw ValidationException::withMessages(['items' => 'La quantité doit être au moins 1.']);
            }

            $product = $products[(int) $item['product_id']];
            $unitPrice = (int) $product->sale_price;

            $lines[] = [
                'product_id' => $product->id,
                'name' => $product->name,
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? $product->sale_unit ?? $product->base_unit ?? 'boite',
                'unit_price' => $unitPrice,
                'line_total' => $unitPrice * $quantity,
            ];
        }

        return $lines;
    }

    /**
     * @return array{0: ?int, 1: int}
     */
    private function resolveDiscount(array $data, int $subtotal): array
    {
        $rawPercent = $data['discount_percent'] ?? null;
        $percent = $rawPercent !== null && $rawPercent !== ''
            ? max(0, min(100, (int) $rawPercent))
            : null;

        $amount = (int) ($data['discount_amount'] ?? 0);

        if ($percent !== null && $percent > 0) {
            $amount = (int) round($subtotal * $percent / 100);
        }

        $amount = max(0, min($amount, $subtotal));

        return [$percent, $amount];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return array{0: int, 1: int} [part client, part assureur]
     */
    private function splitInsurance(?int $companyId, array $lines, int $total): array
    {
        if (! $companyId) {
            return [$total, 0];
        }

        $productIds = array_column($lines, 'product_id');
        $categories = Product::query()
            ->whereIn('id', $productIds)
            ->pluck('category_id', 'id')
            ->map(fn ($id) => $id !== null ? (int) $id : null);

        $rules = InsuranceRule::query()
            ->where('insurance_company_id', $companyId)
            ->get();

        $defaultRule = $rules->first(fn (InsuranceRule $r) => $r->product_id === null && $r->category_id === null);

        $insurerPart = 0;
        foreach ($lines as $line) {
            $productId = (int) $line['product_id'];
            $categoryId = $categories[$productId] ?? null;

            $rule = $rules->first(fn (InsuranceRule $r) => (int) $r->product_id === $productId)
                ?? ($categoryId !== null ? $rules->first(fn (InsuranceRule $r) => (int) $r->category_id === $categoryId) : null)
                ?? $defaultRule;

            $lineInsurer = $rule
                ? ($rule->type === 'percentage'
                    ? (int) round($line['line_total'] * ((int) $rule->value) / 100)
                    : (int) $rule->value)
                : (int) round($line['line_total'] * 0.8);

            $insurerPart += max(0, min($lineInsurer, $line['line_total']));
        }

        $insurerPart = max(0, min($insurerPart, $total));

        return [$total - $insurerPart, $insurerPart];
    }

    /**
     * @param  array<int, array<string, mixed>>  $payments
     * @return array<int, array<string, mixed>>
     */
    private function validatePayments(array $payments, int $expected): array
    {
        if ($payments === []) {
            if ($expected === 0) {
                return [];
            }

            throw ValidationException::withMessages(['payments' => 'Au moins un règlement est requis.']);
        }

        $allowed = ['especes', 'mobile_money', 'carte', 'cheque', 'virement', 'credit'];
        foreach ($payments as $payment) {
            $method = (string) ($payment['method'] ?? '');
            if (! in_array($method, $allowed, true)) {
                throw ValidationException::withMessages([
                    'payments' => "Moyen de paiement inconnu : {$method}.",
                ]);
            }
        }

        $sum = array_sum(array_map(fn ($p) => (int) ($p['amount'] ?? 0), $payments));

        if ($sum !== $expected) {
            throw ValidationException::withMessages([
                'payments' => "Le total des règlements ({$sum} GNF) doit égaler la part client ({$expected} GNF).",
            ]);
        }

        return $payments;
    }

    private function number(Pharmacy $pharmacy): string
    {
        return 'SALE-'.$pharmacy->id.'-'.now()->format('Ymd').'-'.Str::ulid();
    }
}
