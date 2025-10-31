<?php

namespace App\Filament\Administration\Resources\CommissionPayrolls\Pages;

use App\Filament\Administration\Resources\CommissionPayrolls\CommissionPayrollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommissionPayrolls extends ListRecords
{
    protected static string $resource = CommissionPayrollResource::class;

    protected static ?string $title = 'Reporte de Comisiones';
    

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
