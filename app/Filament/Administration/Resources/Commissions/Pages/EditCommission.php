<?php

namespace App\Filament\Administration\Resources\Commissions\Pages;

use App\Filament\Administration\Resources\Commissions\CommissionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommission extends EditRecord
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
