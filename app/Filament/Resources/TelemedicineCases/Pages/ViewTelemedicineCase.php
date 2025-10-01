<?php

namespace App\Filament\Resources\TelemedicineCases\Pages;

use App\Filament\Resources\TelemedicineCases\TelemedicineCaseResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Detalle de Caso';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}