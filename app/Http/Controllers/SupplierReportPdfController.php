<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SupplierReportPdfService;
use Symfony\Component\HttpFoundation\Response;

class SupplierReportPdfController extends Controller
{
    public function download(): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = SupplierReportPdfService::outputBinaryCached();
        $filename = SupplierReportPdfService::FILENAME;

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = SupplierReportPdfService::outputBinaryCached();
        $filename = SupplierReportPdfService::FILENAME;

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * DomPDF con miles de filas puede superar 30s en producción; la caché evita repetir el trabajo,
     * pero el primer render tras invalidar sigue siendo pesado.
     */
    private static function prepareLongRunningPdfResponse(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $limit = config('supplier-report.pdf_memory_limit');
        if (is_string($limit) && $limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }
    }
}
