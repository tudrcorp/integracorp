<?php

declare(strict_types=1);

use App\Support\Operations\SupplierEstatusSistemaOptions;

it('coloca CLINICA CERRADA al final del select de estatus', function () {
    $options = SupplierEstatusSistemaOptions::orderedForSelect([
        'CLINICA CERRADA',
        'AFILIADO',
        'EN PROCESO',
        'NO DESEA CONVENIO',
    ]);

    expect(array_keys($options))->toBe([
        'AFILIADO',
        'EN PROCESO',
        'NO DESEA CONVENIO',
        'CLINICA CERRADA',
    ])->and($options[SupplierEstatusSistemaOptions::CLINICA_CERRADA])->toBe('CLINICA CERRADA');
});

it('conserva el orden de entrada cuando CLINICA CERRADA no está en el catálogo', function () {
    $options = SupplierEstatusSistemaOptions::orderedForSelect([
        'SIN RESPUESTA DE AFILIACION',
        'AFILIADO',
        'EN PROCESO',
    ]);

    expect(array_keys($options))->toBe([
        'SIN RESPUESTA DE AFILIACION',
        'AFILIADO',
        'EN PROCESO',
    ]);
});

it('el formulario de proveedores jurídicos usa el catálogo ordenado', function () {
    $source = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierForm.php'
    );

    expect($source)
        ->toContain('SupplierEstatusSistemaOptions::forSelect()')
        ->toContain("->label('Estatus del convenio')");
});
