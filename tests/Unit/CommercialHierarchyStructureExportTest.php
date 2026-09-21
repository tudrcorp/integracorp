<?php

declare(strict_types=1);

it('define exportacion excel de estructura comercial con columnas de listado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/CommercialStructure/CommercialHierarchyStructureExportService.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain('function headers(): array')
        ->toContain("'Tipo'")
        ->toContain("'Código'")
        ->toContain("'Nombre'")
        ->toContain("'Estatus'")
        ->toContain("'Correo'")
        ->toContain("'Teléfono'")
        ->toContain("'Cadena jerárquica'")
        ->toContain('function toXlsxForAgency')
        ->toContain('function toXlsxForAgent')
        ->toContain('function rowsForAgency')
        ->toContain('function rowsForAgent')
        ->toContain('OpenSpout\\Writer\\XLSX\\Writer')
        ->toContain('generalAgenciesUnderMaster')
        ->toContain('agentsUnderGeneralAgencyQuery')
        ->toContain('agentsUnderAgentQuery');
});

it('expone accion compartida de descarga de estructura comercial', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/Actions/DownloadHierarchyStructureAction.php';
    $source = file_get_contents($path);

    expect($source)
        ->toContain("->label('Descargar estructura (Excel)')")
        ->toContain('function forAgency')
        ->toContain('function forAgent')
        ->toContain('CommercialHierarchyStructureExportService::toXlsxForAgency')
        ->toContain('CommercialHierarchyStructureExportService::toXlsxForAgent');
});

it('conecta la descarga excel en ver jerarquia de master general y agents', function (): void {
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/Concerns/DownloadsCommercialHierarchyStructure.php';
    $masterPath = dirname(__DIR__, 2).'/app/Filament/Master/Pages/ViewMyHierarchy.php';
    $generalPath = dirname(__DIR__, 2).'/app/Filament/General/Pages/ViewMyHierarchy.php';
    $agentsPath = dirname(__DIR__, 2).'/app/Filament/Agents/Pages/ViewMyHierarchy.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/shared/pages/view-my-hierarchy.blade.php';

    expect(file_get_contents($traitPath))
        ->toContain('downloadHierarchyStructureAction')
        ->toContain('DownloadHierarchyStructureAction::forAgency')
        ->toContain('DownloadHierarchyStructureAction::forAgent');

    expect(file_get_contents($masterPath))
        ->toContain('DownloadsCommercialHierarchyStructure');

    expect(file_get_contents($generalPath))
        ->toContain('DownloadsCommercialHierarchyStructure');

    expect(file_get_contents($agentsPath))
        ->toContain('DownloadsCommercialHierarchyStructure')
        ->toContain('hierarchyExportSubjectIsAgent(): bool');

    expect(file_get_contents($viewPath))
        ->toContain('Descargar estructura (Excel)');
});

it('replica la descarga excel en fichas de agencias y agentes', function (): void {
    $files = [
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php',
        dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ViewAgency.php',
        dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/ViewAgent.php',
        dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agencies/Pages/ViewAgency.php',
        dirname(__DIR__, 2).'/app/Filament/Master/Resources/Agents/Pages/ViewAgent.php',
        dirname(__DIR__, 2).'/app/Filament/General/Resources/Agencies/Pages/ViewAgency.php',
        dirname(__DIR__, 2).'/app/Filament/General/Resources/Agents/Pages/ViewAgent.php',
        dirname(__DIR__, 2).'/app/Filament/Agents/Resources/Agents/Pages/ViewAgent.php',
    ];

    foreach ($files as $file) {
        expect(file_get_contents($file))->toContain('DownloadHierarchyStructureAction::');
    }

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgencyInfolist.php'))
        ->toContain('Descargar estructura (Excel)');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/AgentInfolist.php'))
        ->toContain('Descargar estructura (Excel)');
});
