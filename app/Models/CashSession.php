<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashSession extends Model
{
    protected $fillable = ['pharmacy_id','user_id','opening_amount','closing_amount','expected_amount','difference','status','opened_at','closed_at','notes'];
    protected $casts = ['opening_amount'=>'integer','closing_amount'=>'integer','expected_amount'=>'integer','difference'=>'integer','opened_at'=>'datetime','closed_at'=>'datetime'];
    public function pharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function movements(): HasMany { return $this->hasMany(CashMovement::class); }
}
