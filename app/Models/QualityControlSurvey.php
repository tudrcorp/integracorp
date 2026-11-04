<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Encuesta de Quality Control de un paciente (ver `QualityControlAffiliateType`).
 *
 * @property QualityControlAffiliateType $affiliate_type
 * @property QualityControlRating|null $rating_channels
 * @property QualityControlRating|null $rating_response_time
 * @property QualityControlRating|null $rating_medical_service
 * @property QualityControlRating|null $rating_overall
 * @property list<string>|null $services
 */
class QualityControlSurvey extends Model
{
    use SoftDeletes;

    public const SOURCE_FORM = 'FORMULARIO';

    public const SOURCE_IMPORT = 'IMPORTACION';

    /**
     * Las cuatro preguntas de calificación, en el orden del formulario.
     *
     * @var array<string, string>
     */
    public const RATING_FIELDS = [
        'rating_channels' => 'Canales de atención',
        'rating_response_time' => 'Tiempo de respuesta',
        'rating_medical_service' => 'Servicio médico',
        'rating_overall' => '¿Quedó satisfecho con el servicio?',
    ];

    protected $fillable = [
        'affiliate_type',
        'affiliate_id',
        'responded_at',
        'service_date',
        'patient_document',
        'patient_document_key',
        'patient_name',
        'contact_phone',
        'rating_channels',
        'rating_response_time',
        'rating_medical_service',
        'rating_overall',
        'observations',
        'services',
        'source',
        'import_batch',
        'dedupe_hash',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'affiliate_type' => QualityControlAffiliateType::class,
            'responded_at' => 'datetime',
            'service_date' => 'date',
            'rating_channels' => QualityControlRating::class,
            'rating_response_time' => QualityControlRating::class,
            'rating_medical_service' => QualityControlRating::class,
            'rating_overall' => QualityControlRating::class,
            'services' => 'array',
        ];
    }

    /**
     * @param  Builder<QualityControlSurvey>  $query
     * @return Builder<QualityControlSurvey>
     */
    public function scopeOfType(Builder $query, QualityControlAffiliateType $type): Builder
    {
        return $query->where('affiliate_type', $type->value);
    }

    /**
     * @return BelongsTo<Affiliate, $this>
     */
    public function individualAffiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class, 'affiliate_id');
    }

    /**
     * @return BelongsTo<AffiliateCorporate, $this>
     */
    public function corporateAffiliate(): BelongsTo
    {
        return $this->belongsTo(AffiliateCorporate::class, 'affiliate_id');
    }

    /**
     * Notas de seguimiento, de la más reciente a la más antigua.
     *
     * @return HasMany<QualityControlSurveyNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(QualityControlSurveyNote::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
