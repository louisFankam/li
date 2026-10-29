<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    protected $fillable = ['sale_number','pharmacy_id','user_id','customer_id','insurance_company_id','subtotal','discount_amount','discount_percent','total','client_part','insurer_part','status','notes'];
    protected $casts = ['subtotal'=>'integer','discount_amount'=>'integer','total'=>'integer','client_part'=>'integer','insurer_part'=>'integer'];
    public function pharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function insuranceCompany(): BelongsTo { return $this->belongsTo(InsuranceCompany::class); }
    public function items(): HasMany { return $this->hasMany(SaleItem::class); }
    public function payments(): HasMany { return $this->hasMany(Payment::class); }
    public function claim(): \Illuminate\Database\Eloquent\Relations\HasOne { return $this->hasOne(InsuranceClaim::class); }
}
