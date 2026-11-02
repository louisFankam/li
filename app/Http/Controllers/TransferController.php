<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\ReceiveTransferRequest;
use App\Http\Requests\ShipTransferRequest;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TransferController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Transfer::class);
        $context = $this->context();
        $ids = $context->accessibleIds();
        $user = $request->user();

        $transfers = Transfer::with(['fromPharmacy', 'toPharmacy', 'items.product', 'requester'])
            ->where(function ($query) use ($ids) {
                $query->whereIn('from_pharmacy_id', $ids)
                    ->orWhereIn('to_pharmacy_id', $ids);
            })
            ->latest()
            ->get()
            ->map(fn ($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'from' => $t->fromPharmacy->name,
                'from_id' => $t->from_pharmacy_id,
                'to' => $t->toPharmacy->name,
                'to_id' => $t->to_pharmacy_id,
                'status' => $t->status,
                'can_ship' => $user->can('ship', $t),
                'can_receive' => $user->can('receive', $t),
                'can_cancel' => $user->can('cancel', $t),
                'items' => $t->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product' => $i->product->name,
                    'product_id' => $i->product_id,
                    'requested' => $i->quantity_requested,
                    'shipped' => $i->quantity_shipped,
                    'received' => $i->quantity_received,
                ]),
                'date' => $t->created_at->format('d M · H:i'),
            ]);

        return Inertia::render('Transfers', [
            'transfers' => $transfers,
            'pharmacies' => Pharmacy::whereIn('id', $ids)->orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreTransferRequest $request)
    {
        $data = $request->validated();

        abort_unless($this->context()->canAccess((int) $data['from_pharmacy_id']), 404);
        abort_unless($this->context()->canAccess((int) $data['to_pharmacy_id']), 404);

        DB::transaction(function () use ($data, $request) {
            $transfer = Transfer::create([
                'reference' => 'TRF-'.Str::ulid(),
                'from_pharmacy_id' => $data['from_pharmacy_id'],
                'to_pharmacy_id' => $data['to_pharmacy_id'],
                'status' => 'valide',
                'requested_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $item) {
                TransferItem::create([
                    'transfer_id' => $transfer->id,
                    'product_id' => $item['product_id'],
                    'quantity_requested' => (int) $item['quantity'],
                ]);
            }

            app(AuditLogger::class)->log('transfer.created', Transfer::class, $transfer->id, [], [
                'from' => $data['from_pharmacy_id'],
                'to' => $data['to_pharmacy_id'],
            ]);
        });

        return redirect()->back()->with('success', 'Transfert créé');
    }

    public function ship(ShipTransferRequest $request, Transfer $transfer, StockService $stock)
    {
        $this->authorize('ship', $transfer);
        $data = $request->validated();

        DB::transaction(function () use ($transfer, $data, $request, $stock) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($transfer->status, ['valide', 'partiellement_expedie'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ce transfert ne peut plus être expédié.',
                ]);
            }

            $payload = collect($data['items'] ?? [])->keyBy('id');
            $shippedSomething = false;

            foreach ($transfer->items as $item) {
                $remaining = $item->quantity_requested - $item->quantity_shipped;
                $requested = $payload->has($item->id)
                    ? (int) $payload[$item->id]['quantity_shipped']
                    : $remaining;

                if ($requested < 0 || $requested > $remaining) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantité expédiée dépasse la quantité restante.',
                    ]);
                }

                if ($requested === 0) {
                    continue;
                }

                $stock->apply(
                    $transfer->from_pharmacy_id,
                    $item->product_id,
                    -$requested,
                    'transfert_sortant',
                    null,
                    $request->user()->id,
                    'transfer',
                    $transfer->id
                );

                $item->update(['quantity_shipped' => $item->quantity_shipped + $requested]);
                $shippedSomething = true;
            }

            if (! $shippedSomething) {
                throw ValidationException::withMessages([
                    'items' => 'Aucune quantité à expédier.',
                ]);
            }

            $items = TransferItem::where('transfer_id', $transfer->id)->get();
            $allShipped = $items->every(fn (TransferItem $i) => $i->quantity_shipped >= $i->quantity_requested);

            $transfer->update([
                'status' => $allShipped ? 'expedie' : 'partiellement_expedie',
                'shipped_at' => $transfer->shipped_at ?? now(),
            ]);

            app(AuditLogger::class)->log('transfer.shipped', Transfer::class, $transfer->id, [], [
                'status' => $transfer->status,
            ]);
        });

        return redirect()->back()->with('success', 'Transfert expédié');
    }

    public function receive(ReceiveTransferRequest $request, Transfer $transfer, StockService $stock)
    {
        $this->authorize('receive', $transfer);
        $data = $request->validated();

        DB::transaction(function () use ($transfer, $data, $request, $stock) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (! in_array($transfer->status, ['expedie', 'partiellement_expedie', 'partiellement_recu'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ce transfert n’est pas en attente de réception.',
                ]);
            }

            foreach ($data['items'] as $payload) {
                $item = TransferItem::whereKey($payload['id'])
                    ->where('transfer_id', $transfer->id)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Une ligne ne correspond pas à ce transfert.']);
                }

                $previous = $item->quantity_received;
                $new = (int) $payload['quantity_received'];

                if ($new < $previous || $new > $item->quantity_shipped) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantité reçue doit être comprise entre la quantité déjà reçue et la quantité expédiée.',
                    ]);
                }

                $delta = $new - $previous;
                if ($delta > 0) {
                    $stock->apply(
                        $transfer->to_pharmacy_id,
                        $item->product_id,
                        $delta,
                        'transfert_entrant',
                        null,
                        $request->user()->id,
                        'transfer',
                        $transfer->id
                    );
                }

                $item->update(['quantity_received' => $new]);
            }

            // État recalculé sur toutes les lignes : un payload partiel ne doit
            // pas clôturer le transfert.
            $items = TransferItem::where('transfer_id', $transfer->id)->get();
            $allReceived = $items->every(fn (TransferItem $i) => $i->quantity_received >= $i->quantity_shipped);

            $transfer->update([
                'status' => $allReceived ? 'recu' : 'partiellement_recu',
                'received_at' => $allReceived ? now() : null,
            ]);

            app(AuditLogger::class)->log('transfer.received', Transfer::class, $transfer->id, [], [
                'status' => $transfer->status,
            ]);
        });

        return redirect()->back()->with('success', 'Réception enregistrée');
    }

    public function cancel(Request $request, Transfer $transfer, StockService $stock)
    {
        $this->authorize('cancel', $transfer);

        DB::transaction(function () use ($transfer, $request, $stock) {
            $transfer = Transfer::whereKey($transfer->id)->lockForUpdate()->firstOrFail();

            if (in_array($transfer->status, ['recu', 'annule'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Ce transfert ne peut plus être annulé.',
                ]);
            }

            // Si déjà expédié, on remet le stock non reçu dans la pharmacie source.
            foreach ($transfer->items as $item) {
                $restock = $item->quantity_shipped - $item->quantity_received;
                if ($restock <= 0) {
                    continue;
                }

                $stock->apply(
                    $transfer->from_pharmacy_id,
                    $item->product_id,
                    $restock,
                    'transfert_annule',
                    null,
                    $request->user()->id,
                    'transfer',
                    $transfer->id
                );
            }

            $transfer->update(['status' => 'annule']);

            app(AuditLogger::class)->log('transfer.cancelled', Transfer::class, $transfer->id, [], [
                'status' => 'annule',
            ]);
        });

        return redirect()->back()->with('success', 'Transfert annulé');
    }
}
