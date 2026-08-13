<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CreditReconciliationEntityType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditReconciliation extends Model
{
    /** @use HasFactory<\Database\Factories\CreditReconciliationFactory> */
    use HasFactory;

    protected $table = 'credit_reconciliations';

    protected $fillable = [
        'entity_type',
        'white_company_id',
        'agency_id',
        'agent_id',
        'paid_membership_id',
        'paid_membership_corporate_id',
        'collection_id',
        'affiliation_kind',
        'affiliation_id',
        'affiliation_corporate_id',
        'affiliation_code',
        'affiliation_information',
        'affiliates_count',
        'annual_amount',
        'total_to_pay',
        'payment_frequency',
        'collection_invoice_number',
        'plan_id',
        'plan_type',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'entity_type' => CreditReconciliationEntityType::class,
            'affiliates_count' => 'integer',
            'annual_amount' => 'decimal:2',
            'total_to_pay' => 'decimal:2',
        ];
    }

    public function whiteCompany(): BelongsTo
    {
        return $this->belongsTo(WhiteCompany::class);
    }

    public function agency(): BelongsTo
    {
        return $this->belongsTo(Agency::class);
    }

    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    public function paidMembership(): BelongsTo
    {
        return $this->belongsTo(PaidMembership::class);
    }

    public function paidMembershipCorporate(): BelongsTo
    {
        return $this->belongsTo(PaidMembershipCorporate::class);
    }

    public function collection(): BelongsTo
    {
        return $this->belongsTo(Collection::class);
    }

    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForWhiteCompanies(Builder $query): Builder
    {
        return $query->where('entity_type', CreditReconciliationEntityType::WhiteCompany);
    }
}
