<?php

namespace App\Models;

use App\Support\Collections\CollectionDueDate;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'billing_adjustment_id',
    ];

    /**
     * Avisos de cobro por ajuste de población: no son cuotas del calendario, así que los
     * módulos que recorren el calendario (vigencia, frecuencia, upgrades, detector) los dejan
     * fuera con este scope.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Collection>  $query
     * @return \Illuminate\Database\Eloquent\Builder<Collection>
     */
    public function scopeCalendarInstallments($query)
    {
        return self::hasBillingAdjustmentColumn($query->getModel()->getConnectionName())
            ? $query->whereNull($query->qualifyColumn('billing_adjustment_id'))
            : $query;
    }

    /**
     * Si el código llega antes que la migración, el scope no filtra en vez de romper consultas.
     * Se recuerda por conexión: cada conexión puede tener su propia tabla.
     */
    public static function hasBillingAdjustmentColumn(?string $connection = null): bool
    {
        static $exists = [];
        $connection ??= (string) config('database.default');

        try {
            return $exists[$connection] ??= \Illuminate\Support\Facades\Schema::connection($connection)->hasColumn('collections', 'billing_adjustment_id');
        } catch (\Throwable) {
            return false;
        }
    }

    public function isBillingAdjustment(): bool
    {
        return $this->billing_adjustment_id !== null;
    }

    /**
     * @return BelongsTo<CorporateBillingAdjustment, $this>
     */
    public function billingAdjustment(): BelongsTo
    {
        return $this->belongsTo(CorporateBillingAdjustment::class, 'billing_adjustment_id');
    }

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
     * Bitácora de cobranza de la afiliación, compartida por todas sus cuotas: la
     * fila de «Cobranza Por Mes» cambia de cuota al pagarse y las notas siguen.
     */
    public function receivableObservations(): HasMany
    {
        return $this->hasMany(CollectionObservation::class, 'affiliation_code', 'affiliation_code');
    }

    /**
     * Ajustes manuales hechos desde «Ajustar cuota», del más reciente al más antiguo.
     */
    public function adjustments(): HasMany
    {
        return $this->hasMany(CollectionAdjustment::class)->latest('id');
    }

    /**
     * Decisiones sobre la cuota cuando su afiliación fue dada de baja.
     */
    public function dropoutReviews(): HasMany
    {
        return $this->hasMany(CollectionDropoutReview::class)->latest('id');
    }

    /**
     * Decisión vigente (no revertida) sobre la cuota de una afiliación dada de baja.
     */
    public function activeDropoutReview(): HasOne
    {
        return $this->hasOne(CollectionDropoutReview::class)->whereNull('reverted_at')->latestOfMany();
    }
}
