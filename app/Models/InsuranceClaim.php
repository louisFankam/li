<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InsuranceClaim extends Model
{
    protected $fillable = ['sale_id','insurance_company_id','total_amount','client_part','insurer_part','status','reference','notes'];
    protected $casts = ['total_amount'=>'integer','client_part'=>'integer','insurer_part'=>'integer'];
    public function sale(): BelongsTo { return $this->belongsTo(Sale::class); }
    public function insuranceCompany(): BelongsTo { return $this->belongsTo(InsuranceCompany::class); }
}
