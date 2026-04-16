<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('define grupo de navegación compensacion tdev en panel administracion', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/Filament/AdministrationPanelProvider.php';
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain("->label('COMPENSACION TDEV')")
        ->toContain("->icon('heroicon-o-banknotes')");
});

it('ubica reporte de tdev dentro del grupo compensacion tdev', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/TdevReports/TdevReportResource.php';
    $contents = file_get_contents($resourcePath);

    expect($contents)
        ->toContain("protected static string|UnitEnum|null \$navigationGroup = 'COMPENSACION TDEV';")
        ->toContain("protected static ?string \$navigationLabel = 'Reporte de TDEV';");
});

it('expone página de compensacion de vaucher con búsqueda y tabs', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Administration/Pages/CompensacionVaucher.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/administration/pages/compensacion-vaucher.blade.php';

    expect(file_exists($pagePath))->toBeTrue()
        ->and(file_exists($viewPath))->toBeTrue();

    $pageContents = file_get_contents($pagePath);
    $viewContents = file_get_contents($viewPath);

    expect($pageContents)
        ->toContain("protected static ?string \$navigationLabel = 'Compensacion de Vaucher';")
        ->toContain('public function searchVouchers(): void')
        ->toContain('public function savePaymentTab(): void')
        ->toContain('public function saveStatusTab(): void')
        ->toContain('public function saveCommissionTab(): void')
        ->toContain('private function auditAction(string $action, array $details = []): void')
        ->toContain('TDEV_COMPENSACION_SAVE_PAYMENT_SUCCESS')
        ->toContain('TDEV_COMPENSACION_SAVE_STATUS_SUCCESS')
        ->toContain('TDEV_COMPENSACION_SAVE_COMMISSION_SUCCESS');

    expect($viewContents)
        ->toContain('wire:keydown.enter.prevent="searchVouchers"')
        ->toContain("activeTab==='pago'")
        ->toContain("activeTab==='estatus'")
        ->toContain("activeTab==='comision'")
        ->toContain("activeTab==='pendiente'");
});
