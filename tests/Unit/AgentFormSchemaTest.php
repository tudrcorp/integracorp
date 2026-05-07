<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Schemas\AgentForm;
use Filament\Schemas\Schema;

it('configura el formulario de agente business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa pestañas en el formulario de agente', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Tabs::make('agentFormTabs')");
    expect($source)->toContain('Tab::make(');
});
