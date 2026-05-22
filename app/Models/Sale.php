<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Sale extends Model
{
    protected $table = 'sales';

    protected $fillable = [
        'plan_id',
        'coverage_id',
        'agent_id',
        'date_activation',
        'code_agency',
        'owner_code',
        'invoice_number',
        'affiliation_id',
        'affiliation_code',
        'affiliate_full_name',
        'affiliate_contact',
        'affiliate_ci_rif',
        'affiliate_phone',
        'affiliate_email',
        'service',
        'persons',
        'created_by',
        'total_amount',
        'total_amount_ves',
        'type',
        'payment_method',
        'payment_frequency',
        'bank',
        'status_payment_commission',
        'pay_amount_usd',
        'pay_amount_ves',
        'type_roll',
        'bank_usd',
        'bank_ves',
        'payment_date',
        'observations',
        'reference_payment',
        'date_payment_voucher',
        'invoice_generated',
        'is_payment_link',
    ];

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage(): BelongsTo
    {
        return $this->belongsTo(Coverage::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function agencyMasterName(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'owner_code', 'code');
    }

    public function paidMembershipIndividual(): HasOne
    {
        return $this->hasOne(PaidMembership::class, 'invoice_number', 'invoice_number');
    }

    public function paidMembershipCorporate(): HasOne
    {
        return $this->hasOne(PaidMembershipCorporate::class, 'invoice_number', 'invoice_number');
    }

    public function commission(): HasOne
    {
        return $this->hasOne(Commission::class, 'code', 'invoice_number');
    }

    public function resolvePaidReceipt(): PaidMembership|PaidMembershipCorporate|null
    {
        if ($this->type === 'AFILIACION CORPORATIVA') {
            return $this->paidMembershipCorporate;
        }

        return $this->paidMembershipIndividual;
    }

    public function paidReceiptTableName(): string
    {
        return $this->type === 'AFILIACION CORPORATIVA'
            ? 'paid_membership_corporates'
            : 'paid_memberships';
    }
}
