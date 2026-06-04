<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Support\Filament\GlobalSearchSupplierQuery;

it('normaliza rif para búsqueda sin guiones ni espacios', function (): void {
    expect(GlobalSearchSupplierQuery::normalizeRif('j-30.123.456'))
        ->toBe('J30123456');
});

it('define consulta optimizada por nombre razón social y rif normalizado', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/GlobalSearchSupplierQuery.php');
    expect($src)->not->toBeFalse()
        ->and($src)->toContain("->where(\"{\$table}.name\", 'like', \$like)")
        ->and($src)->toContain('razon_social')
        ->and($src)->toContain('REPLACE(REPLACE(REPLACE(UPPER');
});

it('selecciona solo columnas necesarias para búsqueda global de proveedores', function (): void {
    $columns = GlobalSearchSupplierQuery::selectColumns(new Supplier);

    expect($columns)
        ->toContain('suppliers.id')
        ->toContain('suppliers.name')
        ->toContain('suppliers.rif')
        ->toContain('suppliers.status_sistema')
        ->toContain('suppliers.status_convenio')
        ->not->toContain('suppliers.observaciones');
});
