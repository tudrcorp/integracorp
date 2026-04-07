<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

test('vista PDF de reporte de proveedores renderiza sin error', function (): void {
    $html = view('documents.suppliers-report', [
        'reportRows' => [],
        'generatedAt' => now(),
        'logoDataUri' => '',
    ])->render();

    expect($html)
        ->toContain('Red de Proveedores en Venezuela')
        ->toContain('TU DR. GROUP')
        ->toContain('www.tudrgroup.com')
        ->toContain('INTEGRACORP')
        ->toContain('Generado:')
        ->not->toContain('Orden: estado')
        ->toContain('Estado')
        ->toContain('Ciudad')
        ->toContain('Clasificación');
});

test('invitado es redirigido al intentar vista previa del reporte de proveedores', function (): void {
    $this->get(route('operations.suppliers.report.preview'))
        ->assertRedirect();
});
