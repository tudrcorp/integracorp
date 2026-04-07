<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\OperationServiceOrder;
use App\Services\OperationServiceOrderPdfService;
use Symfony\Component\HttpFoundation\Response;

class OperationServiceOrderPdfController extends Controller
{
    public function download(OperationServiceOrder $operationServiceOrder): Response
    {
        $pdf = OperationServiceOrderPdfService::make($operationServiceOrder);

        return $pdf->download(OperationServiceOrderPdfService::filename($operationServiceOrder));
    }

    public function preview(OperationServiceOrder $operationServiceOrder): Response
    {
        $pdf = OperationServiceOrderPdfService::make($operationServiceOrder);

        return $pdf->stream(OperationServiceOrderPdfService::filename($operationServiceOrder));
    }
}
