<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\ReceivePurchaseRequest;
use App\Http\Requests\StorePurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Purchase::class);
        $pharmacy = $this->pharmacy($request);

        $purchases = Purchase::with(['supplier', 'items.product'])
            ->where('pharmacy_id', $pharmacy->id)
            ->latest()
            ->get()
            ->map(fn ($p) => [
                'id' => $p->id,
                'supplier' => $p->supplier->name,
                'status' => $p->status,
                'invoice_number' => $p->invoice_number,
                'payment_status' => $p->payment_status,
                'total' => number_format($p->total, 0, ',', ' ').' GNF',
                'total_raw' => $p->total,
                'items' => $p->items->map(fn ($i) => [
                    'id' => $i->id,
                    'product' => $i->product->name,
                    'product_id' => $i->product_id,
                    'ordered' => $i->quantity_ordered,
                    'received' => $i->quantity_received,
                    'unit_price' => $i->unit_price,
                ]),
                'ordered_at' => $p->ordered_at?->format('d/m/Y'),
                'received_at' => $p->received_at?->format('d/m/Y'),
            ]);

        return Inertia::render('Achats', [
            'pharmacy' => $pharmacy,
            'purchases' => $purchases,
            'suppliers' => Supplier::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name', 'purchase_price']),
        ]);
    }

    public function store(StorePurchaseRequest $request)
    {
        $data = $request->validated();
        $pharmacy = $this->pharmacy($request);

        DB::transaction(function () use ($data, $pharmacy) {
            $total = 0;
            foreach ($data['items'] as $item) {
                $total += (int) $item['unit_price'] * (int) $item['quantity'];
            }

            $purchase = Purchase::create([
                'supplier_id' => $data['supplier_id'],
                'pharmacy_id' => $pharmacy->id,
                'status' => 'brouillon',
                'invoice_number' => $data['invoice_number'] ?? null,
                'total' => $total,
            ]);

            foreach ($data['items'] as $item) {
                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'quantity_ordered' => (int) $item['quantity'],
                    'quantity_received' => 0,
                    'unit_price' => (int) $item['unit_price'],
                ]);
            }

            app(AuditLogger::class)->log('purchase.created', Purchase::class, $purchase->id, [], [
                'pharmacy_id' => $pharmacy->id,
                'total' => $total,
            ]);
        });

        return redirect()->back()->with('success', 'Commande créée');
    }

    public function receive(ReceivePurchaseRequest $request, Purchase $purchase, StockService $stock)
    {
        $this->authorize('receive', $purchase);
        $data = $request->validated();

        DB::transaction(function () use ($purchase, $data, $request, $stock) {
            $purchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if (! in_array($purchase->status, ['envoyee', 'partiellement_recue'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Seule une commande envoyée peut être réceptionnée.',
                ]);
            }

            foreach ($data['items'] as $payload) {
                $item = PurchaseItem::whereKey($payload['id'])
                    ->where('purchase_id', $purchase->id)
                    ->lockForUpdate()
                    ->first();

                if (! $item) {
                    throw ValidationException::withMessages([
                        'items' => 'Une ligne ne correspond pas à cette commande.',
                    ]);
                }

                $previous = $item->quantity_received;
                $new = (int) $payload['quantity_received'];

                if ($new < $previous) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantité reçue ne peut pas diminuer.',
                    ]);
                }

                if ($new > $item->quantity_ordered) {
                    throw ValidationException::withMessages([
                        'items' => 'La quantité reçue dépasse la quantité commandée.',
                    ]);
                }

                $delta = $new - $previous;
                if ($delta > 0) {
                    $stock->apply(
                        $purchase->pharmacy_id,
                        $item->product_id,
                        $delta,
                        'reception',
                        null,
                        $request->user()->id,
                        'purchase',
                        $purchase->id
                    );

                    // Dernier prix d'achat connu : sert à valoriser le stock.
                    Product::whereKey($item->product_id)->update(['purchase_price' => $item->unit_price]);
                }

                $item->update(['quantity_received' => $new]);
            }

            // L'état est recalculé sur TOUTES les lignes, pas seulement celles
            // du payload : une réception partielle ne doit jamais clôturer la
            // commande tant qu'il reste des unités attendues.
            $items = PurchaseItem::where('purchase_id', $purchase->id)->get();
            $allReceived = $items->every(fn (PurchaseItem $i) => $i->quantity_received >= $i->quantity_ordered);

            $purchase->update([
                'status' => $allReceived ? 'recue' : 'partiellement_recue',
                'received_at' => $allReceived ? now() : null,
            ]);

            app(AuditLogger::class)->log('purchase.received', Purchase::class, $purchase->id, [], [
                'status' => $purchase->status,
            ]);
        });

        return redirect()->back()->with('success', 'Réception enregistrée');
    }

    public function updateStatus(Request $request, Purchase $purchase)
    {
        $this->authorize('update', $purchase);

        $data = $request->validate([
            'status' => 'required|in:brouillon,envoyee,annulee',
            'payment_status' => 'nullable|in:non_paye,partiel,paye',
        ]);

        if (in_array($purchase->status, ['recue', 'annulee'], true)) {
            throw ValidationException::withMessages([
                'status' => 'Cette commande ne peut plus changer de statut.',
            ]);
        }

        if ($data['status'] === 'annulee' && $purchase->status === 'partiellement_recue') {
            throw ValidationException::withMessages([
                'status' => 'Une commande déjà partiellement réceptionnée ne peut pas être annulée.',
            ]);
        }

        $purchase->update(array_filter([
            'status' => $data['status'],
            'payment_status' => $data['payment_status'] ?? null,
            'ordered_at' => $data['status'] === 'envoyee' ? now() : $purchase->ordered_at,
        ], fn ($value) => $value !== null));

        app(AuditLogger::class)->log('purchase.status', Purchase::class, $purchase->id, [], [
            'status' => $purchase->status,
        ]);

        return redirect()->back()->with('success', 'Statut mis à jour');
    }
}
