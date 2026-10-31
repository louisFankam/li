<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $pharmacy_id
 * @property int $product_id
 * @property string $type
 * @property int $quantity
 * @property string|null $reference_type
 * @property int|null $reference_id
 * @property string|null $reason
 * @property int|null $user_id
 * @property Carbon $created_at
 * @property-read Pharmacy $pharmacy
 * @property-read Product $product
 * @property-read User|null $user
 */
class StockMovement extends Model
{
    protected $fillable = ['pharmacy_id', 'product_id', 'type', 'quantity', 'reference_type', 'reference_id', 'reason', 'user_id'];

    protected $casts = ['quantity' => 'integer'];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
