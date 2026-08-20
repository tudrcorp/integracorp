<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\TravelAgencies\Pages;

use App\Filament\Administration\Resources\TravelAgencies\TravelAgencyResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTravelAgency extends ViewRecord
{
    protected static string $resource = TravelAgencyResource::class;

    protected static ?string $title = 'Ficha de Agencia de Viajes';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(TravelAgencyResource::getUrl()),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
        ];
    }
}
