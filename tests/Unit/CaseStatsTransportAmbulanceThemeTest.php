<?php

declare(strict_types=1);

it('theme define estilos iOS danger para stat traslado en ambulancia', function (): void {
    $path = dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('.fi-telemedicine-case-stat-ios--transport-ambulance::before')
        ->and($contents)->toContain('.fi-telemedicine-case-stat-ios--transport-ambulance:hover')
        ->and($contents)->toContain('.fi-telemedicine-case-stat-ios--transport-ambulance .fi-wi-stats-overview-stat-value')
        ->and($contents)->toContain('#dc2626');
});
