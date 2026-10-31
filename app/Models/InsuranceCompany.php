<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string|null $code
 * @property string|null $contact
 * @property string|null $phone
 * @property string|null $email
 * @property bool $is_active
 * @property int $version
 */
class InsuranceCompany extends Model
{
    protected $fillable = ['name', 'code', 'contact', 'phone', 'email', 'is_active', 'version'];

    protected $casts = ['is_active' => 'boolean'];

    public function claims(): HasMany
    {
        return $this->hasMany(InsuranceClaim::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(InsuranceRule::class);
    }
}
