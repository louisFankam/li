<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $transfer_id
 * @property int $product_id
 * @property int $quantity_requested
 * @property int $quantity_shipped
 * @property int $quantity_received
 * @property-read Transfer $transfer
 * @property-read Product $product
 */
class TransferItem extends Model
{
    protected $fillable = ['transfer_id', 'product_id', 'quantity_requested', 'quantity_shipped', 'quantity_received'];

    protected $casts = ['quantity_requested' => 'integer', 'quantity_shipped' => 'integer', 'quantity_received' => 'integer'];

    public function transfer(): BelongsTo
    {
        return $this->belongsTo(Transfer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
