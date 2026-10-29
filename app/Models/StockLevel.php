<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockLevel extends Model
{
    protected $fillable = ['pharmacy_id','product_id','quantity'];
    public function pharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
}
