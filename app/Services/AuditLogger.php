<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(string $action, ?string $subjectType = null, ?int $subjectId = null, array $old = [], array $new = [], ?Request $request = null): void
    {
        $request ??= request();
        $user = $request->user();
        $pharmacyId = $request->hasSession()
            ? $request->session()->get('active_pharmacy_id')
            : null;

        AuditLog::create([
            'user_id' => $user?->id,
            'pharmacy_id' => $pharmacyId,
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip' => $request->ip(),
        ]);
    }
}
