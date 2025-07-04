<?php

namespace App\Filament\Master\Resources\Commissions\Pages;

use App\Filament\Master\Resources\Commissions\CommissionResource;
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