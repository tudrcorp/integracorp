<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\Agencies\Schemas\AgencyInfolist;
use Filament\Schemas\Schema;

it('configura el infolist de agencia en administracion sin error', function (): void {
    $schema = Schema::make();
    $configured = AgencyInfolist::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('usa el infolist compartido de estructura comercial para agencias en administracion', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Schemas/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('SharedAgencyInfolist::configure($schema)');
});

it('incluye diagrama de jerarquia comercial en administracion via infolist compartido', function (): void {
    $sharedPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $flowchartPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/CommercialHierarchyFlowchart.php';
    $sharedSource = file_get_contents($sharedPath);
    $flowchartSource = file_get_contents($flowchartPath);

    expect($sharedSource)
        ->toContain("Tab::make('Jerarquía')")
        ->toContain('CommercialHierarchyFlowchart::renderForAgency');

    expect($flowchartSource)->toContain('renderForAgency')->toContain('tdg-hierarchy-flowchart-shell');
});

it('hereda estilos de tabs de telemedicina via infolist compartido de agencias', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php';
    $source = file_get_contents($path);

    expect($source)->toContain('TABS_CONTAINER')->toContain('->persistTab()');
});
