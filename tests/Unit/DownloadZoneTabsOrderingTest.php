<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

it('ordena por posición en módulos marketing, operaciones y administración', function (): void {
    $files = [
        base_path('app/Filament/Marketing/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Operations/Resources/DownloadZones/Pages/ListDownloadZones.php'),
        base_path('app/Filament/Administration/Resources/DownloadZones/Pages/ListDownloadZones.php'),
    ];

    foreach ($files as $file) {
        $contents = file_get_contents($file);
        expect($contents)->not->toBeFalse();

        expect($contents)
            ->toContain("->orderBy('position')")
            ->toContain("->orderBy('id')");
    }
});
