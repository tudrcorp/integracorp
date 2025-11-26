<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWhiteCompany extends EditRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
