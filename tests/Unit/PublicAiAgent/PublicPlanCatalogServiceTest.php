<?php

declare(strict_types=1);

use App\Services\PublicAiAgent\PublicPlanCatalogService;

uses(Tests\TestCase::class);

it('formatea montos de cobertura para el chat publico', function (): void {
    $service = new PublicPlanCatalogService;

    expect($service->formatCoverageAmountForChat(1000))->toBe('US$1.000')
        ->and($service->formatCoverageAmountForChat(10000))->toBe('US$10.000')
        ->and($service->formatCoverageAmountForChat(50000))->toBe('US$50.000');
});

it('construye resumen de planes sin codigos internos y con coberturas legibles', function (): void {
    $service = new PublicPlanCatalogService;

    $summary = $service->buildPlanCatalogChatSummary([
        [
            'plan_id' => 1,
            'code' => 'TDEC-PL-0001',
            'description' => 'Plan Inicial',
            'coverages' => [],
        ],
        [
            'plan_id' => 2,
            'code' => 'TDEC-PL-0002',
            'description' => 'Plan Ideal',
            'coverages' => [
                ['coverage_id' => 2, 'price' => 1000],
                ['coverage_id' => 6, 'price' => 10000],
            ],
        ],
        [
            'plan_id' => 3,
            'code' => 'TDEC-PL-0003',
            'description' => 'Plan Especial',
            'coverages' => [
                ['coverage_id' => 10, 'price' => 50000],
            ],
        ],
    ]);

    expect($summary)
        ->toContain('• Plan 1 — PLAN INICIAL')
        ->toContain('Coberturas: consultar con un asesor')
        ->toContain('• Plan 2 — PLAN IDEAL')
        ->toContain('Coberturas: US$1.000, US$10.000')
        ->toContain('• Plan 3 — PLAN ESPECIAL')
        ->toContain('Coberturas: US$50.000')
        ->not->toContain('TDEC-PL-0001')
        ->not->toContain('#2 (1,000 USD)');
});

it('construye resumen de coberturas del plan seleccionado con formato usd', function (): void {
    $service = new PublicPlanCatalogService;

    $summary = $service->buildCoverageChatSummaryForPlan([
        'plan_id' => 2,
        'description' => 'Plan Ideal',
        'coverages' => [
            ['coverage_id' => 2, 'price' => 1000],
            ['coverage_id' => 6, 'price' => 10000],
        ],
    ]);

    expect($summary)
        ->toContain('• Cobertura 2 — US$1.000')
        ->toContain('• Cobertura 6 — US$10.000')
        ->not->toContain('USD');
});
