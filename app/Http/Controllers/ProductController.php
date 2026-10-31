<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('viewAny', Product::class);
        $pharmacy = $this->pharmacy($request);

        $products = Product::with('category')->where('is_active', true)->orderBy('name')->get()->map(function ($p) use ($pharmacy) {
            $sl = StockLevel::where('pharmacy_id', $pharmacy->id)->where('product_id', $p->id)->first();
            $stock = $sl !== null ? $sl->quantity : 0;
            $status = $stock === 0 ? 'Rupture' : ($stock < $p->alert_threshold ? 'Faible' : 'OK');
            if ($stock > 0 && $stock < ($p->alert_threshold / 2)) {
                $status = 'Critique';
            }

            $marge = $p->purchase_price > 0
                ? (int) round((($p->sale_price - $p->purchase_price) / $p->purchase_price) * 100)
                : null;

            return [
                'id' => $p->id,
                'name' => $p->name,
                'dci' => $p->generic_name,
                'labo' => $p->laboratory,
                'category' => $p->category?->name,
                'stock' => $stock,
                'seuil' => $p->alert_threshold,
                'prix' => number_format($p->sale_price, 0, ',', ' '),
                'marge' => $marge !== null ? $marge.' %' : '—',
                'statut' => $status,
                'code' => $p->code,
                'barcode' => $p->barcode,
                'category_id' => $p->category_id,
                'sale_price' => $p->sale_price,
                'purchase_price' => $p->purchase_price,
                'alert_threshold' => $p->alert_threshold,
                'is_active' => $p->is_active,
            ];
        });

        $levels = StockLevel::where('pharmacy_id', $pharmacy->id)->with('product')->get();

        return Inertia::render('Stocks', [
            'pharmacy' => $pharmacy,
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'stats' => [
                'value' => number_format($levels->sum(fn ($s) => $s->quantity * ($s->product->sale_price ?? 0)), 0, ',', ' ').' GNF',
                'ruptures' => $levels->where('quantity', 0)->count().' produits',
                'alerts' => $levels->filter(fn ($s) => $s->quantity > 0 && $s->quantity < ($s->product->alert_threshold ?? 0))->count().' produits',
                'references' => $products->count().' références',
            ],
        ]);
    }

    public function store(StoreProductRequest $request)
    {
        $data = $request->validated();
        $product = Product::create($data + ['is_active' => true, 'base_unit' => $data['base_unit'] ?? 'boite']);

        foreach (Pharmacy::all() as $ph) {
            StockLevel::firstOrCreate(['pharmacy_id' => $ph->id, 'product_id' => $product->id], ['quantity' => 0]);
        }

        app(AuditLogger::class)->log('product.created', Product::class, $product->id, [], $product->toArray());

        return redirect()->back()->with('success', 'Produit créé');
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = $request->validated();
        $data['is_active'] = $request->boolean('is_active', $product->is_active);
        $old = $product->toArray();
        $product->update($data);

        app(AuditLogger::class)->log('product.updated', Product::class, $product->id, $old, $product->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Produit mis à jour');
    }

    public function destroy(Product $product)
    {
        $this->authorize('delete', $product);

        $old = $product->toArray();
        $product->update(['is_active' => false]);
        app(AuditLogger::class)->log('product.deactivated', Product::class, $product->id, $old, $product->fresh()?->toArray() ?? []);

        return redirect()->back()->with('success', 'Produit désactivé');
    }
}
