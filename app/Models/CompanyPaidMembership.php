<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanyPaidMembership extends Model
{
    protected $table = 'company_paid_memberships';

    protected $fillable = [
        'company_id',
        'plan_generator_id',
        'pay_amount_ves',
        'pay_amount_usd',
        'total_amount',
        'reference_payment_usd',
        'reference_payment_ves',
        'payment_date',
        'prox_payment_date',
        'document_usd',
        'document_ves',
        'observations_payment',
        'status',
        'renewal_date',
        'payment_frequency',
        'bank_usd',
        'bank_ves',
        'payment_method',
        'payment_method_usd',
        'payment_method_ves',
        'type_roll',
        'tasa_bcv',
        'created_by',
        'aproved_by',
        'name_ti_usd',
        'date_payment_voucher',
        'invoice_number',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function planGenerator(): BelongsTo
    {
        return $this->belongsTo(PlanGenerator::class);
    }
}
