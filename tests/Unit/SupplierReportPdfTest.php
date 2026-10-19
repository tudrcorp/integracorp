<?php

declare(strict_types=1);

use App\Services\SupplierReportPdfService;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

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
        ->toContain('Generado: 18/09/2026 01:04 · INTEGRACORP')
        ->toContain('<div class="doc-footer-key">fecha-encriptada-de-prueba</div>')
        ->toContain('<table class="doc-closing">')
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

test('el filtro del reporte no deja que el OR del convenio escape al resto de condiciones', function (): void {
    $query = SupplierReportPdfService::applyEligibilityFilter(DB::table('suppliers'));
    $wheres = $query->wheres;

    expect($wheres[0]['type'])->toBe('Nested')
        ->and($wheres[0]['boolean'])->toBe('and')
        ->and(collect($wheres)->skip(1)->pluck('boolean')->unique()->all())->toBe(['and'])
        ->and($query->getBindings())->toBe(['GENERAL', '%PREFERENCIAL%', '', 'AFILIADO', 'ACTIVO AFILIADO']);
});

test('reportRows aplica el filtro de elegibilidad e invalida la caché anterior', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Services/SupplierReportPdfService.php');

    expect($source)
        ->toContain('->tap(static fn (Builder $query): Builder => self::applyEligibilityFilter($query))')
        ->toContain("private const LAYOUT_VERSION = 'v5';")
        ->and(SupplierReportPdfService::ELIGIBLE_SYSTEM_STATUSES)->toBe(['AFILIADO', 'ACTIVO AFILIADO']);
});

test('el modal muestra spinner al descargar y al enviar el PDF por correo', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/suppliers/suppliers-report-modal.blade.php');

    expect($view)
        ->toContain('x-on:click.prevent="download()"')
        ->toContain("x-text=\"downloading ? 'Generando PDF…' : 'Descargar PDF'\"")
        ->toContain("contentType.includes('application/pdf')")
        ->toContain('No se pudo generar el PDF. Intente de nuevo.')
        ->toContain('wire:loading.attr="disabled"')
        ->toContain('wire:loading.flex wire:target="sendSupplierReportPdf"')
        ->toContain('Generando y enviando PDF…');
});
