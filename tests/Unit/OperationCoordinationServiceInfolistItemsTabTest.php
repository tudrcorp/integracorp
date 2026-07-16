<?php

declare(strict_types=1);

it('agrega un tab de ítems asociados en el infolist de coordinación', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain("Tab::make('Ítems asociados')")
        ->toContain('telemedicinePatientMedicationsSummary')
        ->toContain('telemedicinePatientLabsSummary')
        ->toContain('telemedicinePatientStudiesSummary')
        ->toContain('telemedicinePatientSpecialtiesSummary')
        ->toContain('Indicación: ')
        ->toContain('renderAssociatedItemCard')
        ->toContain('->html()');
});

it('muestra solo bloques con ítems asociados en el tab de ítems', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('hasAnyAssociatedItems')
        ->toContain('hasMedications')
        ->toContain('hasLaboratories')
        ->toContain('hasStudies')
        ->toContain('hasSpecialties')
        ->toContain('self::hasAnyAssociatedItems($record)')
        ->toContain('self::hasMedications($record)');
});

it('separa el servicio TPA/RETAIL standalone de consultas con especialista en el infolist', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain("Fieldset::make('Servicio TPA/RETAIL')")
        ->toContain('isTpaRetailStandaloneCoordination')
        ->toContain("'Servicio: '")
        ->toContain('telemedicinePatientStandaloneServiceSummary');
});

it('mantiene las secciones del infolist siempre abiertas', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->not->toContain('->collapsed(true)')
        ->not->toContain('->collapsible()');
});

it('aplica colores de estatus definidos para los ítems asociados', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain("'FINALIZADO' => 'border-emerald-500/40")
        ->toContain("'PENDIENTE' => 'border-rose-500/40")
        ->toContain("'EN GESTION' => 'border-orange-500/45")
        ->toContain("'CANCELADO', 'CANCELADA', 'CADUCADA'")
        ->toContain('rounded-full border px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide');
});

it('refleja estatus efectivo de orden en ítems asociados del infolist', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem')
        ->toContain('CoordinationServiceItemsManager::serviceOrderLinksByClinicalItemKey');
});

it('muestra cobertura cubierto o no cubierto en los ítems asociados', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('TelemedicineMedicationCoverage::isCovered')
        ->toContain('catalogItemCoverageValue')
        ->toContain('coverageLabel')
        ->toContain('coverageBadgeClasses')
        ->toContain("'coverage' =>")
        ->toContain("'Cubierto'")
        ->toContain("'No cubierto'");
});

it('oculta relation managers en la vista de coordinación', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ViewOperationCoordinationService.php');

    expect($contents)
        ->toContain('public function getRelationManagers(): array')
        ->toContain('return [];');
});

it('aplica estilos visuales tipo AgentForm master en el infolist de coordinación', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('private const TABS_CONTAINER')
        ->toContain('private const SECTION_CARD')
        ->toContain("Tabs::make('operationCoordinationServiceInfolistTabs')")
        ->toContain("'class' => self::TABS_CONTAINER")
        ->toContain("'class' => self::SECTION_CARD");
});

it('permite cancelar la gestión de ítems con observación obligatoria en bitácora', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('CoordinationServiceItemCancellation')
        ->toContain('cancelAssociatedItemSuffixActions')
        ->toContain('can_cancel');
});
