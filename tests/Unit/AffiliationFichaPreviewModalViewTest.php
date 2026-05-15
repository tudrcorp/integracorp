<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('el modal de ficha PDF individual incluye mensaje de espera y rutas esperadas', function (): void {
    $html = View::make('filament.administration.affiliations.affiliation-ficha-preview-modal', [
        'pdfPreviewUrl' => 'https://example.test/administration/affiliations/1/ficha/preview',
        'pdfDownloadUrl' => 'https://example.test/administration/affiliations/1/ficha/download',
        'recordLabel' => 'María Titular',
    ])
        ->withErrors([])
        ->render();

    expect($html)
        ->toContain('Generando la ficha en PDF')
        ->toContain('x-ref="pdfPreview"')
        ->toContain('pdfPreviewLoading');
});
