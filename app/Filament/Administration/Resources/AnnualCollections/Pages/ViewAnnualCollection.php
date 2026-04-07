<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Pages;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewAnnualCollection extends ViewRecord
{
    protected static string $resource = AnnualCollectionResource::class;

    protected static ?string $title = 'Detalle de Cobranza';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('volver')
                ->label('Volver a lista de cobranza')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(AnnualCollectionResource::getUrl('index')),
        ];
    }
}
