<?php

declare(strict_types=1);

namespace App\Support\Operations\PatientWhatsApp;

use App\Models\ServiceOrderPatientWhatsAppSetting;
use Throwable;

/**
 * Configuración del WhatsApp al paciente, memorizada unos segundos (los workers viven horas).
 *
 * Dos llaves, las dos deben estar abiertas para enviar a pacientes:
 * - **El interruptor de la pantalla** (Operaciones → «WhatsApp al paciente»), apagado por
 *   defecto. Es el que maneja el analista.
 * - **El bloqueo del servidor** (`SERVICE_ORDER_PATIENT_WHATSAPP_ENABLED`, abierto por defecto):
 *   un freno de emergencia en el `.env` que también detiene las pruebas.
 *
 * Si la tabla no existe o no se puede leer, el envío queda apagado: nunca se enciende solo.
 */
final class ServiceOrderPatientWhatsAppSettings
{
    private const TTL_SECONDS = 30;

    /** @var array{enabled: bool, response_hours: int}|null */
    private static ?array $settings = null;

    private static int $loadedAt = 0;

    /** @var array{enabled: bool, response_hours: int}|null */
    private static ?array $override = null;

    public static function serverAllows(): bool
    {
        return (bool) config('services.service_order_patient_whatsapp.server_enabled', true);
    }

    public static function switchedOn(): bool
    {
        return self::settings()['enabled'];
    }

    public static function enabled(): bool
    {
        return self::serverAllows() && self::switchedOn();
    }

    public static function responseHours(): int
    {
        return self::settings()['response_hours'];
    }

    public static function flush(): void
    {
        self::$settings = null;
        self::$loadedAt = 0;
    }

    /**
     * Fija la configuración sin tocar la base (pruebas). null vuelve a leerla.
     */
    public static function override(?bool $enabled, int $responseHours = ServiceOrderPatientWhatsAppSetting::DEFAULT_RESPONSE_HOURS): void
    {
        self::$override = $enabled === null ? null : ['enabled' => $enabled, 'response_hours' => ServiceOrderPatientWhatsAppSetting::clampHours($responseHours)];
        self::flush();
    }

    /**
     * @return array{enabled: bool, response_hours: int}
     */
    private static function settings(): array
    {
        if (self::$override !== null) {
            return self::$override;
        }

        if (self::$settings !== null && (time() - self::$loadedAt) < self::TTL_SECONDS) {
            return self::$settings;
        }

        $settings = ['enabled' => false, 'response_hours' => ServiceOrderPatientWhatsAppSetting::DEFAULT_RESPONSE_HOURS];

        try {
            $row = ServiceOrderPatientWhatsAppSetting::query()->orderBy('id')->first();

            if ($row instanceof ServiceOrderPatientWhatsAppSetting) {
                $settings = [
                    'enabled' => (bool) $row->enabled,
                    'response_hours' => ServiceOrderPatientWhatsAppSetting::clampHours((int) $row->response_hours),
                ];
            }
        } catch (Throwable) {
            // Sin la tabla todavía: apagado.
        }

        self::$loadedAt = time();

        return self::$settings = $settings;
    }
}
