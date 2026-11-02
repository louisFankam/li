<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $client_event_id
 * @property int|null $pharmacy_id
 * @property int|null $device_id
 * @property int|null $user_id
 * @property string $type
 * @property array<string, mixed> $payload
 * @property string|null $payload_hash
 * @property int $schema_version
 * @property Carbon|null $occurred_at
 * @property Carbon|null $received_at
 * @property string $status
 * @property string|null $error
 * @property int $attempts
 * @property Carbon|null $processed_at
 * @property-read Pharmacy|null $pharmacy
 * @property-read Device|null $device
 * @property-read User|null $user
 */
class SyncEvent extends Model
{
    protected $fillable = ['client_event_id', 'pharmacy_id', 'device_id', 'user_id', 'type', 'payload', 'payload_hash', 'schema_version', 'occurred_at', 'received_at', 'status', 'error', 'attempts', 'processed_at'];

    protected $casts = [
        'payload' => 'array',
        'schema_version' => 'integer',
        'occurred_at' => 'datetime',
        'received_at' => 'datetime',
        'processed_at' => 'datetime',
        'attempts' => 'integer',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
