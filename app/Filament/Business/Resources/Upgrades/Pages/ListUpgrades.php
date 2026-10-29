<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Pages;

use App\Filament\Business\Resources\Upgrades\UpgradeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListUpgrades extends ListRecords
{
    protected static string $resource = UpgradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo upgrade')
                ->icon(Heroicon::Plus),
        ];
    }
}
