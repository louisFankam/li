<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property string $method
 * @property int $amount
 * @property string|null $reference
 */
class Payment extends Model
{
    protected $fillable = ['sale_id', 'method', 'amount', 'reference'];

    protected $casts = ['amount' => 'integer'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }
}
