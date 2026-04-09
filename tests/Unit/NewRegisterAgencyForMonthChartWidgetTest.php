<?php

declare(strict_types=1);

it('alinea gráfico de agencias por mes con SupplierClasificationChart (paleta y opciones)', function (): void {
    $root = dirname(__DIR__, 2);

    $widget = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Widgets/NewRegisterAgencyForMountChart.php');
    expect($widget)
        ->toContain('new-register-agency-month-chart')
        ->and($widget)->toContain("protected string \$color = 'gray'")
        ->and($widget)->toContain("return 'bar'")
        ->and($widget)->toContain('countAgenciesRegisteredForMonth')
        ->and($widget)->toContain('function getFilters')
        ->and($widget)->toContain('function updatedFilter')
        ->and($widget)->toContain('getRegistrationsTotalInCurrentView')
        ->and($widget)->toContain('getEmptyRegistrationsMessage')
        ->and($widget)->toContain('protected function getOptions(): array')
        ->and($widget)->toContain("protected ?string \$maxHeight = '440px'")
        ->and($widget)->toContain('glassColorAt')
        ->and($widget)->toContain('brighterGlassFill')
        ->and($widget)->toContain('hoverBackgroundColor')
        ->and($widget)->toContain("'backgroundColor' => 'rgba(22, 22, 24, 0.56)'")
        ->and($widget)->toContain("'easeOutQuart'");

    $blade = file_get_contents($root.'/resources/views/filament/widgets/new-register-agency-month-chart.blade.php');
    expect($blade)
        ->toContain('getMaxHeight()')
        ->and($blade)->toContain('fi-agency-registrations-chart-like-suppliers')
        ->and($blade)->not->toContain('fi-glassmorphism-agency-chart')
        ->and($blade)->toContain('getRegistrationsTotalInCurrentView')
        ->and($blade)->toContain('getEmptyRegistrationsMessage')
        ->and($blade)->toContain('agency-registrations-chart-');

    expect($widget)->toContain('No hay agencias registradas en el año en curso');

    $css = file_get_contents($root.'/resources/css/filament/admin/theme.css');
    expect($css)
        ->toContain('.fi-agency-registrations-chart-like-suppliers .fi-wi-chart-canvas-ctn canvas')
        ->and($css)->toContain('.dark .fi-agency-registrations-chart-like-suppliers .fi-wi-chart-canvas-ctn canvas');

    $list = file_get_contents($root.'/app/Filament/Business/Resources/Agencies/Pages/ListAgencies.php');
    expect($list)->toContain('NewRegisterAgencyForMountChart::class');
});
