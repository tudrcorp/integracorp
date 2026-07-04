<?php

declare(strict_types=1);

use App\Support\Companies\CompanyAssociateInclusionQrCatalog;
use App\Support\Companies\CompanyAssociateInclusionQrGenerator;
use App\Support\TarjetaAfiliacionQrPlanCatalog;

uses(Tests\TestCase::class);

it('resuelve qr de inclusion para tarjetas de nuevos negocios', function (): void {
    expect(TarjetaAfiliacionQrPlanCatalog::resolveQrFilename(null, 'INCLUSIÓN'))
        ->toBe(CompanyAssociateInclusionQrCatalog::QR_FILENAME);
});

it('expone rutas publicas del pdf, qr y logo de inclusion', function (): void {
    expect(CompanyAssociateInclusionQrCatalog::pdfStoragePath())
        ->toBe('tarjeta-afiliacion/documentos/canales-de-comunicacion.pdf')
        ->and(CompanyAssociateInclusionQrCatalog::qrStoragePath())
        ->toBe('tarjeta-afiliacion/planes/qr-plan-inclusion.png')
        ->and(CompanyAssociateInclusionQrCatalog::logoSourceAbsolutePath())
        ->toEndWith('image/logo-qr-inclusion.png')
        ->and(CompanyAssociateInclusionQrCatalog::LOGO_CENTER_SCALE)->toBe(0.42);
});

it('expone url de vista previa cuando el qr existe', function (): void {
    $qrPath = \Illuminate\Support\Facades\Storage::disk('public')->path(CompanyAssociateInclusionQrCatalog::qrStoragePath());

    if (! is_file($qrPath)) {
        expect(CompanyAssociateInclusionQrCatalog::qrExists())->toBeFalse()
            ->and(CompanyAssociateInclusionQrCatalog::qrPreviewUrl())->toBeNull();

        return;
    }

    expect(CompanyAssociateInclusionQrCatalog::qrExists())->toBeTrue()
        ->and(CompanyAssociateInclusionQrCatalog::qrPreviewUrl())
        ->toContain('qr-plan-inclusion.png')
        ->toContain('?t=');
});

it('genera pdf y qr de inclusion con logo corporativo en el centro', function (): void {
    if (! CompanyAssociateInclusionQrGenerator::isGenerationEnabled()) {
        expect(CompanyAssociateInclusionQrGenerator::isGenerationEnabled())->toBeFalse();

        return;
    }

    $pdfFixture = sys_get_temp_dir().'/canales-comunicacion-test.pdf';
    file_put_contents($pdfFixture, '%PDF-1.4 test');

    $result = CompanyAssociateInclusionQrGenerator::generate($pdfFixture);

    expect($result['pdf_path'])->toBeFile()
        ->and($result['qr_path'])->toBeFile()
        ->and($result['logo_path'])->toBe(CompanyAssociateInclusionQrCatalog::logoSourceAbsolutePath())
        ->and(filesize($result['qr_path']))->toBeGreaterThan(1000)
        ->and($result['pdf_url'])->toContain('canales-de-comunicacion.pdf');

    @unlink($pdfFixture);
});

it('registra ruta para asociar qr de inclusion en nuevos negocios', function (): void {
    $routes = file_get_contents(dirname(__DIR__, 2).'/routes/web.php');
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/generador-qr-personalizado.blade.php');
    $generator = file_get_contents(dirname(__DIR__, 2).'/app/Support/Companies/CompanyAssociateInclusionQrGenerator.php');

    expect($routes)->toContain('business.company-associate-tarjeta-qr.associate-inclusion');
    expect($generator)
        ->toContain('isGenerationEnabled')
        ->toContain("environment('production')");
    expect($blade)
        ->toContain('downloadAndAssociateInclusionBtn')
        ->toContain('nuevos negocios (Inclusión)')
        ->toContain("@unless (app()->environment('production'))");
});
