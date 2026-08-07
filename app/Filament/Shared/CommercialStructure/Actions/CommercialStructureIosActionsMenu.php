<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure\Actions;

use App\Support\Filament\FilamentIosActionsMenu;
use Filament\Actions\ActionGroup;

final class CommercialStructureIosActionsMenu
{
    /**
     * @param  array<int, mixed>  $actions
     */
    public static function make(array $actions, string $label = 'Acciones'): ActionGroup
    {
        return FilamentIosActionsMenu::make($actions, $label);
    }
}
