<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Navigation\NavigationGroup;

final class AdministrationPanelNavigationGroups
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
                ->label('ESTRUCTURA COMERCIAL')
                ->icon('heroicon-o-building-office-2')
                ->collapsed(),
            NavigationGroup::make()
                ->label('AFILIACIONES')
                ->icon('heroicon-o-identification')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ADMINISTRACIÓN')
                ->icon('heroicon-o-calculator')
                ->collapsed(),
            NavigationGroup::make()
                ->label('COMPENSACION TDEV')
                ->icon('heroicon-o-banknotes')
                ->collapsed(),
            NavigationGroup::make()
                ->label('RRHH')
                ->icon('heroicon-o-user-group')
                ->collapsed(),
            NavigationGroup::make()
                ->label('NOMINA')
                ->icon('heroicon-o-currency-dollar')
                ->collapsed(),
            NavigationGroup::make()
                ->label('ZONA DE DESCARGA')
                ->icon('heroicon-o-cloud-arrow-down')
                ->collapsed(),
        ];
    }
}
