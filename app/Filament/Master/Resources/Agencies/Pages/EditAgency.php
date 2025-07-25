<?php

namespace App\Filament\Master\Resources\Agencies\Pages;

use App\Filament\Master\Resources\Agencies\AgencyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAgency extends EditRecord
{
    protected static string $resource = AgencyResource::class;

    protected static ?string $title = 'Perfil de Agencia';

    protected function getHeaderActions(): array
    {
        return [
            // ViewAction::make(),
            // DeleteAction::make(),
        ];
    }
}