<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Navigation\NavigationGroup;

final class MetricsPanelNavigationGroups
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
                ->label('NEGOCIOS')
                ->icon('heroicon-o-briefcase')
                ->collapsed(false),
            NavigationGroup::make()
                ->label('COTIZACIONES')
                ->icon('heroicon-o-currency-dollar')
                ->collapsed(),
            NavigationGroup::make()
                ->label('AFILIACIONES')
                ->icon('heroicon-o-identification')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ADMINISTRACION')
                ->icon('heroicon-o-building-library')
                ->collapsed(),
            NavigationGroup::make()
                ->label('PROVEEDORES')
                ->icon('heroicon-o-truck')
                ->collapsed(),
            NavigationGroup::make()
                ->label('OPERACIONES')
                ->icon('heroicon-o-cog-6-tooth')
                ->collapsed(),
            NavigationGroup::make()
                ->label('PROYECTOS')
                ->icon('heroicon-o-folder-open')
                ->collapsed(),
        ];
    }
}
