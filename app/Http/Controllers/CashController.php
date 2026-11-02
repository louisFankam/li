<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\UsesActivePharmacy;
use App\Http\Requests\CashMovementRequest;
use App\Http\Requests\CloseCashRequest;
use App\Http\Requests\OpenCashRequest;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\Models\Pharmacy;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CashController extends Controller
{
    use UsesActivePharmacy;

    /** Moyens de paiement considérés comme de la caisse physique. */
    public const CASH_METHODS = ['especes', 'espèces', 'espece', 'espèce', 'cash'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', CashSession::class);
        $pharmacy = $this->pharmacy($request);

        $sessions = CashSession::with('user')
            ->where('pharmacy_id', $pharmacy->id)
            ->latest()
            ->take(20)
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'caisse' => 'Caisse 01 · '.$pharmacy->name,
                'user' => $s->user->name,
                'status' => $s->status,
                'opening' => number_format($s->opening_amount, 0, ',', ' ').' GNF',
                'closing' => $s->closing_amount !== null ? number_format($s->closing_amount, 0, ',', ' ').' GNF' : '—',
                'expected' => $s->expected_amount !== null ? number_format($s->expected_amount, 0, ',', ' ').' GNF' : '—',
                'difference' => $s->difference !== null ? ($s->difference >= 0 ? '+' : '').number_format($s->difference, 0, ',', ' ').' GNF' : '—',
                'difference_raw' => $s->difference,
                'ok' => $s->difference === null || $s->difference === 0,
                'opened_at' => $s->opened_at?->format('d/m H:i'),
                'closed_at' => $s->closed_at?->format('d/m H:i'),
            ]);

        $openSession = CashSession::with('movements.user')
            ->where('pharmacy_id', $pharmacy->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        $todayCash = Payment::whereHas('sale', fn ($q) => $q->where('pharmacy_id', $pharmacy->id)->where('status', 'completed'))
            ->whereIn('method', self::CASH_METHODS)
            ->whereDate('created_at', now()->toDateString())
            ->sum('amount');

        $movements = $openSession
            ? $openSession->movements->sortByDesc('created_at')->values()->map(fn ($m) => [
                'id' => $m->id,
                'type' => $m->type,
                'amount' => number_format($m->amount, 0, ',', ' ').' GNF',
                'amount_raw' => $m->amount,
                'reason' => $m->reason,
                'user' => $m->user?->name,
                'at' => $m->created_at->format('d/m H:i'),
            ])
            : collect();

        $expected = $openSession ? $this->expectedAmount($openSession) : null;

        return Inertia::render('Caisses', [
            'pharmacy' => $pharmacy,
            'sessions' => $sessions,
            'totals' => [
                'encaisse' => number_format($todayCash, 0, ',', ' ').' GNF',
                'fond' => $openSession ? number_format($openSession->opening_amount, 0, ',', ' ').' GNF' : '—',
                'attendu' => $expected !== null ? number_format($expected, 0, ',', ' ').' GNF' : '—',
            ],
            'openSession' => $openSession?->id,
            'openSessionInfo' => $openSession ? [
                'id' => $openSession->id,
                'opening' => $openSession->opening_amount,
                'opened_at' => $openSession->opened_at?->format('d/m H:i'),
                'expected' => $expected,
                'movements' => $movements,
            ] : null,
        ]);
    }

    public function open(OpenCashRequest $request)
    {
        $pharmacy = $this->pharmacy($request);

        $session = DB::transaction(function () use ($request, $pharmacy) {
            // Verrouiller la pharmacie sérialise aussi le cas où aucune
            // session n'existe encore : un verrou sur une ligne absente ne
            // protège pas deux ouvertures concurrentes.
            Pharmacy::whereKey($pharmacy->id)->lockForUpdate()->firstOrFail();

            $alreadyOpen = CashSession::where('pharmacy_id', $pharmacy->id)
                ->where('status', 'open')
                ->exists();

            if ($alreadyOpen) {
                throw ValidationException::withMessages([
                    'opening_amount' => 'Une session de caisse est déjà ouverte pour cette pharmacie.',
                ]);
            }

            return CashSession::create([
                'pharmacy_id' => $pharmacy->id,
                'user_id' => $request->user()->id,
                'opening_amount' => (int) $request->validated('opening_amount'),
                'status' => 'open',
                'opened_at' => now(),
            ]);
        });

        app(AuditLogger::class)->log('cash.opened', CashSession::class, $session->id, [], [
            'pharmacy_id' => $pharmacy->id,
            'opening_amount' => $session->opening_amount,
        ]);

        return redirect()->back()->with('success', 'Caisse ouverte');
    }

    public function close(CloseCashRequest $request, CashSession $cashSession)
    {
        $this->authorize('close', $cashSession);
        $data = $request->validated();

        DB::transaction(function () use ($cashSession, $data) {
            $session = CashSession::whereKey($cashSession->id)->lockForUpdate()->firstOrFail();

            if ($session->status !== 'open') {
                throw ValidationException::withMessages([
                    'closing_amount' => 'Cette session est déjà clôturée.',
                ]);
            }

            $expected = $this->expectedAmount($session);
            $closing = (int) $data['closing_amount'];

            $session->update([
                'closing_amount' => $closing,
                'expected_amount' => $expected,
                'difference' => $closing - $expected,
                'status' => 'closed',
                'closed_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            app(AuditLogger::class)->log('cash.closed', CashSession::class, $session->id, ['status' => 'open'], [
                'status' => 'closed',
                'expected' => $expected,
                'closing' => $closing,
                'difference' => $closing - $expected,
                'notes' => $data['notes'] ?? null,
            ]);
        });

        return redirect()->back()->with('success', 'Caisse fermée');
    }

    public function movement(CashMovementRequest $request, CashSession $cashSession)
    {
        $this->authorize('movement', $cashSession);

        $data = $request->validated();

        $movement = DB::transaction(function () use ($cashSession, $data, $request) {
            $session = CashSession::whereKey($cashSession->id)->lockForUpdate()->firstOrFail();

            if ($session->status !== 'open') {
                throw ValidationException::withMessages([
                    'amount' => 'Impossible d’enregistrer un mouvement sur une caisse fermée.',
                ]);
            }

            return CashMovement::create([
                'cash_session_id' => $session->id,
                'type' => $data['type'],
                'amount' => (int) $data['amount'],
                'reason' => $data['reason'] ?? null,
                'user_id' => $request->user()->id,
            ]);
        });

        app(AuditLogger::class)->log('cash.movement', CashMovement::class, $movement->id, [], [
            'session_id' => $movement->cash_session_id,
            'type' => $data['type'],
            'amount' => $movement->amount,
        ]);

        return redirect()->back()->with('success', 'Mouvement enregistré');
    }

    /**
     * Montant théorique en caisse : fond + espèces encaissées + entrées − retraits.
     */
    public function expectedAmount(CashSession $session): int
    {
        $cashPayments = Payment::whereHas('sale', function ($q) use ($session) {
            $q->where('pharmacy_id', $session->pharmacy_id)->where('status', 'completed');
        })
            ->whereIn('method', self::CASH_METHODS)
            ->whereBetween('created_at', [$session->opened_at ?? $session->created_at, $session->closed_at ?? now()])
            ->sum('amount');

        $movements = CashMovement::where('cash_session_id', $session->id)->get();
        $movementNet = $movements->where('type', 'entree')->sum('amount')
            - $movements->where('type', 'retrait')->sum('amount');

        return (int) ($session->opening_amount + $cashPayments + $movementNet);
    }
}
