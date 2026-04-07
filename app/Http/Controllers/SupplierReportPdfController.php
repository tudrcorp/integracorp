<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SupplierReportPdfService;
use Symfony\Component\HttpFoundation\Response;

class SupplierReportPdfController extends Controller
{
    public function download(): Response
    {
        $pdf = SupplierReportPdfService::make();

        return $pdf->download(SupplierReportPdfService::FILENAME);
    }

    public function preview(): Response
    {
        $pdf = SupplierReportPdfService::make();

        return $pdf->stream(SupplierReportPdfService::FILENAME);
    }
}
