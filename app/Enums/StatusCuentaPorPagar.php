<?php

declare(strict_types=1);

namespace App\Enums;

use App\Enums\Concerns\ResolvesFromMixedState;

/**
 * Estatus de pago de una factura de proveedor registrada en cuentas por pagar.
 *
 * Los valores persistidos van sin acentos, en línea con el resto de estatus del
 * sistema (p. ej. «PENDIENTE POR APROBAR» en órdenes de servicio).
 */
enum StatusCuentaPorPagar: string
{
    use ResolvesFromMixedState;

    case PendientePorPagar = 'PENDIENTE POR PAGAR';

    case EnGestion = 'EN GESTION';

    case Pagada = 'PAGADA';

    public static function default(): self
    {
        return self::PendientePorPagar;
    }

    public function label(): string
    {
        return match ($this) {
            self::PendientePorPagar => 'Pendiente por pagar',
            self::EnGestion => 'En gestión',
            self::Pagada => 'Pagada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::PendientePorPagar => 'danger',
            self::EnGestion => 'warning',
            self::Pagada => 'success',
        };
    }

    public function filamentIcon(): string
    {
        return match ($this) {
            self::PendientePorPagar => 'heroicon-m-exclamation-circle',
            self::EnGestion => 'heroicon-m-arrow-path',
            self::Pagada => 'heroicon-m-check-circle',
        };
    }

    /**
     * Una factura pagada exige referencia, fecha y monto del pago.
     */
    public function requiresPaymentDetails(): bool
    {
        return $this === self::Pagada;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];

        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }

    protected static function legacyAliases(string $lower): ?self
    {
        return match ($lower) {
            'pagado', 'cancelada', 'cancelado' => self::Pagada,
            'en gestion', 'en gestión', 'gestionando', 'en proceso' => self::EnGestion,
            'pendiente', 'pendiente por pagar', 'por pagar' => self::PendientePorPagar,
            default => null,
        };
    }
}
