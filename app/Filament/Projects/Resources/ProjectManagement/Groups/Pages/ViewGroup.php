<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Pages;

use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGroup extends ViewRecord
{
    protected static string $resource = GroupResource::class;

    protected static ?string $title = 'Detalles del Grupo';

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
