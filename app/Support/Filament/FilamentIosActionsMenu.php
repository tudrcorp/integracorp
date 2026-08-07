<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Actions\ActionGroup;
use Filament\Support\Icons\Heroicon;

final class FilamentIosActionsMenu
{
    /**
     * Agrupa acciones del header en un único botón iOS con menú desplegable.
     *
     * @param  array<int, mixed>  $actions
     */
    public static function make(array $actions, string $label = 'Acciones'): ActionGroup
    {
        return ActionGroup::make($actions)
            ->label($label)
            ->icon(Heroicon::OutlinedEllipsisHorizontal)
            ->color('primary')
            ->button()
            ->dropdownPlacement('bottom-end')
            ->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('primary').' fi-ios-actions-menu-trigger',
            ]);
    }
}
