<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de vista previa de ficha del proveedor natural usa el visor estandar', function (): void {
    $html = View::make('filament.operations.doctor-nurses.doctor-nurse-ficha-preview-modal', [
        'pdfPreviewUrl' => 'https://example.test/operations/doctor-nurses/1/ficha/preview',
        'pdfDownloadUrl' => 'https://example.test/operations/doctor-nurses/1/ficha/download',
        'doctorNurseLabel' => 'Proveedor Natural Demo',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando la ficha en PDF')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading')
        ->toContain('Descargar PDF');
});
