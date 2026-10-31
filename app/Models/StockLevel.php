<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $pharmacy_id
 * @property int $product_id
 * @property int $quantity
 * @property int $version
 * @property-read Pharmacy $pharmacy
 * @property-read Product $product
 */
class StockLevel extends Model
{
    protected $fillable = ['pharmacy_id', 'product_id', 'quantity', 'version'];

    protected $casts = ['quantity' => 'integer'];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
