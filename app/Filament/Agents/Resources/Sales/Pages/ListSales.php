<?php

namespace App\Filament\Agents\Resources\Sales\Pages;

use App\Filament\Agents\Resources\Sales\SaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSales extends ListRecords
{
    protected static string $resource = SaleResource::class;

    protected static ?string $title = 'GESTION DE VENTAS';


    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}