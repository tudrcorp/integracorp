<?php

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Pages;

use App\Filament\Operations\Resources\OperationMedicationInvoices\OperationMedicationInvoiceResource;
use App\Support\Operations\OperationInventoryListHeaders;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListOperationMedicationInvoices extends ListRecords
{
    protected static string $resource = OperationMedicationInvoiceResource::class;

    protected static ?string $title = 'Facturas a pacientes';

    public function getHeading(): string|Htmlable
    {
        return OperationInventoryListHeaders::medicationInvoices();
    }
}
