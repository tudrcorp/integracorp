<?php

namespace App\Models;

use App\Support\Collections\CollectionDueDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Collection extends Model
{
    protected $table = 'collections';

    protected $fillable = [
        'sale_id',
        'include_date',
        'owner_code',
        'code_agency',
        'agent_id',
        'coverage_id',
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
        'bank',
        'filter_next_payment_date',

    ];

    /**
     * La fecha oficial de la cuota es `next_payment_date`; `filter_next_payment_date`
     * se deriva de ella al guardar, así que nunca quedan distintas.
     */
    protected static function booted(): void
    {
        static::saving(function (Collection $collection): void {
            CollectionDueDate::syncColumns($collection);
        });
    }

    public function affiliation()
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    public function coverage()
    {
        return $this->belongsTo(Coverage::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function detail_individual_quote()
    {
        return $this->belongsTo(DetailIndividualQuote::class);
    }

    public function paid_memberships()
    {
        return $this->hasMany(PaidMembership::class);
    }

    public function individual_quote()
    {
        return $this->belongsTo(IndividualQuote::class);
    }

    public function agency()
    {
        return $this->belongsTo(Agency::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * La cuota guarda el código de la afiliación, no su id: `affiliation()` apunta a
     * una columna `affiliation_id` que esta tabla no tiene.
     */
    public function affiliationByCode(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class, 'affiliation_code', 'code');
    }

    public function affiliationCorporateByCode(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class, 'affiliation_code', 'code');
    }

    public function agencyByCode(): BelongsTo
    {
        return $this->belongsTo(Agency::class, 'code_agency', 'code');
    }

    /**
     * Ajustes manuales hechos desde «Ajustar cuota», del más reciente al más antiguo.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CollectionAdjustment::class)->latest('id');
    }
}
