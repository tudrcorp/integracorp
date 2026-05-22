<?php

declare(strict_types=1);

it('navega al view al hacer click en una fila de agentes en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Tables/AgentsTable.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->recordUrl(fn (Agent \$record): string => AgentResource::getUrl('view', ['record' => \$record]))")
        ->not->toContain("Action::make('view_agent_profile')");
});
