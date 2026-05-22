<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia business sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('delega al infolist compartido de agencias en business', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('SharedAgencyInfolist::configure($schema)');
});

it('expone la relación observationCommercialStructures en el infolist', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("RepeatableEntry::make('observationCommercialStructures')");
});

it('formatea fechas legadas d/m/Y con FilamentDateDisplay en lugar de TextEntry::date', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('FilamentDateDisplay::toDmy');
    expect($source)->not->toContain("->date('d/m/Y')");
});

it('agrupa secciones alineadas al formulario y usa rejilla de cinco columnas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain("Section::make('Información general de la agencia')");
    expect($source)->toContain("Section::make('Contacto alternativo')");
    expect($source)->toContain('Grid::make(5)');
});

it('incluye una pestaña de jerarquía para resolver general, master y TUDRENCASA', function (): void {
    $agencyPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $agencySource = file_get_contents($agencyPath);
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $flowchartSource = file_get_contents($flowchartPath);

    expect($agencySource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain("Section::make('Jerarquía comercial')")
        ->toContain("TextEntry::make('hierarchy_diagram')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgency');

    expect($flowchartSource)
        ->toContain('tdg-hierarchy-flowchart-shell')
        ->toContain('buildHierarchyFlowContext')
        ->toContain('renderHierarchyFlowchart')
        ->toContain('hierarchyAgentNodePayload')
        ->toContain('appendAgentRowLevel')
        ->toContain('buildAgencyColumnGroupLevel')
        ->toContain('agentsForAgencyCode')
        ->toContain('tdg-hierarchy-flowchart')
        ->toContain('Equipo comercial · Agencia master')
        ->toContain('Nivel 1 · Agencia master')
        ->toContain('TUDRENCASA')
        ->toContain('structureSummaryForAgency')
        ->toContain('generalAgenciesUnderMasterCode')
        ->toContain('resolveAgentOwnerCodesForAgencyCode')
        ->toContain('applyAgentOwnerCodeScopeForAgency')
        ->toContain('resolveAgencyByOwnerCode')
        ->not->toContain('renderStructureCardsByAgency');
});

it('aplica estilos de contenedor de tabs alineados a telemedicina', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('TABS_CONTAINER')
        ->toContain('->persistTab()')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.25rem]');
});
