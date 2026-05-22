<?php

declare(strict_types=1);

it('navega al view al hacer click en una fila de ventas en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("SaleResource::getUrl('view', ['record' => \$record])")
        ->toContain('Clic para ver detalle de la venta');
});

it('registra la pagina view en el recurso de ventas', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/SaleResource.php');

    expect($source)
        ->toContain('ViewSale::route')
        ->toContain('SaleInfolist::configure');
});
