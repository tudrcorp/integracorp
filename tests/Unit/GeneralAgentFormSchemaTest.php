<?php

declare(strict_types=1);

it('usa pestañas en el formulario de agente general', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/General/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('generalAgentFormTabs')")
        ->toContain("Tab::make('Agentes')")
        ->toContain("Tab::make('Información principal')")
        ->toContain("Tab::make('Banca nacional')")
        ->toContain("Tab::make('Banca extranjera')")
        ->toContain("Tab::make('Comentarios')");
});
