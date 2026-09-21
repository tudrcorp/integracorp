<?php

declare(strict_types=1);

use App\Models\Plan;
use App\Support\Plans\PlanQuotability;
use App\Support\Storefront\StorefrontCatalog;
use App\Support\Storefront\StorefrontPlanNarrative;
use Tests\TestCase;

uses(TestCase::class);

it('el catalogo solo lista planes basicos activos', function (): void {
    $sql = StorefrontCatalog::query()->toSql();
    $bindings = StorefrontCatalog::query()->getBindings();

    expect($sql)->toContain('type')
        ->and($sql)->toContain('status')
        ->and($sql)->toContain('id')
        ->and($bindings)->toContain(PlanQuotability::TYPE_BASICO)
        ->and($bindings)->toContain('ACTIVO')
        ->and($bindings)->toContain(1)
        ->and($bindings)->toContain(2)
        ->and($bindings)->toContain(3)
        ->and($sql)->not->toContain('DRESS-TAILOR');
});

it('un id invalido no resuelve plan de catalogo', function (): void {
    expect(StorefrontCatalog::findActiveBasic(0))->toBeNull()
        ->and(StorefrontCatalog::findActiveBasic(-4))->toBeNull();
});

it('la narrativa convierte el plan tecnico en ficha de producto', function (): void {
    $inicial = new Plan(['description' => 'Inicial', 'code' => 'INI']);
    $ideal = new Plan(['description' => 'Plan Ideal']);
    $otro = new Plan(['description' => 'Vital']);

    expect(StorefrontPlanNarrative::keyFor($inicial))->toBe('inicial')
        ->and(StorefrontPlanNarrative::for($inicial)['kicker'])->toBe('')
        ->and(StorefrontPlanNarrative::keyFor($ideal))->toBe('ideal')
        ->and(StorefrontPlanNarrative::for($ideal)['kicker'])->toBe('')
        ->and(StorefrontPlanNarrative::for(new Plan(['description' => 'Especial']))['kicker'])->toBe('')
        ->and(StorefrontPlanNarrative::displayTitle('Especial'))->toBe('Plan Especial')
        ->and(StorefrontPlanNarrative::displayTitle('Plan Inicial'))->toBe('Plan Inicial')
        ->and(StorefrontPlanNarrative::for($otro)['key'])->toBe('otro')
        ->and(StorefrontPlanNarrative::for($inicial)['cover'])->toBe('image/storefront/plan-inicial.jpg')
        ->and(StorefrontPlanNarrative::coverWebp('image/storefront/plan-inicial.jpg'))->toBe('image/storefront/plan-inicial.webp')
        ->and(StorefrontPlanNarrative::for($ideal)['cover'])->toBe('image/storefront/plan-ideal.jpg')
        ->and(StorefrontPlanNarrative::formatMoney(1200))->toBe('US$ 1.200')
        ->and(StorefrontPlanNarrative::formatMoney(99.5))->toBe('US$ 99,50')
        ->and(StorefrontPlanNarrative::sentenceLabel('ATENCIÓN MÉDICA DOMICILIARIA'))->toBe('Atención médica domiciliaria')
        ->and(StorefrontPlanNarrative::sentenceLabel('Telemedicina'))->toBe('Telemedicina')
        ->and(StorefrontPlanNarrative::personName('GUSTAVO CAMACHO'))->toBe('Gustavo Camacho')
        ->and(StorefrontPlanNarrative::personName('SD'))->toBe('SD')
        ->and(StorefrontPlanNarrative::personName('Ana Pérez'))->toBe('Ana Pérez')
        ->and(StorefrontPlanNarrative::planLabel('PLAN INICIAL'))->toBe('Plan Inicial')
        ->and(StorefrontPlanNarrative::planLabel('Plan INICIAL'))->toBe('Plan Inicial')
        ->and(StorefrontPlanNarrative::planLabel('Plan Inicial'))->toBe('Plan Inicial')
        ->and(StorefrontPlanNarrative::phoneLabel('04127546890'))->toBe('0412 754 6890')
        ->and(StorefrontPlanNarrative::phoneLabel('0412 754 6890'))->toBe('0412 754 6890')
        ->and(StorefrontPlanNarrative::phoneLabel('584127018390'))->toBe('0412 701 8390');
});

it('las fotos de portada de cada plan básico existen en public', function (): void {
    $root = dirname(__DIR__, 2);

    expect(file_exists($root.'/public/image/storefront/plan-inicial.jpg'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/plan-ideal.jpg'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/plan-especial.jpg'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/plan-inicial.webp'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/plan-ideal.webp'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/plan-especial.webp'))->toBeTrue()
        ->and(file_exists($root.'/public/image/storefront/welcome.webp'))->toBeTrue();
});
