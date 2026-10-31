<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\User;
use App\Services\PharmacyContext;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AuditController extends Controller
{
    public function index(Request $request, PharmacyContext $context)
    {
        $this->authorize('manage_audit');

        $accessible = $context->accessibleIds($request);

        $filters = [
            'action' => $request->string('action')->toString(),
            'user_id' => $request->integer('user_id') ?: null,
            'pharmacy_id' => $request->integer('pharmacy_id') ?: null,
            'from' => $request->input('from', now()->subDays(30)->toDateString()),
            'to' => $request->input('to', now()->toDateString()),
        ];

        $logs = AuditLog::with(['user', 'pharmacy'])
            ->where(function ($q) use ($accessible) {
                $q->whereIn('pharmacy_id', $accessible)->orWhereNull('pharmacy_id');
            })
            ->when($filters['action'] !== '', fn ($q) => $q->where('action', 'like', $filters['action'].'%'))
            ->when($filters['user_id'] !== null, fn ($q) => $q->where('user_id', $filters['user_id']))
            ->when($filters['pharmacy_id'] !== null, fn ($q) => $q->where('pharmacy_id', $filters['pharmacy_id']))
            ->whereDate('created_at', '>=', $filters['from'])
            ->whereDate('created_at', '<=', $filters['to'])
            ->latest()
            ->paginate(50)
            ->withQueryString()
            ->through(fn (AuditLog $l) => [
                'id' => $l->id,
                'action' => $l->action,
                'subject' => $l->subject_type ? class_basename($l->subject_type).' #'.$l->subject_id : '—',
                'user' => $l->user !== null ? $l->user->name : '—',
                'pharmacy' => $l->pharmacy !== null ? $l->pharmacy->name : '—',
                'ip' => $l->ip,
                'old_values' => $l->old_values,
                'new_values' => $l->new_values,
                'date' => $l->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Audit', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::orderBy('name')->get(['id', 'name']),
            'pharmacies' => Pharmacy::whereIn('id', $accessible)->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
