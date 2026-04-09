<?php

declare(strict_types=1);

it('alinea TotalSaleForEstructureChart con estilo SupplierClasificationChart y conserva la paleta hex', function (): void {
    $root = dirname(__DIR__, 2);
    $widgetPath = $root.'/app/Filament/Business/Resources/Agencies/Widgets/TotalSaleForEstructureChart.php';
    $bladePath = $root.'/resources/views/filament/widgets/total-sale-for-estructure-chart.blade.php';

    $widget = file_get_contents($widgetPath);
    expect($widget)
        ->toContain('total-sale-for-estructure-chart')
        ->and($widget)->toContain("protected ?string \$maxHeight = '440px'")
        ->and($widget)->toContain("protected string \$color = 'gray'")
        ->and($widget)->toContain('#38bdf8')
        ->and($widget)->toContain('easeOutQuart')
        ->and($widget)->toContain('rgba(22, 22, 24, 0.56)')
        ->and($widget)->toContain('categoryPercentage: 0.92');

    $blade = file_get_contents($bladePath);
    expect($blade)
        ->toContain('getMaxHeight()')
        ->and($blade)->toContain('fi-agency-registrations-chart-like-suppliers')
        ->and($blade)->toContain('total-sale-estructure-');
});
