<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Pharmacy;
use App\Models\User;
use Illuminate\Http\Request;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    public function log(
        string $action,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $old = [],
        array $new = [],
        ?Request $request = null,
        ?User $actor = null,
        Pharmacy|int|null $pharmacy = null,
    ): void {
        $request ??= request();
        $user = $actor ?? $request->user();

        $pharmacyId = $pharmacy instanceof Pharmacy
            ? $pharmacy->id
            : $pharmacy;

        if ($pharmacyId === null && $request->hasSession()) {
            $pharmacyId = $request->session()->get('active_pharmacy_id');
        }

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
