<?php

namespace App\Filament\Resources\CheckSales\Pages;

use App\Filament\Resources\CheckSales\CheckSaleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCheckSale extends ViewRecord
{
    protected static string $resource = CheckSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
