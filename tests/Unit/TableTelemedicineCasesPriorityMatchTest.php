<?php

declare(strict_types=1);

it('TableTelemedicineCases maneja NO ASIGNADA y fallback en prioridad sin lanzar UnhandledMatchError', function (): void {
    $path = dirname(__DIR__, 2).'/app/Livewire/FilamentTable/TableTelemedicineCases.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("'NO ASIGNADA' => 'gray'")
        ->and($contents)->toContain("default => 'gray'")
        ->and($contents)->toContain("'NO ASIGNADA' => 'heroicon-o-minus-circle'")
        ->and($contents)->toContain("default => 'heroicon-o-minus-circle'");
});
