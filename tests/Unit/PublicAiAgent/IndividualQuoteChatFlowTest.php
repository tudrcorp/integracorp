<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\IntentSlotFiller;
use App\Services\PublicAiAgent\PublicPlanBenefitsService;

uses(Tests\TestCase::class);

it('detecta solicitud de beneficios por plan', function (): void {
    $filler = new IntentSlotFiller;

    expect($filler->parsePlanBenefitsRequest('1 beneficios'))->toBe(1)
        ->and($filler->parsePlanBenefitsRequest('2beneficios'))->toBe(2)
        ->and($filler->parsePlanBenefitsRequest('3 beneficios'))->toBe(3)
        ->and($filler->parsePlanBenefitsRequest('cotizar'))->toBeNull();
});

it('detecta la palabra cotizar y multiple', function (): void {
    $filler = new IntentSlotFiller;

    expect($filler->isCotizarKeyword('cotizar'))->toBeTrue()
        ->and($filler->isCotizarKeyword('Cotizar'))->toBeTrue()
        ->and($filler->isCotizarKeyword('1 beneficios'))->toBeFalse()
        ->and($filler->isMultipleKeyword('multiple'))->toBeTrue()
        ->and($filler->isMultipleKeyword('múltiple'))->toBeTrue();
});

it('parsea lineas de cotizacion compacta y completa', function (): void {
    $filler = new IntentSlotFiller;

    expect($filler->parseIndividualQuoteLine('1, 10'))->toMatchArray([
        'plan_id' => 1,
        'age' => null,
        'total_persons' => 10,
        'format' => 'compact',
    ])->and($filler->parseIndividualQuoteLine('2, 45, 10'))->toMatchArray([
        'plan_id' => 2,
        'age' => 45,
        'total_persons' => 10,
        'format' => 'full',
    ]);
});

it('construye mensaje de beneficios del plan inicial', function (): void {
    $service = new PublicPlanBenefitsService;

    $message = $service->buildBenefitsMessage(1);

    expect($message)
        ->toContain('1 .- Plan Inicial')
        ->toContain('Asistencia Médica en Sitio')
        ->toContain('Telemedicina');
});

it('recuerda escribir cotizar despues de beneficios', function (): void {
    $service = new PublicPlanBenefitsService;

    expect($service->benefitsReminderMessage())->toContain('cotizar');
});
