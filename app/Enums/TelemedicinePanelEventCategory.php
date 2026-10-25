<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Qué tipo de cosa le pasó al médico en el panel de Telemedicina.
 */
enum TelemedicinePanelEventCategory: string
{
    /** Abrió una pantalla. */
    case Navigation = 'navegacion';

    /** Pulsó un botón, abrió una acción, avanzó de paso o guardó. */
    case Action = 'accion';

    /** Un mensaje de validación le impidió guardar o avanzar. */
    case Validation = 'tropiezo';

    /** Error del servidor o aviso de fallo que el sistema le mostró. */
    case Error = 'error';

    /** Pulsó un botón y el sistema no mostró nada: ni éxito, ni error, ni aviso. */
    case NoResponse = 'sin_respuesta';

    /** Fallo en el navegador: petición caída, sesión vencida, error de JavaScript. */
    case Browser = 'navegador';

    public function label(): string
    {
        return match ($this) {
            self::Navigation => 'Navegación',
            self::Action => 'Acción',
            self::Validation => 'Tropiezo',
            self::Error => 'Error',
            self::NoResponse => 'Sin respuesta',
            self::Browser => 'Navegador',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Navigation => 'gray',
            self::Action => 'info',
            self::Validation => 'warning',
            self::Error => 'danger',
            self::NoResponse => 'danger',
            self::Browser => 'danger',
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
