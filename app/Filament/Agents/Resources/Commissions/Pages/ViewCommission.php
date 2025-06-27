<?php

namespace App\Filament\Agents\Resources\Commissions\Pages;

use App\Filament\Agents\Resources\Commissions\CommissionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCommission extends ViewRecord
{
    protected static string $resource = CommissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
