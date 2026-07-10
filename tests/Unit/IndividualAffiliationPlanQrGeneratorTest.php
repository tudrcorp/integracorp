<?php

declare(strict_types=1);

use App\Http\Controllers\TarjetaAfiliacionController;
use App\Services\AffiliationBusinessDocumentsService;
use App\Support\AffiliateCard\IndividualAffiliationPlanQrGenerator;

uses(Tests\TestCase::class);

it('resuelve la url publica del condicionado por plan_id', function () {
    expect(AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId(1))
        ->toEndWith('/storage/condicionados/CondicionesINICIAL.pdf')
        ->and(AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId(2))
        ->toEndWith('/storage/condicionados/CondicionesIDEAL.pdf')
        ->and(AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId(3))
        ->toEndWith('/storage/condicionados/CondicionesESPECIAL.pdf')
        ->and(AffiliationBusinessDocumentsService::condicionadoPublicUrlForPlanId(99))->toBeNull();
});

it('genera qr dinamico para carnet individual-affiliation segun plan_id', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Titular Demo',
        'ci' => 'V-1',
        'code' => 'TDEC-IND-1',
        'plan_id' => 2,
        'plan' => 'IDEAL',
        'card_layout' => 'individual-affiliation',
        'template_key' => 'individual-affiliation',
    ]);

    expect($data['plan_qr_filename'])->toBeNull()
        ->and($data['plan_qr_absolute_path'])->toEndWith('plan-2.png')
        ->and(is_file((string) $data['plan_qr_absolute_path']))->toBeTrue()
        ->and(IndividualAffiliationPlanQrGenerator::condicionadoUrlForPlanId(2))
        ->toEndWith('/storage/condicionados/CondicionesIDEAL.pdf');
});

it('usa qr estatico de plan para otras plantillas de tarjeta', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Titular Demo',
        'ci' => 'V-1',
        'code' => 'TDEC-IND-1',
        'plan_id' => 1,
        'plan' => 'INICIAL',
        'card_layout' => 'individual',
    ]);

    expect($data['plan_qr_filename'])->toBe('qr-plan-inicial.png');
});

it('usa qr de inclusion como respaldo local cuando falta el qr del plan en plantillas no individual-affiliation', function () {
    $data = TarjetaAfiliacionController::prepareDataForTarjetaPdfView([
        'name' => 'Titular Demo',
        'ci' => 'V-1',
        'code' => 'TDEC-IND-1',
        'plan_id' => 1,
        'plan' => 'INICIAL',
    ]);

    expect($data['plan_qr_absolute_path'])->toEndWith('qr-plan-inclusion.png');
})->skip(fn (): bool => app()->environment('production'), 'Solo aplica en entornos no productivos');
