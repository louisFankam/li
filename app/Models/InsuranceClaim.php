<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $sale_id
 * @property int $insurance_company_id
 * @property int $total_amount
 * @property int $client_part
 * @property int $insurer_part
 * @property string $status
 * @property string|null $reference
 * @property string|null $notes
 * @property-read Sale $sale
 * @property-read InsuranceCompany $insuranceCompany
 */
class InsuranceClaim extends Model
{
    protected $fillable = ['sale_id', 'insurance_company_id', 'total_amount', 'client_part', 'insurer_part', 'reimbursed_amount', 'reimbursed_at', 'status', 'reference', 'notes'];

    protected $casts = [
        'total_amount' => 'integer',
        'client_part' => 'integer',
        'insurer_part' => 'integer',
        'reimbursed_amount' => 'integer',
        'reimbursed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function insuranceCompany(): BelongsTo
    {
        return $this->belongsTo(InsuranceCompany::class);
    }
}
