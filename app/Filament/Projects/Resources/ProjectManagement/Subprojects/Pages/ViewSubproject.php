<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSubproject extends ViewRecord
{
    protected static string $resource = SubprojectResource::class;

    protected static ?string $title = 'Detalles del Subproyecto';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-s-pencil')
                ->color('success')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('success'),
                ]),
        ];
    }
}
