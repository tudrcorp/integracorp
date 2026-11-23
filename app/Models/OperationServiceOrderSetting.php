<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Operations\OperationServiceOrderValidity;
use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con la regla de caducidad de las órdenes de servicio.
 * Se lee a través de OperationServiceOrderValidity, que la memoriza y cae a los
 * valores por defecto si la tabla no está disponible.
 */
class OperationServiceOrderSetting extends Model
{
    protected $table = 'operation_service_order_settings';

    protected $fillable = [
        'expiration_enabled',
        'validity_days',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expiration_enabled' => 'boolean',
            'validity_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrCreate(
            [],
            [
                'expiration_enabled' => true,
                'validity_days' => OperationServiceOrderValidity::DEFAULT_VALIDITY_DAYS,
            ],
        );
    }

    public function validityDays(): int
    {
        return OperationServiceOrderValidity::clampDays((int) $this->validity_days);
    }
}
