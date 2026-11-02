<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $insurance_company_id
 * @property string $type
 * @property int $value
 * @property int|null $category_id
 * @property int|null $product_id
 * @property int $version
 */
class InsuranceRule extends Model
{
    use HasFactory;

    protected $fillable = ['insurance_company_id', 'type', 'value', 'category_id', 'product_id', 'version'];

    protected $casts = ['value' => 'integer'];

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
