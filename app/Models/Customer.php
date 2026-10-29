<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends Model
{
    protected $fillable = ['name','phone','email','insurance_company_id'];
    public function insuranceCompany(): BelongsTo { return $this->belongsTo(InsuranceCompany::class); }
}
