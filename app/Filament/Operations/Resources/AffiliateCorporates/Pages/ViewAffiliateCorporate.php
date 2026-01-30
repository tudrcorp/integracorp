<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Pages;

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateCorporate extends ViewRecord
{
    protected static string $resource = AffiliateCorporateResource::class;

    protected static ?string $title = 'Información Detallada del Afiliado Corporativo';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AffiliateCorporateResource::getUrl())
        ];
    }
}
