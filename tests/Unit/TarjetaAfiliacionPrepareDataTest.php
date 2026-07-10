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
        ->and($data['plan_qr_size_px'])->toBe(82)
        ->and($data['plan_qr_top_px'])->toBe(378)
        ->and($data['plan_qr_right_px'])->toBe(108)
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

it('prepara qr para plantilla individual-affiliation con tamano ampliado', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Titular Demo',
        'ci' => 'V-1',
        'code' => 'TDEC-IND-1',
        'plan_id' => 1,
        'plan' => 'INICIAL',
        'card_layout' => 'individual-affiliation',
        'template_key' => 'individual-affiliation',
    ]);

    expect($data['plan_qr_filename'])->toBeNull()
        ->and($data['plan_qr_size_px'])->toBe(55)
        ->and($data['plan_qr_top_px'])->toBe(46)
        ->and($data['plan_qr_right_px'])->toBe(23)
        ->and($data['plan_qr_absolute_path'])->toEndWith('plan-1.png');
});

it('prepara qr para plantilla individual de afiliaciones', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Titular Demo',
        'ci' => 'V-1',
        'code' => 'TDEC-IND-1',
        'plan_id' => 2,
        'plan' => 'PLAN IDEAL',
        'card_layout' => 'individual',
    ]);

    expect($data['plan_qr_size_px'])->toBe(80)
        ->and($data['plan_qr_top_px'])->toBe(425)
        ->and($data['plan_qr_right_px'])->toBe(135);
});

it('la plantilla pdf de tarjeta usa la imagen completa del carnet como fondo', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/tarjeta-afiliado.blade.php');

    expect($blade)
        ->toContain('storage/certificados/tarjeta-afiliado.png')
        ->toContain('cover-template-image')
        ->not->toContain('fondo-certificado.png')
        ->not->toContain('carnet-title')
        ->not->toContain('carnet-recommendation-inner');
});

it('la plantilla individual usa imagen completa FEDEVIP v2', function (): void {
    $blade = file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/tarjeta-afiliado-individual.blade.php');

    expect($blade)
        ->toContain('storage/certificados/tarjeta-afiliado-individual.png')
        ->toContain('cover-template-image')
        ->toContain('top: 464px; left: 455px')
        ->toContain('top: 485px; left: 455px')
        ->not->toContain('fondo-certificado.png')
        ->not->toContain('carnet-title')
        ->not->toContain('carnet-recommendation-inner');
});
