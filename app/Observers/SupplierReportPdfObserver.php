<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\RegenerateSupplierReportPdfJob;
use App\Models\Supplier;

/**
 * Cualquier alta, edición o baja de un proveedor puede cambiar el reporte: se regenera en cola
 * para que el analista no espere el render de DomPDF al abrirlo.
 */
class SupplierReportPdfObserver
{
    public function saved(Supplier $supplier): void
    {
        RegenerateSupplierReportPdfJob::dispatchDebounced();
    }

    public function deleted(Supplier $supplier): void
    {
        RegenerateSupplierReportPdfJob::dispatchDebounced();
    }
}
