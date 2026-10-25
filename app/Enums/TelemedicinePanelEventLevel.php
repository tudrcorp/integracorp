<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Gravedad de un evento de la bitácora del panel de Telemedicina.
 */
enum TelemedicinePanelEventLevel: string
{
    case Info = 'info';
    case Warning = 'warning';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Info => 'Información',
            self::Warning => 'Advertencia',
            self::Error => 'Error',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Info => 'gray',
            self::Warning => 'warning',
            self::Error => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
