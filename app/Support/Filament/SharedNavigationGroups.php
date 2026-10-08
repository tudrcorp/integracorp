<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Navigation\NavigationGroup;

/**
 * Grupos de navegación que comparten varios paneles internos, con un solo
 * nombre, ícono y comportamiento.
 *
 * - «DEL USUARIO»: herramientas personales de uso diario (Helpdesk, Agenda
 *   Corporativa, Calendarios TDG). Va arriba y abierto en Negocios,
 *   Administración, Marketing y Operaciones.
 * - «MONITOREO Y SEGURIDAD»: Actividad de usuarios, Monitor en vivo y Colas y
 *   errores. Solo existe en Negocios, al final y plegado.
 */
final class SharedNavigationGroups
{
    public const USER = 'DEL USUARIO';

    public const MONITORING = 'MONITOREO Y SEGURIDAD';

    public static function user(): NavigationGroup
    {
        return NavigationGroup::make()
            ->label(self::USER)
            ->icon('heroicon-o-user-circle')
            ->collapsed(false);
    }

    public static function monitoring(): NavigationGroup
    {
        return NavigationGroup::make()
            ->label(self::MONITORING)
            ->icon('heroicon-o-shield-check')
            ->collapsed();
    }
}
