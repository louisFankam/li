<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Models\CashSession;
use App\Models\InsuranceClaim;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\StockLevel;
use App\Models\Transfer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $this->authorize('view_reports');
        $pharmacy = $this->pharmacy($request);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $salesQuery = Sale::with(['user', 'customer'])
            ->where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $sales = (clone $salesQuery)->latest()->take(100)->get()->map(fn (Sale $s) => [
            'number' => $s->sale_number,
            'date' => $s->created_at->format('d/m/Y H:i'),
            'vendeur' => $s->user->name,
            'client' => $s->customer !== null ? $s->customer->name : 'Comptoir',
            'total' => $s->total,
            'status' => $s->status,
        ]);

        $totals = [
            'ventes' => (int) (clone $salesQuery)->where('status', 'completed')->sum('total'),
            'tickets' => (clone $salesQuery)->where('status', 'completed')->count(),
            'remises' => (int) (clone $salesQuery)->where('status', 'completed')->sum('discount_amount'),
            'assureur' => (int) (clone $salesQuery)->where('status', 'completed')->sum('insurer_part'),
        ];

        $bySeller = (clone $salesQuery)
            ->selectRaw('user_id, COUNT(*) as tickets, SUM(total) as total')
            ->where('status', 'completed')
            ->groupBy('user_id')
            ->with('user')
            ->get()
            ->map(fn (Sale $s) => [
                'vendeur' => $s->user->name,
                'tickets' => (int) $s->getAttribute('tickets'),
                'total' => (int) $s->getAttribute('total'),
            ]);

        $byPayment = Payment::query()
            ->whereHas('sale', fn ($q) => $q->where('pharmacy_id', $pharmacy->id)
                ->where('status', 'completed')
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to))
            ->selectRaw('method, COUNT(*) as operations, SUM(amount) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($p) => [
                'method' => $p->method,
                'operations' => (int) $p->getAttribute('operations'),
                'total' => (int) $p->getAttribute('total'),
            ]);

        $stockAlerts = StockLevel::with('product')
            ->where('pharmacy_id', $pharmacy->id)
            ->get()
            ->filter(fn (StockLevel $s) => $s->quantity <= $s->product->alert_threshold)
            ->map(fn (StockLevel $s) => [
                'produit' => $s->product->name,
                'stock' => $s->quantity,
                'seuil' => $s->product->alert_threshold,
            ])->values();

        $purchases = Purchase::with('supplier')
            ->where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Purchase $p) => [
                'reference' => $p->invoice_number ?? 'ACH-'.$p->id,
                'fournisseur' => $p->supplier->name,
                'date' => $p->created_at->format('d/m/Y'),
                'total' => $p->total,
                'status' => $p->status,
                'payment_status' => $p->payment_status,
            ]);

        $claims = InsuranceClaim::with('insuranceCompany')
            ->whereHas('sale', fn ($q) => $q->where('pharmacy_id', $pharmacy->id)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to))
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (InsuranceClaim $c) => [
                'reference' => $c->reference ?? 'PEC-'.$c->id,
                'assureur' => $c->insuranceCompany->name,
                'date' => $c->created_at->format('d/m/Y'),
                'total' => $c->total_amount,
                'assureur_part' => $c->insurer_part,
                'client_part' => $c->client_part,
                'status' => $c->status,
            ]);

        $cashSessions = CashSession::with('user')
            ->where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (CashSession $s) => [
                'caissier' => $s->user->name,
                'opened_at' => $s->opened_at?->format('d/m/Y H:i'),
                'closed_at' => $s->closed_at?->format('d/m/Y H:i'),
                'opening' => $s->opening_amount,
                'expected' => $s->expected_amount,
                'closing' => $s->closing_amount,
                'difference' => $s->difference,
                'status' => $s->status,
            ]);

        $transfers = Transfer::with(['fromPharmacy', 'toPharmacy'])
            ->where(fn ($q) => $q->where('from_pharmacy_id', $pharmacy->id)->orWhere('to_pharmacy_id', $pharmacy->id))
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()
            ->take(50)
            ->get()
            ->map(fn (Transfer $t) => [
                'reference' => $t->reference,
                'from' => $t->fromPharmacy->name,
                'to' => $t->toPharmacy->name,
                'date' => $t->created_at->format('d/m/Y'),
                'status' => $t->status,
            ]);

        return Inertia::render('Rapports', [
            'pharmacy' => $pharmacy,
            'sales' => $sales,
            'filters' => ['from' => $from, 'to' => $to],
            'totals' => $totals,
            'bySeller' => $bySeller,
            'byPayment' => $byPayment,
            'stockAlerts' => $stockAlerts,
            'purchases' => $purchases,
            'claims' => $claims,
            'cashSessions' => $cashSessions,
            'transfers' => $transfers,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $this->authorize('export_data');
        $pharmacy = $this->pharmacy($request);
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $type = $request->string('type', 'ventes')->toString();

        if ($type === 'assurances') {
            return $this->exportClaims($pharmacy, $from, $to);
        }

        $rows = Sale::with(['user', 'customer'])
            ->where('pharmacy_id', $pharmacy->id)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->latest()->get();

        $generatedAt = now()->format('d/m/Y H:i');
        $pharmacyName = $pharmacy->name;

        $response = new StreamedResponse(function () use ($rows, $from, $to, $generatedAt, $pharmacyName) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['# PharmaGest — Export des ventes']);
            fputcsv($out, ['Pharmacie', $pharmacyName]);
            fputcsv($out, ['Période', $from.' → '.$to]);
            fputcsv($out, ['Généré le', $generatedAt]);
            fputcsv($out, []);
            fputcsv($out, ['N° vente', 'Date', 'Vendeur', 'Client', 'Sous-total', 'Remise', 'Part assureur', 'Total', 'Statut']);
            foreach ($rows as $s) {
                fputcsv($out, [
                    $s->sale_number,
                    $s->created_at,
                    $s->user->name,
                    $s->customer !== null ? $s->customer->name : 'Comptoir',
                    $s->subtotal,
                    $s->discount_amount,
                    $s->insurer_part,
                    $s->total,
                    $s->status,
                ]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="ventes-'.$from.'-'.$to.'.csv"');

        return $response;
    }

    private function exportClaims($pharmacy, string $from, string $to): StreamedResponse
    {
        $rows = InsuranceClaim::with(['insuranceCompany', 'sale.customer'])
            ->whereHas('sale', fn ($q) => $q->where('pharmacy_id', $pharmacy->id)
                ->whereDate('created_at', '>=', $from)
                ->whereDate('created_at', '<=', $to))
            ->latest()
            ->get();

        $generatedAt = now()->format('d/m/Y H:i');
        $pharmacyName = $pharmacy->name;

        $response = new StreamedResponse(function () use ($rows, $from, $to, $generatedAt, $pharmacyName) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['# PharmaGest — Bordereau assurances']);
            fputcsv($out, ['Pharmacie', $pharmacyName]);
            fputcsv($out, ['Période', $from.' → '.$to]);
            fputcsv($out, ['Généré le', $generatedAt]);
            fputcsv($out, []);
            fputcsv($out, ['Référence', 'Assureur', 'Bénéficiaire', 'Date', 'Total', 'Part assureur', 'Part client', 'Remboursé', 'Statut']);
            foreach ($rows as $c) {
                fputcsv($out, [
                    $c->reference ?? 'PEC-'.$c->id,
                    $c->insuranceCompany?->name,
                    $c->sale?->customer?->name ?? 'Comptoir',
                    $c->created_at,
                    $c->total_amount,
                    $c->insurer_part,
                    $c->client_part,
                    $c->reimbursed_amount,
                    $c->status,
                ]);
            }
            fclose($out);
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="assurances-'.$from.'-'.$to.'.csv"');

        return $response;
    }
}
