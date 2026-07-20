<?php

declare(strict_types=1);

use App\Support\Operations\SupplierInfrastructureCatalog;

it('expone seis grupos de certificacion de infraestructura juridica', function (): void {
    $groups = SupplierInfrastructureCatalog::groups();

    expect($groups)->toHaveKeys([
        'Especialidades Básicas y Hospitalización',
        'Especialidades Médicas y Quirúrgicas',
        'Unidades Médicas Clínicas Especializadas',
        'Servicios de Apoyo y Diagnóstico',
        'Otras facilidades',
        'OTRAS Unidades Médicas Especializadas Tipo AA',
    ]);

    expect($groups['Especialidades Básicas y Hospitalización'])->toHaveCount(4)
        ->and($groups['Especialidades Médicas y Quirúrgicas'])->toHaveCount(3)
        ->and($groups['Unidades Médicas Clínicas Especializadas'])->toHaveCount(6)
        ->and($groups['Servicios de Apoyo y Diagnóstico'])->toHaveCount(4)
        ->and($groups['Otras facilidades'])->toHaveCount(4)
        ->and($groups['OTRAS Unidades Médicas Especializadas Tipo AA'])->toHaveCount(9);
});

it('reutiliza columnas existentes y agrega las nuevas del catalogo', function (): void {
    $keys = collect(SupplierInfrastructureCatalog::groups())
        ->flatten(1)
        ->pluck('key')
        ->all();

    expect($keys)
        ->toContain('oftalmologia')
        ->toContain('oncologia')
        ->toContain('laboratorio_centro')
        ->toContain('dialisis')
        ->toContain('cirugia_general')
        ->toContain('unidad_uci')
        ->toContain('banco_sangre')
        ->toContain('imagenologia_avanzada');

    expect(SupplierInfrastructureCatalog::newBooleanColumns())
        ->toContain('cirugia_general')
        ->toContain('unidad_uci')
        ->not->toContain('oftalmologia')
        ->not->toContain('laboratorio_centro');

    expect(SupplierInfrastructureCatalog::newDescriptionColumns())
        ->toContain('descripcion_cirugia_general')
        ->toContain('descripcion_unidad_uci');
});

it('mapea oncologia al campo historico de descripcion con typo', function (): void {
    $oncologia = collect(SupplierInfrastructureCatalog::groups()['Unidades Médicas Clínicas Especializadas'])
        ->firstWhere('key', 'oncologia');

    expect($oncologia)
        ->not->toBeNull()
        ->and($oncologia['desc'])->toBe('descripcion_encologogia')
        ->and($oncologia['label'])->toBe('Unidad de Oncología');
});
