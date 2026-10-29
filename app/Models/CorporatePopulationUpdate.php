<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Una carga del archivo de población de una afiliación corporativa.
 *
 * Ciclo: ANALYZING → ANALYZED → APPLYING → APPLIED → (ROLLING_BACK → ROLLED_BACK).
 * Puede terminar en FAILED (no se escribió nada) o DISCARDED (el analista la
 * descartó sin aplicar).
 *
 * @see \App\Support\AffiliationCorporates\Population\PopulationUpdateAnalyzer
 */
class CorporatePopulationUpdate extends Model
{
    public const STATUS_ANALYZING = 'ANALYZING';

    public const STATUS_ANALYZED = 'ANALYZED';

    public const STATUS_APPLYING = 'APPLYING';

    public const STATUS_APPLIED = 'APPLIED';

    public const STATUS_ROLLING_BACK = 'ROLLING_BACK';

    public const STATUS_ROLLED_BACK = 'ROLLED_BACK';

    public const STATUS_FAILED = 'FAILED';

    public const STATUS_DISCARDED = 'DISCARDED';

    public const MODE_FULL = 'FULL';

    protected $table = 'corporate_population_updates';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'public_id',
        'affiliation_corporate_id',
        'status',
        'mode',
        'file_disk',
        'file_path',
        'file_name',
        'file_sha1',
        'file_format',
        'mapping',
        'summary',
        'totals_before',
        'totals_after',
        'rollback_summary',
        'error',
        'analyzed_by',
        'analyzed_by_name',
        'analyzed_at',
        'approved_by',
        'approved_by_name',
        'approved_at',
        'applied_at',
        'rolled_back_by',
        'rolled_back_by_name',
        'rolled_back_at',
        'rollback_note',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mapping' => 'array',
            'summary' => 'array',
            'totals_before' => 'array',
            'totals_after' => 'array',
            'rollback_summary' => 'array',
            'analyzed_at' => 'datetime',
            'approved_at' => 'datetime',
            'applied_at' => 'datetime',
            'rolled_back_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AffiliationCorporate, $this>
     */
    public function affiliationCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliationCorporate::class);
    }

    /**
     * @return HasMany<CorporatePopulationUpdateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(CorporatePopulationUpdateItem::class);
    }

    /**
     * Ajustes de facturación hechos en Administración por esta carga (uno aplicado como máximo).
     *
     * @return HasMany<CorporateBillingAdjustment, $this>
     */
    public function billingAdjustments(): HasMany
    {
        return $this->hasMany(CorporateBillingAdjustment::class);
    }

    /**
     * @return HasManyThrough<CorporateBillingAdjustmentLine, CorporateBillingAdjustment, $this>
     */
    public function billingAdjustmentLines(): HasManyThrough
    {
        return $this->hasManyThrough(CorporateBillingAdjustmentLine::class, CorporateBillingAdjustment::class);
    }

    public function statusLabel(): string
    {
        return self::labelFor((string) $this->status);
    }

    public static function labelFor(string $status): string
    {
        return match ($status) {
            self::STATUS_ANALYZING => 'Analizando',
            self::STATUS_ANALYZED => 'Lista para revisar',
            self::STATUS_APPLYING => 'Aplicando',
            self::STATUS_APPLIED => 'Aplicada',
            self::STATUS_ROLLING_BACK => 'Revirtiendo',
            self::STATUS_ROLLED_BACK => 'Revertida',
            self::STATUS_FAILED => 'Falló (sin cambios)',
            self::STATUS_DISCARDED => 'Descartada',
            default => $status,
        };
    }

    public static function colorFor(string $status): string
    {
        return match ($status) {
            self::STATUS_ANALYZING, self::STATUS_APPLYING, self::STATUS_ROLLING_BACK => 'info',
            self::STATUS_ANALYZED => 'warning',
            self::STATUS_APPLIED => 'success',
            self::STATUS_ROLLED_BACK, self::STATUS_DISCARDED => 'gray',
            self::STATUS_FAILED => 'danger',
            default => 'gray',
        };
    }

    public function isBusy(): bool
    {
        return in_array($this->status, [self::STATUS_ANALYZING, self::STATUS_APPLYING, self::STATUS_ROLLING_BACK], true);
    }
}
