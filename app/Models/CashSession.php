<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pharmacy_id
 * @property int $user_id
 * @property int $opening_amount
 * @property int|null $closing_amount
 * @property int|null $expected_amount
 * @property int|null $difference
 * @property string $status
 * @property Carbon|null $opened_at
 * @property Carbon|null $closed_at
 * @property string|null $notes
 * @property-read Pharmacy $pharmacy
 * @property-read User $user
 * @property-read Collection<int, CashMovement> $movements
 */
class CashSession extends Model
{
    protected $fillable = ['pharmacy_id', 'user_id', 'opening_amount', 'closing_amount', 'expected_amount', 'difference', 'status', 'opened_at', 'closed_at', 'notes'];

    protected $casts = ['opening_amount' => 'integer', 'closing_amount' => 'integer', 'expected_amount' => 'integer', 'difference' => 'integer', 'opened_at' => 'datetime', 'closed_at' => 'datetime'];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }
}
