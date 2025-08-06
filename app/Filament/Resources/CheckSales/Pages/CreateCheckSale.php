<?php

namespace App\Filament\Resources\CheckSales\Pages;

use App\Filament\Resources\CheckSales\CheckSaleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCheckSale extends CreateRecord
{
    protected static string $resource = CheckSaleResource::class;

    protected static ?string $title = 'Histórico de Ventas';
}