<?php

namespace App\Filament\Resources\CommissionPayrolls\Pages;

use App\Filament\Resources\CommissionPayrolls\CommissionPayrollResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionPayroll extends ViewRecord
{
    protected static string $resource = CommissionPayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
