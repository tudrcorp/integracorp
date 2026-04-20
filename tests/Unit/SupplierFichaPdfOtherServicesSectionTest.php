<?php

declare(strict_types=1);

it('incluye la seccion de otros servicios en la ficha pdf del proveedor', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/documents/supplier-ficha.blade.php';
    $contents = file_get_contents($viewPath);

    expect($contents)
        ->toContain('section-title">Otros servicios')
        ->toContain('$supplier->otros_servicios')
        ->toContain('nl2br(e((string) $supplier->otros_servicios))');
});
