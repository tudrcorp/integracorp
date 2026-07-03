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
        ->and($data['plan_qr_filename'])->toBe('qr-plan-ideal.png')
        ->and($data['plan_qr_size_px'])->toBe(73)
        ->and($data['cobertura_display'])->toBe('100,50 US$')
        ->and($data['name_first_part'])->not->toBeEmpty();
});

it('prepara datos de vista de tarjeta usando plan_id para qr extendido', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'María López',
        'ci' => 'V-2',
        'code' => 'Y',
        'plan_id' => 11,
        'plan' => 'PLAN ESTUDIANTIL',
        'frecuencia' => 'ANUAL',
        'cobertura' => '50',
        'desde' => '01/01/2025',
        'hasta' => '01/01/2026',
    ]);

    expect($data['plan_qr_filename'])->toBe('qr-plan-11.png');
});

it('prepara cobertura textual y plan inclusion para tarjetas de nuevos negocios', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Asociado Demo',
        'ci' => 'V-123',
        'code' => 'NB-1-2',
        'plan' => 'INCLUSIÓN',
        'frecuencia' => 'CONTADO',
        'cobertura' => 'LOCAL',
        'desde' => '02/07/2026',
        'hasta' => '02/07/2026',
    ]);

    expect($data['plan_tarjeta_etiqueta'])->toBe('INCLUSIÓN')
        ->and($data['cobertura_display'])->toBe('LOCAL')
        ->and($data['frecuencia'])->toBe('CONTADO');
});

it('la plantilla pdf de tarjeta incluye recomendacion centrada debajo del carnet', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/tarjeta-afiliado.blade.php');

    expect($blade)
        ->toContain('carnet-title')
        ->toContain('carnet-recommendation-inner')
        ->toContain('width: 66.667%')
        ->toContain('no es un requisito obligatorio')
        ->toContain('Recomendamos que nuestro afiliado conserve su tarjeta cerca de sus documentos personales.');
});
