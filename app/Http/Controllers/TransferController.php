<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class TransferController extends Controller
{
    public function index()
    {
        $transfers = Transfer::with(['fromPharmacy', 'toPharmacy', 'items.product', 'requester'])
            ->latest()->get()->map(fn($t) => [
                'id' => $t->id,
                'reference' => $t->reference,
                'from' => $t->fromPharmacy->name,
                'to' => $t->toPharmacy->name,
                'status' => $t->status,
                'items' => $t->items->map(fn($i) => [
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
            'pharmacies' => Pharmacy::orderBy('name')->get(),
            'products' => Product::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'from_pharmacy_id' => 'required|exists:pharmacies,id',
            'to_pharmacy_id' => 'required|exists:pharmacies,id|different:from_pharmacy_id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'notes' => 'nullable|string',
        ]);
        $ref = 'TRF-' . (Transfer::count() + 232);
        $t = Transfer::create([
            'reference' => $ref,
            'from_pharmacy_id' => $data['from_pharmacy_id'],
            'to_pharmacy_id' => $data['to_pharmacy_id'],
            'status' => 'valide',
            'requested_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);
        foreach ($data['items'] as $it) {
            TransferItem::create([
                'transfer_id' => $t->id,
                'product_id' => $it['product_id'],
                'quantity_requested' => $it['quantity'],
            ]);
        }
        return redirect()->back()->with('success', 'Transfert créé');
    }

    public function ship(Request $request, Transfer $transfer)
    {
        if ($transfer->status !== 'valide') {
            return redirect()->back()->with('error', 'Statut invalide');
        }
        DB::transaction(function () use ($transfer) {
            foreach ($transfer->items as $item) {
                $sl = StockLevel::where('pharmacy_id', $transfer->from_pharmacy_id)
                    ->where('product_id', $item->product_id)->lockForUpdate()->first();
                $qty = $item->quantity_requested;
                if (!$sl || $sl->quantity < $qty) {
                    throw new \RuntimeException('Stock insuffisant pour expédition');
                }
                $sl->decrement('quantity', $qty);
                $item->update(['quantity_shipped' => $qty]);
                StockMovement::create([
                    'pharmacy_id' => $transfer->from_pharmacy_id,
                    'product_id' => $item->product_id,
                    'type' => 'transfert_sortant',
                    'quantity' => -$qty,
                    'reference_type' => 'transfer',
                    'reference_id' => $transfer->id,
                    'user_id' => request()->user()->id,
                ]);
            }
            $transfer->update(['status' => 'expedie', 'shipped_at' => now()]);
        });
        return redirect()->back()->with('success', 'Transfert expédié');
    }

    public function receive(Request $request, Transfer $transfer)
    {
        if (!in_array($transfer->status, ['expedie', 'partiellement_recu'])) {
            return redirect()->back()->with('error', 'Statut invalide');
        }
        $data = $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|exists:transfer_items,id',
            'items.*.quantity_received' => 'required|integer|min:0',
        ]);
        DB::transaction(function () use ($transfer, $data) {
            $totalReceived = 0;
            $totalRequested = 0;
            foreach ($data['items'] as $it) {
                $item = TransferItem::find($it['id']);
                $prev = $item->quantity_received;
                $new = $it['quantity_received'];
                $delta = $new - $prev;
                if ($delta > 0) {
                    StockLevel::firstOrCreate(['pharmacy_id' => $transfer->to_pharmacy_id, 'product_id' => $item->product_id], ['quantity' => 0])
                        ->increment('quantity', $delta);
                    StockMovement::create([
                        'pharmacy_id' => $transfer->to_pharmacy_id,
                        'product_id' => $item->product_id,
                        'type' => 'transfert_entrant',
                        'quantity' => $delta,
                        'reference_type' => 'transfer',
                        'reference_id' => $transfer->id,
                        'user_id' => request()->user()->id,
                    ]);
                }
                $item->update(['quantity_received' => $new]);
                $totalReceived += $new;
                $totalRequested += $item->quantity_requested;
            }
            if ($totalReceived >= $totalRequested) {
                $transfer->update(['status' => 'recu', 'received_at' => now()]);
            } else {
                $transfer->update(['status' => 'partiellement_recu']);
            }
        });
        return redirect()->back()->with('success', 'Réception enregistrée');
    }

    public function cancel(Transfer $transfer)
    {
        $transfer->update(['status' => 'annule']);
        return redirect()->back()->with('success', 'Transfert annulé');
    }
}
