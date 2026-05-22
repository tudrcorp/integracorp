<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agents\Schemas\AgentInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agente business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgentInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('expone la relación observationCommercialStructures en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("RepeatableEntry::make('observationCommercialStructures')");
});

it('formatea fecha de nacimiento con FilamentDateDisplay para cadenas d/m/Y', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('FilamentDateDisplay::toDmy');
    expect($source)->toContain("TextEntry::make('birth_date')");
});

it('incluye una pestaña de jerarquía comercial con diagrama visual', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Section::make('Jerarquía comercial')")
        ->toContain("TextEntry::make('hierarchy_diagram')")
        ->toContain('renderHierarchyDiagram');
});

it('aplica estilos de contenedor de tabs alineados a telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Schemas/AgentInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain('->persistTab()')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.25rem]');
});
