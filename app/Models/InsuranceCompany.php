<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InsuranceCompany extends Model
{
    protected $fillable = ['name','code','contact','phone','email','is_active'];
    protected $casts = ['is_active'=>'boolean'];
    public function claims(): HasMany { return $this->hasMany(InsuranceClaim::class); }
    public function rules(): HasMany { return $this->hasMany(InsuranceRule::class); }
}
