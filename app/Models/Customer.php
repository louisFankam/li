<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $phone
 * @property string|null $email
 * @property int|null $insurance_company_id
 * @property int $version
 * @property-read InsuranceCompany|null $insuranceCompany
 */
class Customer extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'phone', 'email', 'insurance_company_id', 'version'];

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
