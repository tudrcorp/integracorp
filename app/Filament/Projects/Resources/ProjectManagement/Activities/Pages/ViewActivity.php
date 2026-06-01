<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewActivity extends ViewRecord
{
    protected static string $resource = ActivityResource::class;

    protected static ?string $title = 'Detalles de la Actividad';

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
