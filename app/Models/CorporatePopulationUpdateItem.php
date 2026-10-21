<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una persona dentro de una carga de población: qué propone el sistema, qué
 * decidió el analista y la foto del afiliado antes del cambio.
 */
class CorporatePopulationUpdateItem extends Model
{
    public const ACTION_ADD = 'ADD';

    public const ACTION_REACTIVATE = 'REACTIVATE';

    public const ACTION_DEACTIVATE = 'DEACTIVATE';

    public const ACTION_FIX_DOCUMENT = 'FIX_DOCUMENT';

    public const ACTION_MATCH = 'MATCH';

    public const ACTION_REVIEW = 'REVIEW';

    public const ACTION_DUPLICATE_BASE = 'DUPLICATE_BASE';

    public const ACTION_DUPLICATE_FILE = 'DUPLICATE_FILE';

    public const ACTION_BLOCKED_OPEN_CASE = 'BLOCKED_OPEN_CASE';

    public const ACTION_INVALID = 'INVALID';

    public const APPLY_PENDING = 'PENDING';

    public const APPLY_APPLIED = 'APPLIED';

    public const APPLY_SKIPPED = 'SKIPPED';

    public const APPLY_REVERTED = 'REVERTED';

    public const APPLY_NOT_REVERTED = 'NOT_REVERTED';

    /** Acciones que cambian datos si se aprueban. */
    public const CHANGING_ACTIONS = [self::ACTION_ADD, self::ACTION_REACTIVATE, self::ACTION_DEACTIVATE, self::ACTION_FIX_DOCUMENT];

    protected $table = 'corporate_population_update_items';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'corporate_population_update_id',
        'action',
        'selectable',
        'selected',
        'file_row',
        'file_data',
        'affiliate_corporate_id',
        'match_via',
        'reason',
        'details',
        'snapshot',
        'result',
        'apply_status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'selectable' => 'boolean',
            'selected' => 'boolean',
            'file_data' => 'array',
            'details' => 'array',
            'snapshot' => 'array',
            'result' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CorporatePopulationUpdate, $this>
     */
    public function populationUpdate(): BelongsTo
    {
        return $this->belongsTo(CorporatePopulationUpdate::class, 'corporate_population_update_id');
    }

    /**
     * @return BelongsTo<AffiliateCorporate, $this>
     */
    public function affiliateCorporate(): BelongsTo
    {
        return $this->belongsTo(AffiliateCorporate::class);
    }

    public static function actionLabel(string $action): string
    {
        return match ($action) {
            self::ACTION_ADD => 'Agregar',
            self::ACTION_REACTIVATE => 'Reactivar',
            self::ACTION_DEACTIVATE => 'Dar de baja',
            self::ACTION_FIX_DOCUMENT => 'Corregir cédula',
            self::ACTION_MATCH => 'Coincide',
            self::ACTION_REVIEW => 'Revisar',
            self::ACTION_DUPLICATE_BASE => 'Duplicado en la base',
            self::ACTION_DUPLICATE_FILE => 'Duplicado en el archivo',
            self::ACTION_BLOCKED_OPEN_CASE => 'Bloqueado: caso abierto',
            self::ACTION_INVALID => 'Fila incompleta',
            default => $action,
        };
    }

    public static function actionColor(string $action): string
    {
        return match ($action) {
            self::ACTION_ADD, self::ACTION_REACTIVATE => 'success',
            self::ACTION_DEACTIVATE => 'danger',
            self::ACTION_FIX_DOCUMENT => 'info',
            self::ACTION_REVIEW, self::ACTION_DUPLICATE_BASE, self::ACTION_DUPLICATE_FILE => 'warning',
            self::ACTION_BLOCKED_OPEN_CASE => 'danger',
            default => 'gray',
        };
    }
}
