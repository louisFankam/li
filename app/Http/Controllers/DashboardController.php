<?php

namespace App\Http\Controllers;

use App\Models\Pharmacy;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockLevel;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $pharmacies = Pharmacy::where('is_active', true)->get();
        $today = now()->toDateString();

        $pharmaStats = $pharmacies->map(function ($ph) use ($today) {
            $ca = Sale::where('pharmacy_id', $ph->id)->whereDate('created_at', $today)->sum('total');
            $tickets = Sale::where('pharmacy_id', $ph->id)->whereDate('created_at', $today)->count();
            return [
                'id' => $ph->id,
                'name' => $ph->name,
                'ville' => $ph->city,
                'ca' => number_format($ca, 0, ',', ' '),
                'tickets' => $tickets,
                'panier' => $tickets ? number_format((int) round($ca / $tickets), 0, ',', ' ') : '0',
                'progress' => min(100, (int) round(($ca / 50000000) * 100)),
                'badge' => null,
            ];
        });

        $totalCa = Sale::whereDate('created_at', $today)->sum('total');
        $totalTickets = Sale::whereDate('created_at', $today)->count();

        $lowStock = StockLevel::with(['product', 'pharmacy'])
            ->get()
            ->filter(fn($s) => $s->quantity < ($s->product->alert_threshold ?? 0))
            ->take(5)
            ->map(fn($s) => [
                'name' => $s->product->name,
                'pharma' => $s->pharmacy->name,
                'stock' => $s->quantity,
                'seuil' => $s->product->alert_threshold,
                'dot' => $s->quantity === 0 ? 'bg-danger' : ($s->quantity < ($s->product->alert_threshold / 2) ? 'bg-danger' : 'bg-warning'),
            ])->values();

        $lastSales = Sale::with(['customer', 'pharmacy'])->latest()->take(4)->get()->map(fn($s) => [
            'id' => '#TK-' . $s->id,
            'heure' => $s->created_at->format('H:i'),
            'client' => $s->customer?->name ?? 'Client comptoir',
            'items' => $s->items()->count() ?: 1,
            'total' => number_format($s->total, 0, ',', ' ') . ' GNF',
            'mode' => $s->insuranceCompany ? 'Assurance' : 'Espèces',
            'status' => $s->status === 'completed' ? 'Payé' : 'En attente',
        ]);

        return Inertia::render('Dashboard', [
            'stats' => [
                'ca' => number_format($totalCa, 0, ',', ' ') . ' GNF',
                'tickets' => number_format($totalTickets, 0, ',', ' '),
                'lowStock' => $lowStock->count() . ' produits',
                'pendingInsurance' => number_format(Sale::whereNotNull('insurance_company_id')->sum('insurer_part'), 0, ',', ' ') . ' GNF',
            ],
            'pharmacies' => $pharmaStats,
            'lowStock' => $lowStock,
            'lastSales' => $lastSales,
        ]);
    }
}
