<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Pages;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Telemedicina\Resources\TelemedicineFollowUps\TelemedicineFollowUpResource;

class ViewTelemedicineFollowUp extends ViewRecord
{
    protected static string $resource = TelemedicineFollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicineFollowUpResource::getUrl('index')),
                EditAction::make()
                    ->label('Reportar Seguimiento')
                    ->button()
                    ->icon('heroicon-s-pencil')
                    ->color('warning'),
        ];
    }
}