<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Registro de la bitácora de servicios principales RETAIL. Solo se crea: editarlo o borrarlo lanza error,
 * para que la traza no se pueda alterar desde la aplicación.
 */
class RetailMainServiceLog extends Model
{
    public const ACTION_ADDED = 'agregado';

    public const ACTION_REACTIVATED = 'reactivado';

    public const ACTION_REMOVED = 'quitado';

    public const UPDATED_AT = null;

    protected $fillable = [
        'retail_main_service_id',
        'service_name',
        'service_label',
        'action',
        'note',
        'user_id',
        'user_name',
    ];

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('La bitácora de servicios RETAIL no se edita.'));
        static::deleting(fn (): never => throw new LogicException('La bitácora de servicios RETAIL no se borra.'));
    }

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function actionLabel(): string
    {
        return match ($this->action) {
            self::ACTION_ADDED => 'Agregado',
            self::ACTION_REACTIVATED => 'Reactivado',
            self::ACTION_REMOVED => 'Quitado',
            default => ucfirst((string) $this->action),
        };
    }
}
