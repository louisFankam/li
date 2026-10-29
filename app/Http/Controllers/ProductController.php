<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Models\Category;
use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProductController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $pharmacy = $this->pharmacy($request);
        $products = Product::with('category')->where('is_active', true)->get()->map(function ($p) use ($pharmacy) {
            $sl = StockLevel::where('pharmacy_id', $pharmacy->id)->where('product_id', $p->id)->first();
            $stock = $sl?->quantity ?? 0;
            $status = $stock === 0 ? 'Rupture' : ($stock < $p->alert_threshold ? 'Faible' : 'OK');
            if ($stock > 0 && $stock < ($p->alert_threshold / 2)) $status = 'Critique';
            return [
                'id' => $p->id,
                'name' => $p->name,
                'dci' => $p->generic_name,
                'labo' => $p->laboratory,
                'lot' => 'Lot ' . strtoupper(substr($p->code, 0, 3)) . '-' . rand(100, 999),
                'stock' => $stock,
                'seuil' => $p->alert_threshold,
                'prix' => number_format($p->sale_price, 0, ',', ' '),
                'marge' => '30%',
                'statut' => $status,
                'code' => $p->code,
                'category_id' => $p->category_id,
                'sale_price' => $p->sale_price,
                'purchase_price' => $p->purchase_price,
                'alert_threshold' => $p->alert_threshold,
                'is_active' => $p->is_active,
            ];
        });

        return Inertia::render('Stocks', [
            'pharmacy' => $pharmacy,
            'products' => $products,
            'categories' => Category::orderBy('name')->get(),
            'stats' => [
                'value' => number_format(StockLevel::where('pharmacy_id', $pharmacy->id)->get()->sum(fn($s) => $s->quantity * ($s->product->sale_price ?? 0)), 0, ',', ' ') . ' GNF',
                'ruptures' => StockLevel::where('pharmacy_id', $pharmacy->id)->where('quantity', 0)->count() . ' produits',
                'alerts' => StockLevel::where('pharmacy_id', $pharmacy->id)->get()->filter(fn($s) => $s->quantity > 0 && $s->quantity < ($s->product->alert_threshold ?? 0))->count() . ' produits',
                'fiability' => '92 %',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:products,code',
            'name' => 'required|string|max:200',
            'generic_name' => 'nullable|string|max:200',
            'laboratory' => 'nullable|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'sale_price' => 'required|integer|min:0',
            'purchase_price' => 'nullable|integer|min:0',
            'alert_threshold' => 'nullable|integer|min:0',
        ]);
        $product = Product::create($data + ['is_active' => true, 'base_unit' => 'boite']);
        foreach (Pharmacy::all() as $ph) {
            StockLevel::firstOrCreate(['pharmacy_id' => $ph->id, 'product_id' => $product->id], ['quantity' => 0]);
        }
        return redirect()->back()->with('success', 'Produit créé');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40|unique:products,code,' . $product->id,
            'name' => 'required|string|max:200',
            'generic_name' => 'nullable|string|max:200',
            'laboratory' => 'nullable|string|max:120',
            'category_id' => 'nullable|exists:categories,id',
            'sale_price' => 'required|integer|min:0',
            'purchase_price' => 'nullable|integer|min:0',
            'alert_threshold' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);
        $data['is_active'] = $request->boolean('is_active', $product->is_active);
        $product->update($data);
        return redirect()->back()->with('success', 'Produit mis à jour');
    }

    public function destroy(Product $product)
    {
        $product->update(['is_active' => false]);
        return redirect()->back()->with('success', 'Produit désactivé');
    }

}
