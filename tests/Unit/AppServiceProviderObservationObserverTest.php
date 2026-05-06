<?php

declare(strict_types=1);

it('registra el observer de observaciones comerciales', function (): void {
    $path = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($path);

    expect($contents)->toContain('ObservationCommercialStructure::observe(ObservationCommercialStructureObserver::class)');
});
