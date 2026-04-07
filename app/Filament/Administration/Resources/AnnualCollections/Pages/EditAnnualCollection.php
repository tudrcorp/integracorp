<?php

namespace App\Filament\Administration\Resources\AnnualCollections\Pages;

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAnnualCollection extends EditRecord
{
    protected static string $resource = AnnualCollectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
