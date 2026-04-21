<?php

declare(strict_types=1);

it('la plantilla de vista previa incluye iframe y mensaje de error', function () {
    $path = dirname(__DIR__, 2).'/resources/views/filament/operations/suppliers/carta-acceptance-preview.blade.php';
    $contents = file_get_contents($path);
    expect($contents)
        ->toContain('iframe')
        ->toContain('No se encontró el archivo')
        ->toContain('Eliminar carta');
});
