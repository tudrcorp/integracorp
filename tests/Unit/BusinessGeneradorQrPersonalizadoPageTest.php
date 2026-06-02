<?php

declare(strict_types=1);

it('registra el generador qr personalizado en el panel business dentro de configuracion', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/GeneradorQrPersonalizado.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/generador-qr-personalizado.blade.php';
    $administrationPanelProviderPath = dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php';

    expect(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue();

    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);

    expect($pageContents)
        ->toContain('namespace App\\Filament\\Business\\Pages;')
        ->toContain("protected static ?string \$navigationLabel = 'Generador QR personalizado';")
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'CONFIGURACIÓN';")
        ->toContain("protected string \$view = 'filament.business.pages.generador-qr-personalizado';")
        ->toContain('Heroicon::OutlinedQrCode');

    expect($viewContents)
        ->toContain('qr-code-styling')
        ->toContain('id="qrPreview"')
        ->toContain('downloadPngBtn')
        ->toContain('livewire:navigated')
        ->toContain('bootQrGenerator');

    expect(file_get_contents($administrationPanelProviderPath))
        ->not->toContain('GeneradorQrPersonalizado');
});
