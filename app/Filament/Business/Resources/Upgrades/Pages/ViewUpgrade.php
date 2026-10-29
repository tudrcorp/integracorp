<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Upgrades\Pages;

use App\Filament\Business\Resources\Upgrades\UpgradeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewUpgrade extends ViewRecord
{
    protected static string $resource = UpgradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare),
        ];
    }
}
