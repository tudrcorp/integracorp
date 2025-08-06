<?php

namespace App\Filament\Resources\CheckSales\Pages;

use App\Filament\Resources\CheckSales\CheckSaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCheckSales extends ListRecords
{
    protected static string $resource = CheckSaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
