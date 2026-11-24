<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fila única con el interruptor del WhatsApp al paciente de las órdenes de servicio no
 * cubiertas (apagado por defecto) y las horas que tiene para responder. Se lee a través de
 * {@see \App\Support\Operations\PatientWhatsApp\ServiceOrderPatientWhatsAppSettings}.
 */
class ServiceOrderPatientWhatsAppSetting extends Model
{
    public const DEFAULT_RESPONSE_HOURS = 72;

    public const MIN_RESPONSE_HOURS = 1;

    public const MAX_RESPONSE_HOURS = 720;

    protected $table = 'service_order_patient_whatsapp_settings';

    protected $fillable = [
        'enabled',
        'response_hours',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'response_hours' => 'integer',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrCreate([], [
            'enabled' => false,
            'response_hours' => self::DEFAULT_RESPONSE_HOURS,
        ]);
    }

    public static function clampHours(int $hours): int
    {
        return max(self::MIN_RESPONSE_HOURS, min(self::MAX_RESPONSE_HOURS, $hours));
    }
}
