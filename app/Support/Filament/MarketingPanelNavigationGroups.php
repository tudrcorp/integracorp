<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Navigation\NavigationGroup;

final class MarketingPanelNavigationGroups
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
                ->label('AFILIACIONES')
                ->icon('heroicon-o-user-group')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ESTRUCTURA DE CORRETAJES')
                ->icon('heroicon-o-building-office-2')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ESTRUCTURA DE VIAJES')
                ->icon('heroicon-o-paper-airplane')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ADMINISTRACION/RRHH')
                ->icon('heroicon-o-users')
                ->collapsed(),
            NavigationGroup::make()
                ->label('MARKETING')
                ->icon('heroicon-o-megaphone')
                ->collapsed(),
            NavigationGroup::make()
                ->label('VENTAS DIRECTAS')
                ->icon('heroicon-m-cursor-arrow-rays')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ZONA DE DESCARGA')
                ->icon('heroicon-o-cloud-arrow-down')
                ->collapsed(),
        ];
    }
}
