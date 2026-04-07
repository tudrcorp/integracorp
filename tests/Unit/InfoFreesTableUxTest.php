<?php

declare(strict_types=1);

it('incluye mejoras de UI y UX en la tabla InfoFrees', function (): void {
    $path = __DIR__.'/../../app/Filament/Marketing/Resources/InfoFrees/Tables/InfoFreesTable.php';
    $contents = file_get_contents($path);

    expect($contents)->not->toBeFalse()
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('Asociar información')
        ->toContain('Data externa (FREE)');
});
