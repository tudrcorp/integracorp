<?php

declare(strict_types=1);

use App\Http\Controllers\TarjetaAfiliacionController;

uses(Tests\TestCase::class);

it('prepara datos de vista de tarjeta con etiqueta de plan y cobertura formateada', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Juan Carlos Pérez López',
        'ci' => 'V-1',
        'code' => 'X',
        'plan' => 'PLAN IDEAL',
        'frecuencia' => 'ANUAL',
        'cobertura' => '100.5',
        'desde' => '01/01/2025',
        'hasta' => '01/01/2026',
    ]);

    expect($data['plan_tarjeta_etiqueta'])->toBe('IDEAL')
        ->and($data['cobertura_display'])->toBe('100,50 US$')
        ->and($data['name_first_part'])->not->toBeEmpty();
});
