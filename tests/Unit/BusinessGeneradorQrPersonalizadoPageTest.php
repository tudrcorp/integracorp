<?php

declare(strict_types=1);

it('registra el centro de qr en el panel business dentro de configuracion', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/GeneradorQrPersonalizado.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/generador-qr-personalizado.blade.php';
    $administrationPanelProviderPath = dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php';

    expect(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue();

    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);

    expect($pageContents)
        ->toContain("protected static ?string \$navigationLabel = 'Centro de QR';")
        ->toContain("protected static ?string \$title = 'Centro de QR de tarjetas';")
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'CONFIGURACIÓN';")
        ->toContain("protected string \$view = 'filament.business.pages.generador-qr-personalizado';")
        ->toContain('Heroicon::OutlinedQrCode')
        ->toContain('getIndividualQrPlanOptions')
        ->toContain('getCorporateQrPlanOptions')
        ->toContain('QrCenterAccess::allows()')
        ->toContain('private function authorizedUser(): User');

    // La librería se sirve desde el proyecto: si el CDN no responde, el generador sigue funcionando.
    expect($viewContents)
        ->toContain("asset('vendor/qr-code-styling/1.8.0/qr-code-styling.js')")
        ->not->toContain('cdn.jsdelivr.net')
        ->not->toContain('alert(')
        ->toContain('this.$wire.saveDestination(')
        ->toContain("errorCorrectionLevel: 'H'")
        ->toContain('x-ref="editorPreview"')
        ->toContain('x-ref="freePreview" wire:ignore');

    expect(file_exists(dirname(__DIR__, 2).'/public/vendor/qr-code-styling/1.8.0/qr-code-styling.js'))->toBeTrue();

    expect(file_get_contents($administrationPanelProviderPath))
        ->not->toContain('GeneradorQrPersonalizado');
});

it('el estado alpine del centro de qr no cambia entre repintados', function (): void {
    $viewContents = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/generador-qr-personalizado.blade.php');

    preg_match('/class="qrc"\s+x-data="(\{.*?\n        \})"/s', $viewContents, $root);

    expect($root)->toHaveKey(1)
        ->and(substr_count($root[1], '@js('))->toBe(1)
        ->and($root[1])->toContain('config: @js($studioConfig)')
        ->and($root[1])->not->toContain('{{');
});
