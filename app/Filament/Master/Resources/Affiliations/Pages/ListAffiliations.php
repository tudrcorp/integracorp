<?php

namespace App\Filament\Master\Resources\Affiliations\Pages;

use App\Filament\Master\Resources\Affiliations\AffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'GESTIÓN DE AFILIACIONES INDIVIDUALES';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Crear')
                ->icon('heroicon-s-user'),
        ];
    }
}