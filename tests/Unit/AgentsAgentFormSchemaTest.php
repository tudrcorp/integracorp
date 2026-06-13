<?php

declare(strict_types=1);

it('usa pestañas con estilos en el formulario de agente del panel agents', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Schemas/AgentForm.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tabs::make('agentsAgentFormTabs')")
        ->toContain('hasDuplicatedEmail')
        ->toContain('Agency::query()')
        ->toContain('User::query()')
        ->toContain('registrado en las tablas de agentes, agencias o usuarios')
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain("Tab::make('Información Personal')")
        ->toContain("Tab::make('Comisiones')")
        ->toContain("Tab::make('Información Bancaria Local(VES)')")
        ->toContain("Tab::make('Información Bancaria Extra(US$)')")
        ->toContain("Tab::make('Acuerdo y condiciones')")
        ->toContain("Select::make('state_id')")
        ->toContain('->createOptionForm([')
        ->toContain('->createOptionAction(fn (Action $action): Action => $action')
        ->toContain('createStateForAgentForm')
        ->toContain('->createOptionUsing(fn (array $data, Get $get, Set $set): int => self::createStateForAgentForm($data, $get, $set)')
        ->not->toContain('Wizard::make');
});
