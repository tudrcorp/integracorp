<?php

declare(strict_types=1);

it('TotalEstructureAgency usa la misma altura y vista que NewRegisterAgencyForMountChart', function (): void {
    $root = dirname(__DIR__, 2);
    $widgetPath = $root.'/app/Filament/Business/Resources/Agencies/Widgets/TotalEstructureAgency.php';
    $bladePath = $root.'/resources/views/filament/widgets/total-estructure-agency-chart.blade.php';

    $widget = file_get_contents($widgetPath);
    expect($widget)
        ->toContain('total-estructure-agency-chart')
        ->and($widget)->toContain("protected ?string \$maxHeight = '440px'")
        ->and($widget)->toContain("protected string \$color = 'gray'");

    $blade = file_get_contents($bladePath);
    expect($blade)
        ->toContain('getMaxHeight()')
        ->and($blade)->toContain('440px')
        ->and($blade)->toContain('fi-agency-registrations-chart-like-suppliers')
        ->and($blade)->toContain('total-estructure-agency-');
});
