<?php

namespace App\Http\Controllers;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\Device;
use App\Models\InsuranceCompany;
use App\Models\InsuranceRule;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\SyncConflict;
use App\Models\SyncEvent;
use App\Services\AuditLogger;
use App\Services\PharmacyContext;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SyncController extends Controller
{
    public function push(Request $request, PharmacyContext $context): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string'],
            'events' => ['required', 'array', 'min:1', 'max:100'],
            'events.*.client_event_id' => ['required', 'uuid'],
            'events.*.type' => ['required', 'string', 'max:60'],
            'events.*.payload' => ['required', 'array'],
            'events.*.occurred_at' => ['nullable', 'date'],
            'events.*.pharmacy_id' => ['nullable', 'integer'],
            'events.*.schema_version' => ['nullable', 'integer'],
        ]);

        $device = Device::where('token_hash', hash('sha256', $request->input('device_token')))->whereNull('revoked_at')->first();
        abort_unless($device !== null, 401, 'Appareil non autorisé.');

        $device->update(['last_seen_at' => now()]);
        $user = $device->user;
        $accessible = $user->is_super_admin ? Pharmacy::pluck('id')->all() : $user->pharmacies()->pluck('pharmacies.id')->all();

        $results = [];

        foreach ($request->input('events') as $event) {
            $clientEventId = $event['client_event_id'];

            $existing = SyncEvent::where('client_event_id', $clientEventId)->first();
            if ($existing) {
                $results[] = [
                    'client_event_id' => $clientEventId,
                    'status' => $existing->status === 'applied' ? 'duplicate' : $existing->status,
                    'error' => $existing->error,
                ];

                continue;
            }

            $pharmacyId = $event['pharmacy_id'] ?? $device->pharmacy_id;
            if ($pharmacyId !== null && ! in_array((int) $pharmacyId, $accessible, true)) {
                $results[] = [
                    'client_event_id' => $clientEventId,
                    'status' => 'rejected',
                    'error' => 'Pharmacie inaccessible.',
                ];

                continue;
            }

            $syncEvent = SyncEvent::create([
                'client_event_id' => $clientEventId,
                'pharmacy_id' => $pharmacyId,
                'device_id' => $device->id,
                'user_id' => $user->id,
                'type' => $event['type'],
                'payload' => $event['payload'],
                'schema_version' => $event['schema_version'] ?? 1,
                'occurred_at' => $event['occurred_at'] ?? now(),
                'received_at' => now(),
                'status' => 'pending',
            ]);

            try {
                DB::transaction(function () use ($syncEvent) {
                    $this->process($syncEvent);
                    $syncEvent->update(['status' => 'applied', 'processed_at' => now()]);
                });
                $results[] = ['client_event_id' => $clientEventId, 'status' => 'applied'];
            } catch (\Throwable $e) {
                $syncEvent->update(['status' => 'conflict', 'error' => $e->getMessage(), 'attempts' => $syncEvent->attempts + 1]);
                SyncConflict::create([
                    'sync_event_id' => $syncEvent->id,
                    'pharmacy_id' => $syncEvent->pharmacy_id,
                    'type' => $syncEvent->type,
                    'details' => ['error' => $e->getMessage(), 'payload' => $syncEvent->payload],
                    'status' => 'open',
                ]);
                $results[] = ['client_event_id' => $clientEventId, 'status' => 'conflict', 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    public function pull(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => ['required', 'string'],
            'since' => ['nullable', 'integer', 'min:0'],
        ]);

        $device = Device::where('token_hash', hash('sha256', $request->input('device_token')))->whereNull('revoked_at')->first();
        abort_unless($device !== null, 401, 'Appareil non autorisé.');

        $device->update(['last_seen_at' => now()]);
        $user = $device->user;
        $accessible = $user->is_super_admin ? Pharmacy::pluck('id')->all() : $user->pharmacies()->pluck('pharmacies.id')->all();

        $since = (int) $request->input('since', 0);

        // `since` = dernière version connue du poste. On ne renvoie que les
        // lignes modifiées depuis. Pour un premier pull (since = 0), on renvoie
        // tout le référentiel, y compris les lignes historiques en version 0.
        $modified = fn ($query) => $since > 0 ? $query->where('version', '>', $since) : $query;

        $changes = [
            'products' => $modified(Product::where('is_active', true))->get()->map(fn ($p) => [
                'id' => $p->id,
                'code' => $p->code,
                'barcode' => $p->barcode,
                'name' => $p->name,
                'generic_name' => $p->generic_name,
                'form' => $p->form,
                'dosage' => $p->dosage,
                'laboratory' => $p->laboratory,
                'category_id' => $p->category_id,
                'base_unit' => $p->base_unit,
                'sale_unit' => $p->sale_unit,
                'conversion_factor' => $p->conversion_factor,
                'purchase_price' => $p->purchase_price,
                'sale_price' => $p->sale_price,
                'alert_threshold' => $p->alert_threshold,
                'version' => $p->version,
            ]),
            'customers' => $modified(Customer::query())->get()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'email' => $c->email,
                'insurance_company_id' => $c->insurance_company_id,
                'version' => $c->version,
            ]),
            'insurers' => $modified(InsuranceCompany::where('is_active', true))->get()->map(fn ($i) => [
                'id' => $i->id,
                'name' => $i->name,
                'code' => $i->code,
                'version' => $i->version,
            ]),
            'insurance_rules' => $modified(InsuranceRule::query())->get()->map(fn (InsuranceRule $r) => [
                'id' => $r->id,
                'insurance_company_id' => $r->insurance_company_id,
                'product_id' => $r->product_id,
                'category_id' => $r->category_id,
                'type' => $r->type,
                'value' => $r->value,
                'version' => $r->version,
            ]),
            'stock_levels' => $modified(StockLevel::whereIn('pharmacy_id', $accessible))->get()->map(fn ($s) => [
                'pharmacy_id' => $s->pharmacy_id,
                'product_id' => $s->product_id,
                'quantity' => $s->quantity,
                'version' => $s->version,
            ]),
        ];

        $maxVersion = (int) max(
            Product::max('version') ?? 0,
            Customer::max('version') ?? 0,
            InsuranceCompany::max('version') ?? 0,
            InsuranceRule::max('version') ?? 0,
            StockLevel::whereIn('pharmacy_id', $accessible)->max('version') ?? 0,
        );

        return response()->json([
            'server_time' => now()->toIso8601String(),
            'version' => $maxVersion,
            'changes' => $changes,
        ]);
    }

    private function process(SyncEvent $event): void
    {
        $payload = $event->payload ?? [];

        match ($event->type) {
            'sale.created' => $this->processSaleCreated($event, $payload),
            'sale.cancelled' => $this->processSaleCancelled($event, $payload),
            'cash.opened' => $this->processCashOpened($event, $payload),
            'cash.closed' => $this->processCashClosed($event, $payload),
            'cash.movement' => $this->processCashMovement($event, $payload),
            'stock.adjusted' => $this->processStockAdjusted($event, $payload),
            'inventory.counted' => $this->processInventoryCounted($event, $payload),
            default => null,
        };
    }

    private function processSaleCreated(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $event->pharmacy_id;
        abort_if($pharmacyId === null, 422, 'Pharmacie manquante.');

        $clientRequestId = $payload['client_request_id'] ?? $event->client_event_id;

        $existing = Sale::where('client_request_id', $clientRequestId)->first();
        if ($existing) {
            return;
        }

        // Delegate to SaleService using server prices.
        $data = $payload;
        $data['client_request_id'] = $clientRequestId;
        $user = $event->user;
        abort_if($user === null, 422, 'Utilisateur manquant.');
        $pharmacy = Pharmacy::findOrFail($pharmacyId);
        app(SaleService::class)->create($user, $pharmacy, $data, null);
    }

    private function processSaleCancelled(SyncEvent $event, array $payload): void
    {
        $sale = Sale::where('client_request_id', $payload['client_request_id'] ?? null)->first();
        if (! $sale || $sale->status === 'cancelled') {
            return;
        }

        $user = $event->user;
        abort_if($user === null, 422, 'Utilisateur manquant.');

        app(SaleService::class)->cancel($user, $sale, (string) ($payload['reason'] ?? 'Annulation hors ligne'));
    }

    private function processCashOpened(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $this->requirePharmacy($event);

        if (CashSession::where('pharmacy_id', $pharmacyId)->where('status', 'open')->exists()) {
            return;
        }

        $session = CashSession::create([
            'pharmacy_id' => $pharmacyId,
            'user_id' => $event->user_id,
            'opening_amount' => (int) ($payload['opening_amount'] ?? 0),
            'status' => 'open',
            'opened_at' => $event->occurred_at ?? now(),
        ]);

        app(AuditLogger::class)->log('cash.opened', CashSession::class, $session->id, [], [
            'pharmacy_id' => $pharmacyId,
            'opening_amount' => $session->opening_amount,
            'source' => 'sync',
        ]);
    }

    private function processCashClosed(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $this->requirePharmacy($event);

        $session = isset($payload['session_id'])
            ? CashSession::where('pharmacy_id', $pharmacyId)->whereKey($payload['session_id'])->first()
            : CashSession::where('pharmacy_id', $pharmacyId)->where('status', 'open')->latest()->first();

        if (! $session || $session->status !== 'open') {
            return;
        }

        $expected = $this->expectedCash($session);
        $closing = (int) ($payload['closing_amount'] ?? $expected);

        $session->update([
            'closing_amount' => $closing,
            'expected_amount' => $expected,
            'difference' => $closing - $expected,
            'status' => 'closed',
            'closed_at' => $event->occurred_at ?? now(),
            'notes' => $payload['notes'] ?? $session->notes,
        ]);

        app(AuditLogger::class)->log('cash.closed', CashSession::class, $session->id, ['status' => 'open'], [
            'status' => 'closed',
            'expected' => $expected,
            'closing' => $closing,
            'source' => 'sync',
        ]);
    }

    private function processCashMovement(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $this->requirePharmacy($event);

        $session = isset($payload['session_id'])
            ? CashSession::where('pharmacy_id', $pharmacyId)->whereKey($payload['session_id'])->first()
            : CashSession::where('pharmacy_id', $pharmacyId)->where('status', 'open')->latest()->first();

        if (! $session || $session->status !== 'open') {
            throw ValidationException::withMessages([
                'cash_session_id' => 'Aucune session de caisse ouverte pour ce mouvement.',
            ]);
        }

        $type = (string) ($payload['type'] ?? '');
        if (! in_array($type, ['entree', 'retrait'], true)) {
            throw ValidationException::withMessages(['type' => 'Type de mouvement invalide.']);
        }

        $amount = (int) ($payload['amount'] ?? 0);
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Le montant doit être positif.']);
        }

        $movement = CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'reason' => $payload['reason'] ?? null,
            'user_id' => $event->user_id,
        ]);

        app(AuditLogger::class)->log('cash.movement', CashMovement::class, $movement->id, [], [
            'session_id' => $session->id,
            'type' => $type,
            'amount' => $amount,
            'source' => 'sync',
        ]);
    }

    private function processStockAdjusted(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $this->requirePharmacy($event);
        $productId = (int) ($payload['product_id'] ?? 0);
        abort_if($productId <= 0, 422, 'Produit manquant.');

        $delta = isset($payload['delta']) ? (int) $payload['delta'] : (int) ($payload['quantity'] ?? 0);
        if ($delta === 0) {
            return;
        }

        $type = (string) ($payload['type'] ?? 'correction');
        if (! in_array($type, ['correction', 'perte', 'casse', 'inventaire'], true)) {
            $type = 'correction';
        }

        app(StockService::class)->apply(
            $pharmacyId,
            $productId,
            $delta,
            $type,
            $payload['reason'] ?? 'Ajustement hors ligne',
            $event->user_id,
        );

        app(AuditLogger::class)->log('stock.adjusted', null, null, [], [
            'pharmacy_id' => $pharmacyId,
            'product_id' => $productId,
            'type' => $type,
            'delta' => $delta,
            'source' => 'sync',
        ]);
    }

    private function processInventoryCounted(SyncEvent $event, array $payload): void
    {
        $pharmacyId = $this->requirePharmacy($event);
        $counts = $payload['counts'] ?? [];

        if (! is_array($counts) || $counts === []) {
            return;
        }

        $stock = app(StockService::class);
        foreach ($counts as $count) {
            $productId = (int) ($count['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $stock->setCount(
                $pharmacyId,
                $productId,
                (int) ($count['counted'] ?? 0),
                $event->user_id,
                'Comptage physique hors ligne',
            );
        }

        app(AuditLogger::class)->log('inventory.counted', null, null, [], [
            'pharmacy_id' => $pharmacyId,
            'lines' => count($counts),
            'source' => 'sync',
        ]);
    }

    private function requirePharmacy(SyncEvent $event): int
    {
        abort_if($event->pharmacy_id === null, 422, 'Pharmacie manquante.');

        return (int) $event->pharmacy_id;
    }

    private function expectedCash(CashSession $session): int
    {
        $cashPayments = \App\Models\Payment::whereHas('sale', function ($q) use ($session) {
            $q->where('pharmacy_id', $session->pharmacy_id)->where('status', 'completed');
        })
            ->whereIn('method', ['especes', 'espèces', 'espece', 'espèce', 'cash'])
            ->whereBetween('created_at', [$session->opened_at ?? $session->created_at, now()])
            ->sum('amount');

        $movements = CashMovement::where('cash_session_id', $session->id)->get();
        $net = $movements->where('type', 'entree')->sum('amount')
            - $movements->where('type', 'retrait')->sum('amount');

        return (int) ($session->opening_amount + $cashPayments + $net);
    }

    public function registerDevice(Request $request): JsonResponse
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'pharmacy_id' => ['nullable', 'integer', 'exists:pharmacies,id'],
        ]);

        $user = $request->user();
        $pharmacyId = $request->input('pharmacy_id');
        if ($pharmacyId !== null && ! $user->canAccessPharmacy(Pharmacy::find($pharmacyId))) {
            abort(403);
        }

        $rawToken = Str::random(64);
        $device = Device::create([
            'user_id' => $user->id,
            'pharmacy_id' => $pharmacyId,
            'name' => $request->input('name', 'Navigateur'),
            'token_hash' => hash('sha256', $rawToken),
            'last_seen_at' => now(),
        ]);

        return response()->json([
            'device_id' => $device->id,
            'device_token' => $rawToken,
        ]);
    }

    public function conflicts(Request $request): Response
    {
        $this->authorize('manage_audit');
        $accessible = app(PharmacyContext::class)->accessibleIds($request);

        /** @var Collection<int, SyncConflict> $rows */
        $rows = SyncConflict::with(['syncEvent', 'pharmacy'])
            ->whereIn('pharmacy_id', $accessible)
            ->where('status', 'open')
            ->latest()
            ->get();

        $conflicts = [];
        foreach ($rows as $c) {
            $conflicts[] = [
                'id' => $c->id,
                'type' => $c->type,
                'pharmacy' => $c->pharmacy !== null ? (string) $c->pharmacy->getAttribute('name') : null,
                'details' => $c->details,
                'created_at' => $c->created_at->format('d/m/Y H:i'),
            ];
        }

        return Inertia::render('SyncConflicts', ['conflicts' => $conflicts]);
    }

    public function resolveConflict(Request $request, SyncConflict $conflict): RedirectResponse
    {
        $this->authorize('manage_audit');
        $data = $request->validate(['resolution' => ['required', 'in:applied,cancelled,adjusted']]);

        $conflict->update([
            'status' => $data['resolution'],
            'resolved_by' => $request->user()->id,
            'resolved_at' => now(),
        ]);

        return redirect()->back()->with('success', 'Conflit résolu');
    }
}
