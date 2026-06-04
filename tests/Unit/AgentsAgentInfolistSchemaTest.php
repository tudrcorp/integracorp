<?php

declare(strict_types=1);

use App\Filament\Agents\Resources\Agents\Schemas\AgentInfolist;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

it('configura el infolist de subagente con tabs estilo AgentForm', function (): void {
    $configured = AgentInfolist::configure(Schema::make());

    expect($configured)->toBeInstanceOf(Schema::class);

    $components = $configured->getComponents();
    expect($components)->toHaveCount(1)
        ->and($components[0])->toBeInstanceOf(Tabs::class);
});

it('aplica estilos de tabs y secciones en AgentInfolist del panel agents', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Schemas/AgentInfolist.php');

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('agentsAgentInfolistTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain('Información Personal')
        ->toContain('Información Bancaria Local(VES)')
        ->toContain('Acuerdo y condiciones');
});
