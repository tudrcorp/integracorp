<?php

declare(strict_types=1);

it('mejora la tabla Capemiac sin estilos iOS dedicados', function (): void {
    $tablePath = __DIR__.'/../../app/Filament/Marketing/Resources/Capemiacs/Tables/CapemiacsTable.php';
    $themePath = __DIR__.'/../../resources/css/filament/admin/theme.css';

    $table = file_get_contents($tablePath);
    $theme = file_get_contents($themePath);

    expect($table)->not->toBeFalse()
        ->not->toContain('ios-table-capemiacs')
        ->toContain('emptyStateHeading')
        ->toContain('paginationPageOptions')
        ->toContain('Asociar información');

    expect($theme)->not->toBeFalse()
        ->not->toContain('.fi-ta.ios-table-capemiacs');
});
