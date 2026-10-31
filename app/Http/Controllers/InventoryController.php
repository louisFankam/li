<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\AdjustStockRequest;
use App\Http\Requests\StoreInventoryRequest;
use App\Models\Product;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Services\AuditLogger;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InventoryController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('manage_inventory');
        $pharmacy = $this->pharmacy($request);

        $products = Product::where('is_active', true)->orderBy('name')->get()->map(function ($p) use ($pharmacy) {
            $sl = StockLevel::where('pharmacy_id', $pharmacy->id)->where('product_id', $p->id)->first();

            return [
                'id' => $p->id,
                'name' => $p->name,
                'code' => $p->code,
                'theoretical' => $sl !== null ? $sl->quantity : 0,
            ];
        });

        $movements = StockMovement::with(['product', 'user'])
            ->where('pharmacy_id', $pharmacy->id)
            ->whereIn('type', ['inventaire', 'perte', 'casse', 'correction'])
            ->latest()
            ->take(15)
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'product' => $m->product->name,
                'type' => $m->type,
                'quantity' => $m->quantity,
                'reason' => $m->reason,
                'user' => $m->user?->name,
                'at' => $m->created_at->format('d/m H:i'),
            ]);

        return Inertia::render('Inventaires', [
            'pharmacy' => $pharmacy,
            'products' => $products,
            'movements' => $movements,
        ]);
    }

    public function store(StoreInventoryRequest $request, StockService $stock)
    {
        $pharmacy = $this->pharmacy($request);
        $data = $request->validated();

        DB::transaction(function () use ($data, $pharmacy, $request, $stock) {
            foreach ($data['counts'] as $count) {
                $stock->setCount(
                    $pharmacy->id,
                    (int) $count['product_id'],
                    (int) $count['counted'],
                    $request->user()->id,
                    'Comptage physique'
                );
            }

            app(AuditLogger::class)->log('inventory.counted', null, null, [], [
                'pharmacy_id' => $pharmacy->id,
                'lines' => count($data['counts']),
            ]);
        });

        return redirect()->back()->with('success', 'Inventaire enregistré');
    }

    public function adjust(AdjustStockRequest $request, StockService $stock)
    {
        $data = $request->validated();
        $pharmacy = $this->pharmacy($request);

        // Perte/casse = toujours une sortie, quel que soit le signe saisi.
        // Correction = mouvement signé explicite.
        $delta = $data['type'] === 'correction'
            ? (int) $data['quantity']
            : -abs((int) $data['quantity']);

        DB::transaction(function () use ($data, $delta, $pharmacy, $request, $stock) {
            $stock->apply(
                $pharmacy->id,
                (int) $data['product_id'],
                $delta,
                $data['type'],
                $data['reason'],
                $request->user()->id
            );

            app(AuditLogger::class)->log('stock.adjusted', null, null, [], [
                'pharmacy_id' => $pharmacy->id,
                'product_id' => $data['product_id'],
                'type' => $data['type'],
                'delta' => $delta,
                'reason' => $data['reason'],
            ]);
        });

        return redirect()->back()->with('success', 'Ajustement enregistré');
    }
}
