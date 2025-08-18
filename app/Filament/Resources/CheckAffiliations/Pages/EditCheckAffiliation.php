<?php

namespace App\Filament\Resources\CheckAffiliations\Pages;

use App\Filament\Resources\CheckAffiliations\CheckAffiliationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCheckAffiliation extends EditRecord
{
    protected static string $resource = CheckAffiliationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    //after
    protected function mutateFormDataBeforeSave(array $data): array
    {
        // dd($data);
        $data['status_migration'] = 'PENDIENTE POR MIGRAR';

        return $data;
    }
}