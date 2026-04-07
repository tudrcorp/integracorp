<?php

declare(strict_types=1);

namespace App\Support;

use Barryvdh\DomPDF\PDF as PdfDocument;

/**
 * Ajustes del motor Dompdf para menos trabajo por PDF, sin cambiar HTML/CSS ni resolución tipográfica.
 */
final class DomPdfBatchRenderOptions
{
    public static function apply(PdfDocument $pdf): void
    {
        $pdf->setOptions([
            'isRemoteEnabled' => false,
            'isJavascriptEnabled' => false,
            'isPhpEnabled' => false,
        ], mergeWithDefaults: true);
    }
}
