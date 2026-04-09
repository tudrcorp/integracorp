<?php

declare(strict_types=1);

namespace App\Support\Filament;

final class FilamentIosButton
{
    /**
     * Cola de utilidades alineada con proveedores (ListSuppliers) y theme.css (.aviso-btn-ios-*, .ticket-btn-ios-gray).
     */
    public const TAIL = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * @param  string  $color  Valor coherente con Action::color() en el mismo botón.
     */
    public static function extraClassForFilamentColor(string $color): string
    {
        $base = match ($color) {
            'primary' => 'aviso-btn-ios-primary',
            'success' => 'aviso-btn-ios-success',
            'danger', 'critico' => 'aviso-btn-ios-danger',
            'warning', 'urgencia' => 'aviso-btn-ios-warning',
            'info' => 'aviso-btn-ios-info',
            'estandar', 'gray', 'secondary' => 'ticket-btn-ios-gray',
            default => 'aviso-btn-ios-primary',
        };

        return $base.' '.self::TAIL;
    }
}
