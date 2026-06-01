<?php

declare(strict_types=1);

it('usa pestañas con estilos en el formulario de agente del panel agents', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('agentsAgentFormTabs')")
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain("Tab::make('Información Personal')")
        ->toContain("Tab::make('Comisiones')")
        ->toContain("Tab::make('Información Bancaria Local(VES)')")
        ->toContain("Tab::make('Información Bancaria Extra(US$)')")
        ->toContain("Tab::make('Acuerdo y condiciones')")
        ->not->toContain('Wizard::make');
});
