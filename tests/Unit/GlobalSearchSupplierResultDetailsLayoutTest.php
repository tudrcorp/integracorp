<?php

declare(strict_types=1);

it('organiza detalles de proveedor en filas sin rejilla de dos columnas de Filament', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/GlobalSearchSupplierResultDetails.php');
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($src)
        ->toContain('fi-global-search-supplier-body')
        ->toContain('fi-global-search-supplier-row--duo')
        ->toContain('fi-global-search-supplier-value--email');

    expect($css)
        ->toContain('.fi-global-search-supplier-body')
        ->toContain('flex-col gap-2');
});
