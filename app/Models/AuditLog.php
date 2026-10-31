<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $pharmacy_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $ip
 * @property-read User|null $user
 * @property-read Pharmacy|null $pharmacy
 */
class AuditLog extends Model
{
    protected $fillable = ['user_id', 'pharmacy_id', 'action', 'subject_type', 'subject_id', 'old_values', 'new_values', 'ip'];

    protected $casts = ['old_values' => 'array', 'new_values' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Pharmacy, $this> */
    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
