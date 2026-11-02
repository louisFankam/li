<?php

namespace App\Services;

use App\Exceptions\SyncRejectedException;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\SyncEvent;
use App\Support\SyncEventTypes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SyncEventProcessor
{
    public function __construct(
        private SaleService $sales,
        private StockService $stock,
        private AuditLogger $audit,
    ) {}

    /**
     * @throws SyncRejectedException
     * @throws ValidationException
     */
    public function apply(SyncEvent $event): void
    {
        $definition = SyncEventTypes::definition($event->type);

        if ($definition === null) {
            throw new SyncRejectedException("Type d'événement non pris en charge : {$event->type}.");
        }

        if ((int) $event->schema_version !== SyncEventTypes::SCHEMA_VERSION) {
            throw new SyncRejectedException('Version de schéma non prise en charge.');
        }

        $pharmacy = $event->pharmacy;
        if ($definition['pharmacy_required'] && $pharmacy === null) {
            throw new SyncRejectedException('Pharmacie manquante pour cet événement.');
        }

        $user = $event->user;
        if ($user === null) {
            throw new SyncRejectedException('Utilisateur manquant pour cet événement.');
        }

        if ($pharmacy !== null
            && (! $user->canAccessPharmacy($pharmacy)
                || ! $user->hasPermission($definition['permission'], $pharmacy))) {
            throw new SyncRejectedException("Permission {$definition['permission']} requise pour cet événement.");
        }

        $validator = Validator::make($event->payload ?? [], $definition['rules']);
        if ($validator->fails()) {
            throw new SyncRejectedException('Payload invalide : '.$validator->errors()->first());
        }

        match ($event->type) {
            'sale.created' => $this->processSaleCreated($event, $validator->validated()),
            'sale.cancelled' => $this->processSaleCancelled($event, $validator->validated()),
            'cash.opened' => $this->processCashOpened($event, $validator->validated()),
            'cash.closed' => $this->processCashClosed($event, $validator->validated()),
            'cash.movement' => $this->processCashMovement($event, $validator->validated()),
            'stock.adjusted' => $this->processStockAdjusted($event, $validator->validated()),
            'inventory.counted' => $this->processInventoryCounted($event, $validator->validated()),
            default => throw new SyncRejectedException("Type d'événement non pris en charge : {$event->type}."),
        };
    }

    private function processSaleCreated(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);
        $user = $event->user;
        abort_if($user === null, 422, 'Utilisateur manquant.');

        $clientRequestId = $payload['client_request_id'] ?? $event->client_event_id;
        $existing = Sale::query()
            ->where('client_request_id', $clientRequestId)
            ->where('pharmacy_id', $pharmacy->id)
            ->first();

        if ($existing) {
            return;
        }

        $payload['client_request_id'] = $clientRequestId;
        $this->sales->create($user, $pharmacy, $payload, null);
    }

    private function processSaleCancelled(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);
        $sale = Sale::query()
            ->where('client_request_id', $payload['client_request_id'])
            ->where('pharmacy_id', $pharmacy->id)
            ->first();

        if ($sale === null) {
            throw ValidationException::withMessages([
                'client_request_id' => 'Vente locale introuvable pour cette pharmacie.',
            ]);
        }

        $user = $event->user;
        abort_if($user === null, 422, 'Utilisateur manquant.');

        $this->sales->cancel($user, $sale, (string) $payload['reason']);
    }

    private function processCashOpened(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);

        // Même verrou que l'ouverture en ligne : deux push concurrents ne
        // doivent pas créer deux sessions ouvertes sur la même pharmacie.
        Pharmacy::query()->whereKey($pharmacy->id)->lockForUpdate()->firstOrFail();

        if (CashSession::query()->where('pharmacy_id', $pharmacy->id)->where('status', 'open')->exists()) {
            throw ValidationException::withMessages([
                'cash_session' => 'Une session de caisse est déjà ouverte pour cette pharmacie.',
            ]);
        }

        $session = CashSession::create([
            'pharmacy_id' => $pharmacy->id,
            'user_id' => $event->user_id,
            'opening_amount' => (int) $payload['opening_amount'],
            'status' => 'open',
            'opened_at' => $event->occurred_at ?? now(),
        ]);

        $this->audit->log('cash.opened', CashSession::class, $session->id, [], [
            'pharmacy_id' => $pharmacy->id,
            'opening_amount' => $session->opening_amount,
            'source' => 'sync',
        ], actor: $event->user, pharmacy: $pharmacy);
    }

    private function processCashClosed(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);
        $session = $this->findCashSession($event, $payload);

        if ($session === null || $session->status !== 'open') {
            throw ValidationException::withMessages([
                'cash_session' => 'Aucune session de caisse ouverte pour cette clôture.',
            ]);
        }

        $expected = $this->expectedCash($session);
        $closing = (int) $payload['closing_amount'];

        $session->update([
            'closing_amount' => $closing,
            'expected_amount' => $expected,
            'difference' => $closing - $expected,
            'status' => 'closed',
            'closed_at' => $event->occurred_at ?? now(),
            'notes' => $payload['notes'] ?? $session->notes,
        ]);

        $this->audit->log('cash.closed', CashSession::class, $session->id, ['status' => 'open'], [
            'status' => 'closed',
            'expected' => $expected,
            'closing' => $closing,
            'source' => 'sync',
        ], actor: $event->user, pharmacy: $pharmacy);
    }

    private function processCashMovement(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);
        $session = $this->findCashSession($event, $payload);

        if ($session === null || $session->status !== 'open') {
            throw ValidationException::withMessages([
                'cash_session' => 'Aucune session de caisse ouverte pour ce mouvement.',
            ]);
        }

        $movement = CashMovement::create([
            'cash_session_id' => $session->id,
            'type' => $payload['type'],
            'amount' => (int) $payload['amount'],
            'reason' => $payload['reason'] ?? null,
            'user_id' => $event->user_id,
        ]);

        $this->audit->log('cash.movement', CashMovement::class, $movement->id, [], [
            'session_id' => $session->id,
            'type' => $payload['type'],
            'amount' => $movement->amount,
            'source' => 'sync',
        ], actor: $event->user, pharmacy: $pharmacy);
    }

    private function processStockAdjusted(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);
        $delta = (int) ($payload['delta'] ?? $payload['quantity']);

        $this->stock->apply(
            $pharmacy,
            (int) $payload['product_id'],
            $delta,
            $payload['type'],
            $payload['reason'] ?? 'Ajustement hors ligne',
            $event->user_id,
        );

        $this->audit->log('stock.adjusted', null, null, [], [
            'pharmacy_id' => $pharmacy->id,
            'product_id' => (int) $payload['product_id'],
            'type' => $payload['type'],
            'delta' => $delta,
            'source' => 'sync',
        ], actor: $event->user, pharmacy: $pharmacy);
    }

    private function processInventoryCounted(SyncEvent $event, array $payload): void
    {
        $pharmacy = $this->requirePharmacy($event);

        foreach ($payload['counts'] as $count) {
            $this->stock->setCount(
                $pharmacy,
                (int) $count['product_id'],
                (int) $count['counted'],
                $event->user_id,
                'Comptage physique hors ligne',
            );
        }

        $this->audit->log('inventory.counted', null, null, [], [
            'pharmacy_id' => $pharmacy->id,
            'lines' => count($payload['counts']),
            'source' => 'sync',
        ], actor: $event->user, pharmacy: $pharmacy);
    }

    private function findCashSession(SyncEvent $event, array $payload): ?CashSession
    {
        $pharmacy = $this->requirePharmacy($event);

        $query = CashSession::query()
            ->where('pharmacy_id', $pharmacy->id)
            ->lockForUpdate();

        return isset($payload['session_id'])
            ? (clone $query)->whereKey($payload['session_id'])->first()
            : $query->where('status', 'open')->latest()->first();
    }

    private function requirePharmacy(SyncEvent $event): Pharmacy
    {
        $pharmacy = $event->pharmacy;
        if ($pharmacy === null) {
            throw ValidationException::withMessages(['pharmacy_id' => 'Pharmacie manquante.']);
        }

        return $pharmacy;
    }

    private function expectedCash(CashSession $session): int
    {
        $cashPayments = Payment::whereHas('sale', function ($q) use ($session) {
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
}
