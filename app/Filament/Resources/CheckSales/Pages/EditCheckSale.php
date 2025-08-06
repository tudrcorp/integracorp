<?php

namespace App\Filament\Resources\CheckSales\Pages;

use App\Filament\Resources\CheckSales\CheckSaleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCheckSale extends EditRecord
{
    protected static string $resource = CheckSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
