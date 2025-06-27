<?php

namespace App\Filament\Resources\CommissionPayrolls\Pages;

use App\Filament\Resources\CommissionPayrolls\CommissionPayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionPayrolls extends ListRecords
{
    protected static string $resource = CommissionPayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
