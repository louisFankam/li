<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Transfer extends Model
{
    protected $fillable = ['reference','from_pharmacy_id','to_pharmacy_id','status','requested_by','approved_by','shipped_at','received_at','notes'];
    protected $casts = ['shipped_at'=>'datetime','received_at'=>'datetime'];
    public function fromPharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class,'from_pharmacy_id'); }
    public function toPharmacy(): BelongsTo { return $this->belongsTo(Pharmacy::class,'to_pharmacy_id'); }
    public function requester(): BelongsTo { return $this->belongsTo(User::class,'requested_by'); }
    public function approver(): BelongsTo { return $this->belongsTo(User::class,'approved_by'); }
    public function items(): HasMany { return $this->hasMany(TransferItem::class); }
}
