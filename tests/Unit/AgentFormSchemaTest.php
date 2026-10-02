<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Schemas\AgentForm;
use Filament\Schemas\Schema;

it('configura el formulario de agente business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('permite letras en ci rif id o pasaporte de banca extranjera', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php');

    expect($source)
        ->toContain("TextInput::make('extra_beneficiary_ci_rif')")
        ->not->toMatch("/TextInput::make\('extra_beneficiary_ci_rif'\)[\s\S]{0,500}->numeric\(\)/");
});

it('usa pestañas en el formulario de agente', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Tabs::make('agentFormTabs')");
    expect($source)->toContain('Tab::make(');
});

it('define opciones para el select de sexo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php');

    expect($source)
        ->toContain("Select::make('sex')")
        ->toContain("'MASCULINO' => 'MASCULINO'")
        ->toContain("'FEMENINO' => 'FEMENINO'");
});
