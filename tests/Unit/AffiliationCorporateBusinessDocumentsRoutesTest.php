<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('registra rutas de documentos de afiliación corporativa en business', function (): void {
    $routesPath = dirname(__DIR__, 2).'/routes/web.php';
    $contents = file_get_contents($routesPath);

    expect($contents)
        ->toContain('business.affiliation-corporate-documents.regenerate-async')
        ->toContain('business.affiliation-corporate-documents.status')
        ->toContain('business.affiliation-corporate-documents.send-email')
        ->toContain('business.affiliation-corporate-tarjeta-qr.associate-plan');
});

it('renderiza la vista modal de documentos de afiliación corporativa', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/affiliation-corporates/affiliation-corporate-documents-preview-modal.blade.php';
    $html = file_get_contents($viewPath);

    expect($html)
        ->toContain('affiliationDocumentsPanel')
        ->toContain('regenerate()')
        ->toContain('statusUrlTemplate')
        ->toContain('regenerate-async');
});

it('el servicio corporativo normaliza lotes anidados de tarjetas', function (): void {
    $servicePath = dirname(__DIR__, 2).'/app/Services/AffiliationCorporateBusinessDocumentsService.php';

    expect(file_get_contents($servicePath))->toContain('normalizeTarjetaPayloads');
});
