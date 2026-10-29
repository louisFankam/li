<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Models\CashSession;
use App\Models\Payment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CashController extends Controller
{
    use UsesActivePharmacy;

    public function index(Request $request)
    {
        $pharmacy = $this->pharmacy($request);
        $sessions = CashSession::with('user')->where('pharmacy_id', $pharmacy->id)->latest()->take(5)->get()->map(fn($s) => [
            'caisse' => 'Caisse 01 · ' . $pharmacy->name,
            'user' => $s->user->name . ' · Shift A',
            'espece' => number_format($s->opening_amount, 0, ',', ' ') . ' GNF',
            'mobile' => '0 GNF',
            'ecart' => ($s->difference !== null ? ($s->difference >= 0 ? '+' : '') . number_format($s->difference, 0, ',', ' ') . ' GNF' : '0 GNF'),
            'ok' => ($s->difference === null || $s->difference == 0),
        ]);

        if ($sessions->isEmpty()) {
            $sessions = collect([[
                'caisse' => 'Caisse 01 · ' . $pharmacy->name,
                'user' => 'Aucune session',
                'espece' => '0 GNF',
                'mobile' => '0 GNF',
                'ecart' => '0 GNF',
                'ok' => true,
            ]]);
        }

        $todayCash = Payment::whereHas('sale', fn($q) => $q->where('pharmacy_id', $pharmacy->id))->whereDate('created_at', now()->toDateString())->sum('amount');

        return Inertia::render('Caisses', [
            'pharmacy' => $pharmacy,
            'sessions' => $sessions,
            'totals' => [
                'encaisse' => number_format($todayCash, 0, ',', ' ') . ' GNF',
                'depenses' => '1 850 000 GNF',
                'fond' => '1 500 000 GNF',
                'resultat' => '+3 240 000 GNF',
            ],
        ]);
    }

}
