<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A dónde lleva el QR de un tipo de tarjeta (alcance + plan).
 *
 * El QR impreso codifica el enlace estable `/q/{code}`, que redirige al destino
 * de la versión vigente: cambiarlo no exige reimprimir tarjetas.
 */
class QrDestination extends Model
{
    public const SCOPE_INDIVIDUAL = 'individual';

    public const SCOPE_CORPORATE = 'corporativo';

    public const SCOPE_INCLUSION = 'inclusion';

    /** Página HTML subida al centro de QR. */
    public const TARGET_HOSTED_PAGE = 'hosted_page';

    /** Una dirección https externa. */
    public const TARGET_URL = 'url';

    /** El documento del alcance: condicionado del plan o PDF de inclusión. */
    public const TARGET_DOCUMENT = 'document';

    /** Sin QR personalizado: la tarjeta vuelve al QR automático de siempre. */
    public const TARGET_AUTOMATIC = 'automatic';

    protected $fillable = [
        'code',
        'scope',
        'plan_key',
        'plan_id',
        'target_type',
        'current_version_id',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'plan_key' => 'integer',
            'plan_id' => 'integer',
            'current_version_id' => 'integer',
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [self::SCOPE_INDIVIDUAL, self::SCOPE_CORPORATE, self::SCOPE_INCLUSION];
    }

    /**
     * @return list<string>
     */
    public static function targetTypes(): array
    {
        return [self::TARGET_HOSTED_PAGE, self::TARGET_URL, self::TARGET_DOCUMENT, self::TARGET_AUTOMATIC];
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<QrDestinationVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(QrDestinationVersion::class, 'current_version_id');
    }

    /**
     * @return HasMany<QrDestinationVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(QrDestinationVersion::class)->orderByDesc('version');
    }

    /**
     * @return HasMany<QrScan, $this>
     */
    public function scans(): HasMany
    {
        return $this->hasMany(QrScan::class);
    }

    /**
     * La tarjeta imprime el QR personalizado solo si la versión vigente no es «automático».
     */
    public function isCustomized(): bool
    {
        return $this->target_type !== self::TARGET_AUTOMATIC && $this->current_version_id !== null;
    }
}
