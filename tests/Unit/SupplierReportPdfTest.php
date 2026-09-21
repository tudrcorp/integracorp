<?php

declare(strict_types=1);

use App\Services\SupplierReportPdfService;
use Illuminate\Support\Facades\Crypt;

uses(Tests\TestCase::class);

test('vista PDF de reporte de proveedores renderiza sin error', function (): void {
    $html = view('documents.suppliers-report', [
        'reportRows' => [],
        'generatedAt' => now(),
        'generatedAtLabel' => '18/09/2026 01:04',
        'encryptedGeneratedAt' => 'fecha-encriptada-de-prueba',
        'logoDataUri' => '',
        'headerBannerDataUri' => 'data:image/png;base64,header',
        'footerBannerDataUri' => 'data:image/png;base64,footer',
    ])->render();

    expect($html)
        ->toContain('Red de Proveedores en Venezuela')
        ->toContain('TU DR. GROUP')
        ->toContain('www.tudrgroup.com')
        ->toContain('INTEGRACORP')
        ->toContain('Generado:')
        ->toContain('18/09/2026 01:04 - fecha-encriptada-de-prueba')
        ->toContain('header-banner')
        ->toContain('footer-banner')
        ->toContain('data:image/png;base64,header')
        ->toContain('data:image/png;base64,footer')
        ->not->toContain('Orden: estado')
        ->toContain('Estado')
        ->toContain('Ciudad')
        ->toContain('Clasificación');
});

test('invitado es redirigido al intentar vista previa del reporte de proveedores', function (): void {
    $this->get(route('operations.suppliers.report.preview'))
        ->assertRedirect();
});

test('la fecha de generación del reporte se encripta de forma reversible', function (): void {
    $generatedAt = now()->timezone(config('app.timezone'))->setTime(1, 4);
    $label = SupplierReportPdfService::generatedAtLabel($generatedAt);
    $encrypted = SupplierReportPdfService::encryptGeneratedAt($generatedAt);

    expect($label)
        ->toBe($generatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i'))
        ->and($encrypted)->not->toBe($label)
        ->and(Crypt::decryptString($encrypted))->toBe($label);
});

test('los banners del reporte de proveedores están disponibles como data URI', function (): void {
    $header = SupplierReportPdfService::cachedHeaderBannerDataUri();
    $footer = SupplierReportPdfService::cachedFooterBannerDataUri();

    expect($header)
        ->toStartWith('data:image/png;base64,')
        ->and(strlen($header))->toBeGreaterThan(100)
        ->and($footer)->toStartWith('data:image/png;base64,')
        ->and(strlen($footer))->toBeGreaterThan(100);
});
