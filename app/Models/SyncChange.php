<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $entity_type
 * @property int $entity_id
 * @property int|null $pharmacy_id
 * @property string $operation
 * @property array<string, mixed> $payload
 * @property Carbon $changed_at
 */
class SyncChange extends Model
{
    protected $fillable = ['entity_type', 'entity_id', 'pharmacy_id', 'operation', 'payload', 'changed_at'];

    protected $casts = [
        'payload' => 'array',
        'changed_at' => 'datetime',
    ];
}
