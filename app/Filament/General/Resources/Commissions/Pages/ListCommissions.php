<?php

namespace App\Filament\General\Resources\Commissions\Pages;

use App\Filament\General\Resources\Commissions\CommissionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissions extends ListRecords
{
    protected static string $resource = CommissionResource::class;

    protected static ?string $title = 'Comisiones';

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}