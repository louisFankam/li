<?php

namespace App\Http\Controllers;

use App\Models\CashSession;
use App\Models\InsuranceClaim;
use App\Models\Pharmacy;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Services\PharmacyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request, PharmacyContext $context)
    {
        $this->authorize('view_dashboard');

        $accessible = $context->accessibleIds($request);
        $pharmacies = Pharmacy::whereIn('id', $accessible)->where('is_active', true)->get();
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $pharmaStats = $pharmacies->map(function (Pharmacy $ph) use ($today) {
            $ca = (int) Sale::where('pharmacy_id', $ph->id)->whereDate('created_at', $today)->where('status', 'completed')->sum('total');
            $tickets = Sale::where('pharmacy_id', $ph->id)->whereDate('created_at', $today)->where('status', 'completed')->count();

            return [
                'id' => $ph->id,
                'name' => $ph->name,
                'ville' => $ph->city ?? '—',
                'ca' => number_format($ca, 0, ',', ' '),
                'tickets' => $tickets,
                'panier' => $tickets ? number_format((int) round($ca / $tickets), 0, ',', ' ') : '0',
                'progress' => min(100, (int) round(($ca / 50_000_000) * 100)),
                'badge' => null,
            ];
        });

        $totalCa = (int) Sale::whereIn('pharmacy_id', $accessible)->whereDate('created_at', $today)->where('status', 'completed')->sum('total');
        $yesterdayCa = (int) Sale::whereIn('pharmacy_id', $accessible)->whereDate('created_at', $yesterday)->where('status', 'completed')->sum('total');
        $totalTickets = Sale::whereIn('pharmacy_id', $accessible)->whereDate('created_at', $today)->where('status', 'completed')->count();
        $yesterdayTickets = Sale::whereIn('pharmacy_id', $accessible)->whereDate('created_at', $yesterday)->where('status', 'completed')->count();

        $lowStock = StockLevel::with(['product', 'pharmacy'])
            ->whereIn('pharmacy_id', $accessible)
            ->get()
            ->filter(fn (StockLevel $s) => $s->quantity <= $s->product->alert_threshold)
            ->sortBy(fn (StockLevel $s) => $s->quantity)
            ->take(5)
            ->map(fn (StockLevel $s) => [
                'name' => $s->product->name,
                'pharma' => $s->pharmacy->name,
                'stock' => $s->quantity,
                'seuil' => $s->product->alert_threshold,
                'dot' => $s->quantity === 0 ? 'bg-danger' : ($s->quantity < ($s->product->alert_threshold / 2) ? 'bg-danger' : 'bg-warning'),
            ])->values();

        $lowStockCount = StockLevel::with('product')
            ->whereIn('pharmacy_id', $accessible)
            ->get()
            ->filter(fn (StockLevel $s) => $s->quantity <= $s->product->alert_threshold)
            ->count();

        $pendingClaims = InsuranceClaim::whereHas('sale', fn ($q) => $q->whereIn('pharmacy_id', $accessible))
            ->whereIn('status', ['a_facturer', 'envoye'])
            ->get();

        $lastSales = Sale::with(['customer', 'pharmacy'])
            ->whereIn('pharmacy_id', $accessible)
            ->latest()
            ->take(4)
            ->get()
            ->map(fn (Sale $s) => [
                'id' => $s->sale_number,
                'heure' => $s->created_at->format('H:i'),
                'client' => $s->customer !== null ? $s->customer->name : 'Client comptoir',
                'items' => $s->items()->count() ?: 1,
                'total' => number_format($s->total, 0, ',', ' ').' GNF',
                'mode' => $s->insurance_company_id !== null ? 'Assurance' : 'Espèces',
                'status' => $s->status === 'completed' ? 'Payé' : ($s->status === 'cancelled' ? 'Annulé' : 'En attente'),
            ]);

        $chart = collect(range(6, 0))->map(function (int $offset) use ($accessible) {
            $day = now()->subDays($offset);

            return [
                'label' => $day->locale('fr')->isoFormat('ddd'),
                'date' => $day->toDateString(),
                'total' => (int) Sale::whereIn('pharmacy_id', $accessible)
                    ->whereDate('created_at', $day->toDateString())
                    ->where('status', 'completed')
                    ->sum('total'),
            ];
        });

        $openSession = CashSession::whereIn('pharmacy_id', $accessible)->where('status', 'open')->first();

        return Inertia::render('Dashboard', [
            'stats' => [
                'ca' => number_format($totalCa, 0, ',', ' ').' GNF',
                'tickets' => number_format($totalTickets, 0, ',', ' '),
                'lowStock' => $lowStockCount.' produits',
                'pendingInsurance' => number_format((int) $pendingClaims->sum('insurer_part'), 0, ',', ' ').' GNF',
                'caDelta' => $this->delta($totalCa, $yesterdayCa),
                'ticketsDelta' => $this->delta($totalTickets, $yesterdayTickets),
                'claimsCount' => $pendingClaims->count(),
                'cashOpen' => $openSession !== null,
            ],
            'pharmacies' => $pharmaStats,
            'lowStock' => $lowStock,
            'lastSales' => $lastSales,
            'chart' => $chart,
        ]);
    }

    private function delta(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return $current === 0 ? 0.0 : null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }
}
