<?php

namespace App\Filament\Administration\Resources\Commissions\Pages;

use App\Filament\Administration\Resources\Commissions\CommissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected static ?string $title = 'Detallado de Comisiones';
}
