<?php

namespace App\Jobs;

use Filament\Actions\Exports\Jobs\PrepareCsvExport;

class PrepareSupplierCsvExport extends PrepareCsvExport
{
    public function getExportCsvJob(): string
    {
        return SupplierExportCsv::class;
    }
}
