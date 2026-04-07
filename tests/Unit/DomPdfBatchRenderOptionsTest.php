<?php

declare(strict_types=1);

use App\Support\DomPdfBatchRenderOptions;
use Barryvdh\DomPDF\Facade\Pdf;

uses(Tests\TestCase::class);

it('aplica opciones de motor Dompdf sin lanzar', function () {
    $pdf = Pdf::loadHTML('<html><body><p>x</p></body></html>');
    DomPdfBatchRenderOptions::apply($pdf);
    expect(true)->toBeTrue();
});
