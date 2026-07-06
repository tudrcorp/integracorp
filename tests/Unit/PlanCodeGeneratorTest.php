<?php

declare(strict_types=1);

use App\Support\Plans\PlanCodeGenerator;
use Tests\TestCase;

uses(TestCase::class);

it('genera codigos de plan con el prefijo del sistema', function (): void {
    expect(PlanCodeGenerator::next())->toStartWith('TDEC-PL-000');
});

it('formulario de plan incluye codigo autogenerado y campo type', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Plans/Schemas/PlanForm.php');

    expect($php)->toContain('PlanCodeGenerator::next()')
        ->toContain("TextInput::make('code')")
        ->toContain("Select::make('type')");
});

it('create plan no inserta created_by en benefit_plans', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Plans/Pages/CreatePlan.php');
    $model = file_get_contents(__DIR__.'/../../app/Models/BenefitPlan.php');

    expect($php)->toContain('syncPackageBenefits')
        ->not->toContain("'created_by' => auth()->user()->name");

    expect($model)->not->toContain("'created_by'");
});
