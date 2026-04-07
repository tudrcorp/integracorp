<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SupplierFichaPdfService;
use Symfony\Component\HttpFoundation\Response;

class SupplierFichaPdfController extends Controller
{
    public function download(Supplier $supplier): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = SupplierFichaPdfService::outputBinaryCached($supplier);
        $filename = SupplierFichaPdfService::downloadFilename($supplier);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(Supplier $supplier): Response
    {
        self::prepareLongRunningPdfResponse();

        $binary = SupplierFichaPdfService::outputBinaryCached($supplier);
        $filename = SupplierFichaPdfService::downloadFilename($supplier);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

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
