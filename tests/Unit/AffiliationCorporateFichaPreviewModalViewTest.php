<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de ficha PDF corporativa incluye mensaje de espera y rutas esperadas', function (): void {
    $html = View::make('filament.administration.affiliation-corporates.affiliation-corporate-ficha-preview-modal', [
        'pdfPreviewUrl' => 'https://example.test/administration/affiliation-corporates/1/ficha/preview',
        'pdfDownloadUrl' => 'https://example.test/administration/affiliation-corporates/1/ficha/download',
        'recordLabel' => 'Empresa Demo',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando la ficha en PDF')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading');
});
