<?php

namespace App\Filament\Resources\Affiliations\Pages;

use App\Filament\Resources\Affiliations\AffiliationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAffiliations extends ListRecords
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'Afiliaciones Individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva afiliación')
                ->icon('heroicon-m-plus'),
        ];
    }
}