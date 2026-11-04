<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CapitationPaymentFrequency;
use App\Support\Operations\ProviderDeletion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Plan de capitado de un proveedor de la red Outsourcing: costo por afiliado en USD,
 * frecuencia de pago, beneficios y respaldos de la negociación.
 *
 * @property CapitationPaymentFrequency $payment_frequency
 * @property list<string>|null $documents
 * @property array<string, string>|null $document_names
 */
class CapitationPlan extends Model
{
    use SoftDeletes;

    public const STATUS_ACTIVE = 'ACTIVO';

    public const STATUS_INACTIVE = 'INACTIVO';

    public const PROVIDER_SUPPLIER = ProviderDeletion::TYPE_SUPPLIER;

    public const PROVIDER_DOCTOR_NURSE = ProviderDeletion::TYPE_DOCTOR_NURSE;

    protected $fillable = [
        'provider_type',
        'provider_id',
        'name',
        'cost_per_affiliate_usd',
        'payment_frequency',
        'valid_from',
        'valid_until',
        'status',
        'negotiation_type',
        'documents',
        'document_names',
        'created_by_user_id',
        'created_by_name',
        'updated_by_user_id',
        'updated_by_name',
    ];

    protected function casts(): array
    {
        return [
            'cost_per_affiliate_usd' => 'decimal:2',
            'payment_frequency' => CapitationPaymentFrequency::class,
            'valid_from' => 'date',
            'valid_until' => 'date',
            'documents' => 'array',
            'document_names' => 'array',
        ];
    }

    /**
     * @return HasMany<CapitationPlanBenefit, $this>
     */
    public function benefits(): HasMany
    {
        return $this->hasMany(CapitationPlanBenefit::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'provider_id')->withTrashed();
    }

    /**
     * @return BelongsTo<DoctorNurse, $this>
     */
    public function doctorNurse(): BelongsTo
    {
        return $this->belongsTo(DoctorNurse::class, 'provider_id')->withTrashed();
    }

    public function provider(): ?Model
    {
        return $this->provider_type === self::PROVIDER_SUPPLIER ? $this->supplier : $this->doctorNurse;
    }

    public function providerKey(): string
    {
        return $this->provider_type.':'.$this->provider_id;
    }

    public function monthlyEquivalent(): float
    {
        return $this->payment_frequency->monthlyEquivalent((float) $this->cost_per_affiliate_usd);
    }

    /**
     * @param  Builder<CapitationPlan>  $query
     * @return Builder<CapitationPlan>
     */
    public function scopeForProvider(Builder $query, string $type, int $id): Builder
    {
        return $query->where('provider_type', $type)->where('provider_id', $id);
    }
}
