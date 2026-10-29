<?php

namespace App\Http\Controllers;

use App\Models\InsuranceClaim;
use App\Models\InsuranceCompany;
use App\Models\Pharmacy;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InsuranceController extends Controller
{
    public function index(Request $request)
    {
        $companies = InsuranceCompany::where('is_active', true)->get()->map(function ($c) {
            $claims = InsuranceClaim::where('insurance_company_id', $c->id)->get();
            $ventes = $claims->sum('total_amount');
            $attente = $claims->where('status', 'a_facturer')->sum('insurer_part') + $claims->where('status', 'envoye')->sum('insurer_part');
            $dossiers = $claims->count();
            return [
                'name' => $c->name,
                'code' => $c->code . ' · Tiers-payant',
                'ventes' => number_format($ventes, 0, ',', ' ') . ' GNF',
                'part' => min(100, (int) round(($ventes / max(1, InsuranceClaim::sum('total_amount'))) * 100)),
                'dossiers' => $dossiers,
                'attente' => number_format($attente, 0, ',', ' ') . ' GNF',
                'color' => 'bg-primary',
            ];
        });

        $claims = InsuranceClaim::with(['sale.customer', 'insuranceCompany'])->latest()->take(10)->get()->map(fn($c) => [
            'id' => 'PEC-' . $c->id,
            'client' => ($c->sale?->customer?->name ?? 'Client') . ' · ' . ($c->insuranceCompany?->code ?? '') . ' ' . (100 - (int) round(($c->client_part / max(1, $c->total_amount)) * 100)) . '%',
            'date' => $c->created_at->format('d M · H:i'),
            'total' => number_format($c->total_amount, 0, ',', ' ') . ' GNF',
            'assureur' => number_format($c->insurer_part, 0, ',', ' ') . ' GNF',
            'tclient' => number_format($c->client_part, 0, ',', ' ') . ' GNF',
            'statut' => match($c->status) {
                'rembourse' => 'Remboursé',
                'rejete' => 'Rejeté',
                'envoye' => 'Envoyé',
                'annule' => 'Annulé',
                default => 'À facturer',
            },
        ]);

        $totals = [
            'assured' => number_format(InsuranceClaim::sum('total_amount'), 0, ',', ' ') . ' GNF',
            'insurer' => number_format(InsuranceClaim::sum('insurer_part'), 0, ',', ' ') . ' GNF',
            'client' => number_format(InsuranceClaim::sum('client_part'), 0, ',', ' ') . ' GNF',
            'pending' => number_format(InsuranceClaim::where('status', 'a_facturer')->sum('insurer_part'), 0, ',', ' ') . ' GNF',
            'dossiers' => InsuranceClaim::where('status', 'a_facturer')->count() . ' dossiers ouverts',
        ];

        return Inertia::render('Assurances', [
            'companies' => $companies,
            'claims' => $claims,
            'totals' => $totals,
        ]);
    }
}
