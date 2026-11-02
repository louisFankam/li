<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string|null $barcode
 * @property string $name
 * @property string|null $generic_name
 * @property string|null $dosage
 * @property string|null $form
 * @property string|null $laboratory
 * @property int|null $category_id
 * @property string $base_unit
 * @property string|null $sale_unit
 * @property int $conversion_factor
 * @property int $purchase_price
 * @property int $sale_price
 * @property int $alert_threshold
 * @property bool $is_active
 * @property string|null $notes
 * @property int $version
 * @property-read Category|null $category
 */
class Product extends Model
{
    use HasFactory;

    protected $fillable = ['code', 'barcode', 'name', 'generic_name', 'dosage', 'form', 'laboratory', 'category_id', 'base_unit', 'sale_unit', 'conversion_factor', 'purchase_price', 'sale_price', 'alert_threshold', 'is_active', 'notes', 'version'];

    protected $casts = ['is_active' => 'boolean', 'purchase_price' => 'integer', 'sale_price' => 'integer', 'alert_threshold' => 'integer', 'conversion_factor' => 'integer'];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stockLevels(): HasMany
    {
        return $this->hasMany(StockLevel::class);
    }

    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }
}
