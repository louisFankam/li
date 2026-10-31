<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $product_id
 * @property int $quantity
 * @property string|null $unit
 * @property int $unit_price
 * @property int $discount_amount
 * @property int $total
 * @property-read Sale $sale
 * @property-read Product $product
 */
class SaleItem extends Model
{
    protected $fillable = ['sale_id', 'product_id', 'quantity', 'unit', 'unit_price', 'discount_amount', 'total'];

    protected $casts = ['quantity' => 'integer', 'unit_price' => 'integer', 'discount_amount' => 'integer', 'total' => 'integer'];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
