<?php

namespace App\Filament\Operations\Resources\OperationMedicationFailures\Pages;

use App\Filament\Operations\Resources\OperationMedicationFailures\OperationMedicationFailureResource;
use App\Support\Operations\OperationInventoryListHeaders;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationMedicationFailures extends ListRecords
{
    protected static string $resource = OperationMedicationFailureResource::class;

    protected static ?string $title = 'Fallas de medicamentos';

    public function getHeading(): string|Htmlable
    {
        return OperationInventoryListHeaders::medicationFailures();
    }
}
