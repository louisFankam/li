<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $pharmacy_id
 * @property string|null $name
 * @property string $token_hash
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $revoked_at
 * @property-read User $user
 * @property-read Pharmacy|null $pharmacy
 */
class Device extends Model
{
    protected $fillable = ['user_id', 'pharmacy_id', 'name', 'token_hash', 'last_seen_at', 'revoked_at'];

    protected $casts = ['last_seen_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }
}
