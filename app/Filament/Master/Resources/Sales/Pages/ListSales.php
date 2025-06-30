<?php

namespace App\Filament\Master\Resources\Sales\Pages;

use App\Filament\Master\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'Ventas';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}