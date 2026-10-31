<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cash_session_id
 * @property string $type
 * @property int $amount
 * @property string|null $reason
 * @property int|null $user_id
 * @property-read CashSession $cashSession
 * @property-read User|null $user
 */
class CashMovement extends Model
{
    protected $fillable = ['cash_session_id', 'type', 'amount', 'reason', 'user_id'];

    protected $casts = ['amount' => 'integer'];

    public function cashSession(): BelongsTo
    {
        return $this->belongsTo(CashSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
