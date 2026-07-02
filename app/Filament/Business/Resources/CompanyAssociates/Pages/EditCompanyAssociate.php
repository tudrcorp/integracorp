<?php

namespace App\Filament\Business\Resources\CompanyAssociates\Pages;

use App\Filament\Business\Resources\CompanyAssociates\CompanyAssociateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCompanyAssociate extends EditRecord
{
    protected static string $resource = CompanyAssociateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
