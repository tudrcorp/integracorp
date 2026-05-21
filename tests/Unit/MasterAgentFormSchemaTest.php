<?php

declare(strict_types=1);

it('usa pestañas en el formulario de agente master', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('masterAgentFormTabs')")
        ->toContain("Tab::make('Informacion Principal')")
        ->toContain("Tab::make('Comisiones')")
        ->toContain("Tab::make('Información Bancaria Local(VES)')")
        ->toContain("Tab::make('Datos Bancarios(US$)')")
        ->not->toContain('Wizard::make');
});
