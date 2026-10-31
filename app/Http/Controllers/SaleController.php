<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\CancelSaleRequest;
use App\Http\Requests\PreviewSaleRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\InsuranceCompany;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Services\SaleService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SaleController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Sale::class);
        $pharmacy = $this->pharmacy($request);

        $products = Product::with('category')->where('is_active', true)->get()->map(function ($p) use ($pharmacy) {
            $sl = StockLevel::where('pharmacy_id', $pharmacy->id)->where('product_id', $p->id)->first();
            $stock = $sl !== null ? $sl->quantity : 0;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'generic_name' => $p->generic_name,
                'barcode' => $p->barcode,
                'category' => $p->category?->name,
                'labo' => trim(($p->laboratory ?? '').' · '.($p->form ?? 'Comprimés'), ' ·'),
                'price' => number_format($p->sale_price, 0, ',', ' ').' GNF',
                'price_raw' => $p->sale_price,
                'stock' => $stock,
                'alert_threshold' => $p->alert_threshold,
                'tag' => $stock === 0 ? 'Rupture — bloqué' : ($stock < $p->alert_threshold ? 'Stock faible' : null),
            ];
        });

        $insurers = InsuranceCompany::where('is_active', true)->get()->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'code' => $i->code]);
        $customers = Customer::orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'insurance_company_id' => $c->insurance_company_id]);

        return Inertia::render('Vente', [
            'pharmacy' => $pharmacy,
            'products' => $products,
            'insurers' => $insurers,
            'customers' => $customers,
            'discountCap' => $this->discountCap($request),
            'openSession' => CashSession::where('pharmacy_id', $pharmacy->id)->where('status', 'open')->first()?->id,
            'recentSales' => Sale::with('items')->where('pharmacy_id', $pharmacy->id)->latest()->take(5)->get()->map(fn ($s) => [
                'id' => $s->id,
                'sale_number' => $s->sale_number,
                'total' => number_format($s->total, 0, ',', ' ').' GNF',
                'status' => $s->status,
                'time' => $s->created_at->format('H:i'),
            ]),
        ]);
    }

    public function store(StoreSaleRequest $request, SaleService $sales)
    {
        $data = $request->validated();
        $pharmacy = $this->pharmacy($request);

        // Le client ne choisit pas la pharmacie : seule la pharmacie active compte.
        if (! empty($data['pharmacy_id']) && (int) $data['pharmacy_id'] !== $pharmacy->id) {
            abort(404);
        }

        $approvedBy = $this->resolveDiscountApproval($request, $data);

        $sale = $sales->create($request->user(), $pharmacy, $data, $approvedBy);

        return redirect()->route('vente.ticket', $sale)
            ->with('success', 'Vente enregistrée : '.$sale->sale_number)
            ->with('ticket_id', $sale->id);
    }

    /**
     * Aperçu autoritaire des totaux (part client / part assureur) sans
     * enregistrer la vente. Évite de dupliquer les règles d'assurance côté client.
     */
    public function preview(PreviewSaleRequest $request, SaleService $sales)
    {
        return response()->json($sales->preview($request->validated()));
    }

    public function ticket(Request $request, Sale $sale)
    {
        $this->authorize('view', $sale);
        $sale->load(['items.product', 'payments', 'customer', 'insuranceCompany', 'pharmacy', 'user']);

        return Inertia::render('Ticket', [
            'sale' => [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'date' => $sale->created_at->format('d/m/Y H:i'),
                'status' => $sale->status,
                'pharmacy' => [
                    'name' => $sale->pharmacy->name,
                    'city' => $sale->pharmacy->city,
                    'address' => $sale->pharmacy->address,
                    'phone' => $sale->pharmacy->phone,
                ],
                'seller' => $sale->user->name,
                'customer' => $sale->customer?->name,
                'insurer' => $sale->insuranceCompany?->name,
                'items' => $sale->items->map(fn ($i) => [
                    'name' => $i->product->name,
                    'quantity' => $i->quantity,
                    'unit' => $i->unit,
                    'unit_price' => $i->unit_price,
                    'total' => $i->total,
                ]),
                'payments' => $sale->payments->map(fn ($p) => [
                    'method' => $p->method,
                    'amount' => $p->amount,
                    'reference' => $p->reference,
                ]),
                'subtotal' => $sale->subtotal,
                'discount_amount' => $sale->discount_amount,
                'discount_percent' => $sale->discount_percent,
                'total' => $sale->total,
                'client_part' => $sale->client_part,
                'insurer_part' => $sale->insurer_part,
            ],
        ]);
    }

    public function cancel(CancelSaleRequest $request, Sale $sale, SaleService $sales)
    {
        $this->authorize('cancel', $sale);
        $data = $request->validated();

        $sales->cancel($request->user(), $sale, $data['reason']);

        return redirect()->back()->with('success', 'Vente annulée');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return int|null identifiant de l'approbateur si une validation a été requise
     */
    private function resolveDiscountApproval(Request $request, array $data): ?int
    {
        $percent = (int) ($data['discount_percent'] ?? 0);
        $amount = (int) ($data['discount_amount'] ?? 0);

        if ($percent === 0 && $amount === 0) {
            return null;
        }

        // Si une remise en pourcentage est saisie, le montant effectif est
        // calculé sur le sous-total du panier (le plafond montant doit être
        // comparé au montant réel, pas au champ discount_amount éventuel).
        if ($percent > 0) {
            $subtotal = 0;
            foreach ($data['items'] ?? [] as $item) {
                $product = Product::find($item['product_id'] ?? null);
                $subtotal += ($product?->sale_price ?? 0) * (int) ($item['quantity'] ?? 0);
            }
            $amount = (int) round($subtotal * $percent / 100);
        }

        if (! $request->user()->hasPermission('apply_discount')) {
            throw ValidationException::withMessages(['discount' => 'Vous n’êtes pas autorisé à appliquer une remise.']);
        }

        $absoluteMax = (int) config('discounts.absolute_max_percent', 50);
        if ($percent > $absoluteMax) {
            throw ValidationException::withMessages([
                'discount' => "La remise ne peut pas dépasser {$absoluteMax} %.",
            ]);
        }

        $cap = $this->discountCap($request);
        $exceedsCap = $percent > $cap['percent'] || $amount > $cap['amount'];

        if (! $exceedsCap) {
            return null;
        }

        if (! $request->user()->hasPermission('approve_discount')) {
            throw ValidationException::withMessages([
                'discount' => 'Remise supérieure au plafond : validation d’un gérant requise.',
            ]);
        }

        if (empty($data['discount_reason'])) {
            throw ValidationException::withMessages([
                'discount_reason' => 'Un motif est obligatoire pour une remise au-delà du plafond.',
            ]);
        }

        return $request->user()->id;
    }

    /**
     * @return array{percent: int, amount: int}
     */
    private function discountCap(Request $request): array
    {
        $role = $request->user()?->roleFor($this->pharmacy($request));

        return config('discounts.caps.'.$role, config('discounts.default'));
    }
}
