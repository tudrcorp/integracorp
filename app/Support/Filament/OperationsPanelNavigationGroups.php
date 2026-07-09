<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Navigation\NavigationGroup;

final class OperationsPanelNavigationGroups
{
    /**
     * @return list<string>
     */
    public static function labels(): array
    {
        return array_map(
            fn (NavigationGroup $group): string => (string) $group->getLabel(),
            self::definitions(),
        );
    }

    /**
     * @return list<NavigationGroup>
     */
    public static function definitions(): array
    {
        return [
            NavigationGroup::make()
                ->label('AFILIADOS')
                ->icon('heroicon-o-identification')
                ->collapsed(),
            NavigationGroup::make()
                ->label('INVENTARIO DIAGNOMOVIL')
                ->icon('heroicon-o-building-office-2')
                ->collapsed(),
            NavigationGroup::make()
                ->label('TELEMEDICINA')
                ->icon('heroicon-o-building-office-2')
                ->collapsed(),
            NavigationGroup::make()
                ->label('COORDINACIÓN DE SERVICIOS')
                ->icon('heroicon-o-square-3-stack-3d')
                ->collapsed(),
            NavigationGroup::make()
                ->label('CONFIGURACION')
                ->icon('heroicon-o-cog-8-tooth')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ZONA DE DESCARGA')
                ->icon('heroicon-o-cloud-arrow-down')
                ->collapsed(),
        ];
    }
}
