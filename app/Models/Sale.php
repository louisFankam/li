<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $sale_number
 * @property string|null $client_request_id
 * @property int $pharmacy_id
 * @property int $user_id
 * @property int|null $customer_id
 * @property int|null $insurance_company_id
 * @property int $subtotal
 * @property int $discount_amount
 * @property int|null $discount_percent
 * @property int $total
 * @property int $client_part
 * @property int $insurer_part
 * @property string $status
 * @property string|null $notes
 * @property int|null $cancelled_by
 * @property Carbon|null $cancelled_at
 * @property string|null $cancel_reason
 * @property Carbon $created_at
 * @property-read Pharmacy $pharmacy
 * @property-read User $user
 * @property-read Customer|null $customer
 * @property-read InsuranceCompany|null $insuranceCompany
 * @property-read Collection<int, SaleItem> $items
 * @property-read Collection<int, Payment> $payments
 * @property-read InsuranceClaim|null $claim
 */
class Sale extends Model
{
    protected $fillable = [
        'sale_number', 'client_request_id', 'pharmacy_id', 'user_id', 'customer_id',
        'insurance_company_id', 'subtotal', 'discount_amount', 'discount_percent', 'discount_reason',
        'discount_approved_by', 'discount_approved_at', 'total',
        'client_part', 'insurer_part', 'status', 'notes', 'cancelled_by', 'cancelled_at', 'cancel_reason',
    ];

    protected $casts = [
        'subtotal' => 'integer', 'discount_amount' => 'integer', 'total' => 'integer',
        'client_part' => 'integer', 'insurer_part' => 'integer', 'cancelled_at' => 'datetime',
        'discount_approved_at' => 'datetime',
    ];

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function claim(): HasOne
    {
        return $this->hasOne(InsuranceClaim::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }
}
