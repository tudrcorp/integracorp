<?php

namespace App\Filament\Agents\Resources\Sales\Pages;

use App\Filament\Agents\Resources\Sales\SaleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
