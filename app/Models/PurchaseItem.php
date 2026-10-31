<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $purchase_id
 * @property int $product_id
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $unit_price
 * @property-read Purchase $purchase
 * @property-read Product $product
 */
class PurchaseItem extends Model
{
    protected $fillable = ['purchase_id', 'product_id', 'quantity_ordered', 'quantity_received', 'unit_price'];

    protected $casts = ['quantity_ordered' => 'integer', 'quantity_received' => 'integer', 'unit_price' => 'integer'];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
