<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference
 * @property int $from_pharmacy_id
 * @property int $to_pharmacy_id
 * @property string $status
 * @property int|null $requested_by
 * @property int|null $approved_by
 * @property Carbon|null $shipped_at
 * @property Carbon|null $received_at
 * @property string|null $notes
 * @property Carbon $created_at
 * @property-read Pharmacy $fromPharmacy
 * @property-read Pharmacy $toPharmacy
 * @property-read User|null $requester
 * @property-read User|null $approver
 * @property-read Collection<int, TransferItem> $items
 */
class Transfer extends Model
{
    protected $fillable = ['reference', 'from_pharmacy_id', 'to_pharmacy_id', 'status', 'requested_by', 'approved_by', 'shipped_at', 'received_at', 'notes'];

    protected $casts = ['shipped_at' => 'datetime', 'received_at' => 'datetime'];

    public function fromPharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'from_pharmacy_id');
    }

    public function toPharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class, 'to_pharmacy_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransferItem::class);
    }
}
