<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Models\Customer;
use App\Models\InsuranceClaim;
use App\Models\InsuranceCompany;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\StockLevel;
use App\Models\StockMovement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SaleController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $pharmacy = $this->pharmacy($request);
        $products = Product::where('is_active', true)->get()->map(function ($p) use ($pharmacy) {
            $sl = StockLevel::where('pharmacy_id', $pharmacy->id)->where('product_id', $p->id)->first();
            $stock = $sl?->quantity ?? 0;
            return [
                'id' => $p->id,
                'name' => $p->name,
                'labo' => $p->laboratory . ' · ' . ($p->form ?? 'Comprimés'),
                'price' => number_format($p->sale_price, 0, ',', ' ') . ' GNF',
                'price_raw' => $p->sale_price,
                'stock' => $stock,
                'tag' => $stock === 0 ? 'Rupture — bloqué' : ($stock < $p->alert_threshold ? 'Stock faible' : null),
            ];
        });

        $insurers = InsuranceCompany::where('is_active', true)->get()->map(fn($i) => ['id' => $i->id, 'name' => $i->name, 'code' => $i->code]);
        $customers = Customer::all()->map(fn($c) => ['id' => $c->id, 'name' => $c->name]);

        return Inertia::render('Vente', [
            'pharmacy' => $pharmacy,
            'products' => $products,
            'insurers' => $insurers,
            'customers' => $customers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|integer|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|string',
            'payments.*.amount' => 'required|integer|min:0',
            'customer_id' => 'nullable|exists:customers,id',
            'insurance_company_id' => 'nullable|exists:insurance_companies,id',
            'discount_amount' => 'nullable|integer|min:0',
            'discount_percent' => 'nullable|integer|min:0|max:100',
        ]);

        return DB::transaction(function () use ($data, $request) {
            $pharmacyId = $data['pharmacy_id'];
            $subtotal = 0;
            foreach ($data['items'] as $it) {
                $subtotal += $it['unit_price'] * $it['quantity'];
            }
            $discountAmount = $data['discount_amount'] ?? 0;
            $total = max(0, $subtotal - $discountAmount);

            $clientPart = $total;
            $insurerPart = 0;
            if (!empty($data['insurance_company_id'])) {
                // default 80/20 split
                $insurerPart = (int) round($total * 0.8);
                $clientPart = $total - $insurerPart;
            }

            $sale = Sale::create([
                'sale_number' => 'TK-' . now()->format('ymd') . '-' . str_pad((string) (Sale::count() + 1), 5, '0', STR_PAD_LEFT),
                'pharmacy_id' => $pharmacyId,
                'user_id' => $request->user()->id,
                'customer_id' => $data['customer_id'] ?? null,
                'insurance_company_id' => $data['insurance_company_id'] ?? null,
                'subtotal' => $subtotal,
                'discount_amount' => $discountAmount,
                'discount_percent' => $data['discount_percent'] ?? null,
                'total' => $total,
                'client_part' => $clientPart,
                'insurer_part' => $insurerPart,
                'status' => 'completed',
            ]);

            foreach ($data['items'] as $it) {
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'product_id' => $it['product_id'],
                    'quantity' => $it['quantity'],
                    'unit' => 'boite',
                    'unit_price' => $it['unit_price'],
                    'total' => $it['unit_price'] * $it['quantity'],
                ]);

                // stock decrement with lock
                $sl = StockLevel::where('pharmacy_id', $pharmacyId)->where('product_id', $it['product_id'])->lockForUpdate()->first();
                if (!$sl || $sl->quantity < $it['quantity']) {
                    throw new \RuntimeException('Stock insuffisant');
                }
                $sl->decrement('quantity', $it['quantity']);
                StockMovement::create([
                    'pharmacy_id' => $pharmacyId,
                    'product_id' => $it['product_id'],
                    'type' => 'vente',
                    'quantity' => -$it['quantity'],
                    'reference_type' => 'sale',
                    'reference_id' => $sale->id,
                    'user_id' => $request->user()->id,
                ]);
            }

            foreach ($data['payments'] as $p) {
                Payment::create([
                    'sale_id' => $sale->id,
                    'method' => $p['method'],
                    'amount' => $p['amount'],
                ]);
            }

            if (!empty($data['insurance_company_id'])) {
                InsuranceClaim::create([
                    'sale_id' => $sale->id,
                    'insurance_company_id' => $data['insurance_company_id'],
                    'total_amount' => $total,
                    'client_part' => $clientPart,
                    'insurer_part' => $insurerPart,
                    'status' => 'a_facturer',
                ]);
            }

            return redirect()->route('vente')->with('success', 'Vente enregistrée : ' . $sale->sale_number);
        });
    }

}
