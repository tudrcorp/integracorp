<?php

namespace App\Filament\Resources\CommissionPayrolls\Pages;

use App\Filament\Resources\CommissionPayrolls\CommissionPayrollResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCommissionPayroll extends EditRecord
{
    protected static string $resource = CommissionPayrollResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
