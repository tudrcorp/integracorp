<?php

declare(strict_types=1);

use Illuminate\Support\Facades\View;

uses(Tests\TestCase::class);

it('renderiza vista de carta de aceptación para proveedor natural', function (): void {
    $html = View::make('filament.operations.doctor-nurses.carta-acceptance-preview', [
        'exists' => true,
        'url' => 'https://example.test/doc.pdf',
        'downloadUrl' => 'https://example.test/doc-download.pdf',
        'extension' => 'pdf',
        'doctorNurse' => null,
    ])->render();

    expect($html)
        ->toContain('Abrir en pestaña')
        ->toContain('Descargar');
});

it('renderiza vista de documentos de afiliación para proveedor natural', function (): void {
    $html = View::make('filament.operations.doctor-nurses.documents-preview', [
        'doctorNurse' => null,
        'documents' => [
            [
                'id' => 1,
                'index' => 0,
                'path' => 'doctor-nurses/documents/file.pdf',
                'exists' => true,
                'extension' => 'pdf',
                'name' => 'file.pdf',
                'url' => 'https://example.test/storage/doctor-nurses/documents/file.pdf',
                'download_url' => 'https://example.test/download/file.pdf',
            ],
        ],
    ])->render();

    expect($html)
        ->toContain('Documento 1')
        ->toContain('deleteDoctorNurseAffiliationDocument');
});
