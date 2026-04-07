<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnnualCollection extends Model
{
    protected $table = 'annual_collections';

    protected $fillable = [
        'sale_id',
        'include_date',
        'owner_code',
        'code_agency',
        'agent_id',
        'collection_invoice_number',
        'quote_number',
        'affiliation_code',
        'affiliate_full_name',
        'affiliate_contact',
        'affiliate_ci_rif',
        'affiliate_phone',
        'affiliate_email',
        'affiliate_status',
        'plan_id',
        'coverage_id',
        'service',
        'persons',
        'type',
        'reference',
        'payment_method',
        'payment_frequency',
        'next_payment_date',
        'total_amount',
        'expiration_date',
        'status',
        'days',
        'created_by',
        'pay_amount_usd',
        'pay_amount_ves',
        'bank_usd',
        'bank_ves',
        'filter_next_payment_date',
        'month_1',
        'month_2',
        'month_3',
        'month_4',
        'month_5',
        'month_6',
        'month_7',
        'month_8',
        'month_9',
        'month_10',
        'month_11',
        'month_12',
        'remaining_days',
    ];

    protected function casts(): array
    {
        return [
            'total_amount' => 'decimal:2',
            'pay_amount_usd' => 'decimal:2',
            'pay_amount_ves' => 'decimal:2',
            'filter_next_payment_date' => 'date',
            'remaining_days' => 'integer',
            'month_1' => 'boolean',
            'month_2' => 'boolean',
            'month_3' => 'boolean',
            'month_4' => 'boolean',
            'month_5' => 'boolean',
            'month_6' => 'boolean',
            'month_7' => 'boolean',
            'month_8' => 'boolean',
            'month_9' => 'boolean',
            'month_10' => 'boolean',
            'month_11' => 'boolean',
            'month_12' => 'boolean',
        ];
    }

    public function sale(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function agent(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function plan(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    public function collections(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Collection::class, 'sale_id', 'sale_id');
    }
}
