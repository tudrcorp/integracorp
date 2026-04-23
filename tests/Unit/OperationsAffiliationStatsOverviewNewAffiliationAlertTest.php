<?php

declare(strict_types=1);

it('widget de resumen en operaciones contempla afiliaciones nuevas del dia y ubicacion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Widgets/StatsOverview.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("whereDate('created_at', \$now->toDateString())")
        ->toContain('state:id,definition')
        ->toContain('city:id,definition')
        ->toContain('Nueva afiliacion hoy:')
        ->toContain('nuevas hoy en')
        ->toContain('heroicon-m-bell-alert');
});
