<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $fillable = ['code','barcode','name','generic_name','dosage','form','laboratory','category_id','base_unit','sale_unit','conversion_factor','purchase_price','sale_price','alert_threshold','is_active','notes'];
    protected $casts = ['is_active'=>'boolean','purchase_price'=>'integer','sale_price'=>'integer'];

    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
    public function stockLevels(): HasMany { return $this->hasMany(StockLevel::class); }
    public function saleItems(): HasMany { return $this->hasMany(SaleItem::class); }
}
