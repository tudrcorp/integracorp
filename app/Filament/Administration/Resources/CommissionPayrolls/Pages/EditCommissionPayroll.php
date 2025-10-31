<?php

namespace App\Filament\Administration\Resources\CommissionPayrolls\Pages;

use App\Filament\Administration\Resources\CommissionPayrolls\CommissionPayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionPayroll extends EditRecord
{
    protected static string $resource = CommissionPayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
