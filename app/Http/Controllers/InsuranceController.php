<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\UpdateClaimRequest;
use App\Models\InsuranceClaim;
use App\Models\InsuranceCompany;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class InsuranceController extends Controller
{
    use UsesActivePharmacy;

    private const TRANSITIONS = [
        'a_facturer' => ['envoye', 'annule'],
        'envoye' => ['rembourse', 'rejete', 'annule'],
        'rembourse' => [],
        'rejete' => [],
        'annule' => [],
    ];

    public function index(Request $request)
    {
        $this->authorize('manage_insurance');
        $pharmacy = $this->pharmacy($request);

        $base = InsuranceClaim::query()
            ->whereHas('sale', fn ($q) => $q->where('pharmacy_id', $pharmacy->id));

        $from = $request->date('from')?->startOfDay();
        $to = $request->date('to')?->endOfDay();
        if ($from) {
            $base->where('created_at', '>=', $from);
        }
        if ($to) {
            $base->where('created_at', '<=', $to);
        }
        if ($request->filled('status')) {
            $base->where('status', $request->string('status'));
        }

        $claimsQuery = clone $base;

        $globalTotal = (int) (clone $base)->sum('total_amount');

        $companies = InsuranceCompany::where('is_active', true)->get()->map(function ($c) use ($base, $globalTotal) {
            $claims = (clone $base)->where('insurance_company_id', $c->id)->get();
            $ventes = (int) $claims->sum('total_amount');
            $attente = (int) $claims->whereIn('status', ['a_facturer', 'envoye'])->sum('insurer_part');
            $facture = (int) $claims->sum('insurer_part');
            $rembourse = (int) $claims->where('status', 'rembourse')->sum('reimbursed_amount');

            return [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code !== null ? $c->code.' · Tiers-payant' : 'Tiers-payant',
                'ventes' => number_format($ventes, 0, ',', ' ').' GNF',
                'ventes_raw' => $ventes,
                'part' => (int) round(($ventes / max(1, $globalTotal)) * 100),
                'dossiers' => $claims->count(),
                'attente' => number_format($attente, 0, ',', ' ').' GNF',
                'attente_raw' => $attente,
                'rembourse' => number_format($rembourse, 0, ',', ' ').' GNF',
                'solde' => number_format(max(0, $facture - $rembourse), 0, ',', ' ').' GNF',
                'solde_raw' => max(0, $facture - $rembourse),
            ];
        });

        $claims = $claimsQuery->with(['sale.customer', 'insuranceCompany'])
            ->latest()
            ->take(50)
            ->get()
            ->map(fn ($c) => [
                'id' => 'PEC-'.$c->id,
                'claim_id' => $c->id,
                'client' => $c->sale->customer !== null ? $c->sale->customer->name : 'Client comptoir',
                'assureur_nom' => $c->insuranceCompany->name,
                'taux' => 100 - (int) round(($c->client_part / max(1, $c->total_amount)) * 100),
                'date' => $c->created_at->format('d M · H:i'),
                'total' => number_format($c->total_amount, 0, ',', ' ').' GNF',
                'assureur' => number_format($c->insurer_part, 0, ',', ' ').' GNF',
                'tclient' => number_format($c->client_part, 0, ',', ' ').' GNF',
                'rembourse' => number_format($c->reimbursed_amount, 0, ',', ' ').' GNF',
                'reference' => $c->reference,
                'statut' => $c->status,
                'statut_label' => $this->statusLabel($c->status),
                'transitions' => self::TRANSITIONS[$c->status] ?? [],
            ]);

        $all = clone $base;

        $assured = (int) (clone $all)->sum('total_amount');
        $insurer = (int) (clone $all)->sum('insurer_part');
        $client = (int) (clone $all)->sum('client_part');

        $totals = [
            'assured' => number_format($assured, 0, ',', ' ').' GNF',
            'insurer' => number_format($insurer, 0, ',', ' ').' GNF',
            'client' => number_format($client, 0, ',', ' ').' GNF',
            'pending' => number_format((clone $all)->whereIn('status', ['a_facturer', 'envoye'])->sum('insurer_part'), 0, ',', ' ').' GNF',
            'reimbursed' => number_format((clone $all)->where('status', 'rembourse')->sum('reimbursed_amount'), 0, ',', ' ').' GNF',
            'dossiers' => (clone $all)->whereIn('status', ['a_facturer', 'envoye'])->count().' dossiers ouverts',
            'insurer_share' => $assured > 0 ? (int) round($insurer / $assured * 100) : 0,
            'client_share' => $assured > 0 ? (int) round($client / $assured * 100) : 0,
        ];

        // Bordereau : uniquement les dossiers réellement en attente de facturation.
        $bordereau = $companies
            ->filter(fn (array $c) => $c['attente_raw'] > 0)
            ->map(fn (array $c) => [
                'id' => $c['id'],
                'name' => $c['name'],
                'dossiers' => (clone $base)->where('insurance_company_id', $c['id'])->whereIn('status', ['a_facturer', 'envoye'])->count(),
                'montant' => $c['attente'],
                'montant_raw' => $c['attente_raw'],
            ])
            ->values();

        return Inertia::render('Assurances', [
            'pharmacy' => $pharmacy,
            'companies' => $companies,
            'claims' => $claims,
            'totals' => $totals,
            'bordereau' => $bordereau,
            'filters' => [
                'from' => $from?->toDateString(),
                'to' => $to?->toDateString(),
                'status' => $request->input('status'),
            ],
        ]);
    }

    public function updateStatus(UpdateClaimRequest $request, InsuranceClaim $claim)
    {
        $this->authorize('update', $claim);
        $data = $request->validated();

        DB::transaction(function () use ($claim, $data) {
            $claim = InsuranceClaim::query()->whereKey($claim->id)->lockForUpdate()->firstOrFail();

            $allowed = self::TRANSITIONS[$claim->status] ?? [];
            if (! in_array($data['status'], $allowed, true)) {
                throw ValidationException::withMessages([
                    'status' => "Transition invalide depuis le statut « {$this->statusLabel($claim->status)} ».",
                ]);
            }

            $update = [
                'status' => $data['status'],
                'reference' => $data['reference'] ?? $claim->reference,
                'notes' => $data['notes'] ?? $claim->notes,
            ];

            if ($data['status'] === 'rembourse') {
                $reimbursed = (int) ($data['reimbursed_amount'] ?? $claim->insurer_part);

                if ($reimbursed > $claim->insurer_part) {
                    throw ValidationException::withMessages([
                        'reimbursed_amount' => 'Le montant remboursé ne peut pas dépasser la part assureur ('.number_format($claim->insurer_part, 0, ',', ' ').' GNF).',
                    ]);
                }

                $update['reimbursed_amount'] = $reimbursed;
                $update['reimbursed_at'] = now();
            }

            $old = $claim->only(['status', 'reimbursed_amount']);
            $claim->update($update);

            app(AuditLogger::class)->log('insurance.status', InsuranceClaim::class, $claim->id, $old, [
                'status' => $claim->status,
                'reimbursed_amount' => $claim->reimbursed_amount,
                'reference' => $claim->reference,
            ]);
        });

        return redirect()->back()->with('success', 'Dossier mis à jour');
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'rembourse' => 'Remboursé',
            'rejete' => 'Rejeté',
            'envoye' => 'Envoyé',
            'annule' => 'Annulé',
            default => 'À facturer',
        };
    }
}
