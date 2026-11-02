<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sync_event_id
 * @property int|null $pharmacy_id
 * @property string $type
 * @property array<string, mixed>|null $details
 * @property string $status
 * @property int|null $resolved_by
 * @property Carbon|null $resolved_at
 * @property-read SyncEvent $syncEvent
 * @property-read Pharmacy|null $pharmacy
 */
class SyncConflict extends Model
{
    protected $fillable = ['sync_event_id', 'pharmacy_id', 'type', 'details', 'status', 'resolved_by', 'resolved_at'];

    protected $casts = ['details' => 'array', 'resolved_at' => 'datetime'];

    public function syncEvent(): BelongsTo
    {
        return $this->belongsTo(SyncEvent::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
