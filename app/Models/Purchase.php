<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $pharmacy_id
 * @property string $status
 * @property string|null $invoice_number
 * @property string $payment_status
 * @property int $total
 * @property Carbon|null $ordered_at
 * @property Carbon|null $received_at
 * @property-read Supplier $supplier
 * @property-read Pharmacy $pharmacy
 * @property-read Collection<int, PurchaseItem> $items
 */
class Purchase extends Model
{
    protected $fillable = ['supplier_id', 'pharmacy_id', 'status', 'invoice_number', 'payment_status', 'total', 'ordered_at', 'received_at'];

    protected $casts = ['total' => 'integer', 'ordered_at' => 'datetime', 'received_at' => 'datetime'];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function pharmacy(): BelongsTo
    {
        return $this->belongsTo(Pharmacy::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
