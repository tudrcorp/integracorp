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
        ->toContain('associatedItemSuffixActions')
        ->toContain('can_cancel');
});

it('muestra el proveedor y la CI/RIF de la gestión en cada ítem asociado', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('OperationServiceOrderProviderSummary::managementProvidersByClinicalLookup')
        ->toContain('OperationServiceOrderProviderSummary::providerForClinicalItem')
        ->toContain("'provider_name'")
        ->toContain("'provider_rif'")
        ->toContain('Proveedor: ')
        ->toContain('CI/RIF: ')
        ->toContain('CoordinationServiceAssociatedItemPricePreview::makeAction')
        ->toContain("'quote_id'")
        ->toContain("'order_id'");
});

it('permite editar ítems pendientes con nota obligatoria desde el tab de ítems asociados', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');

    expect($contents)
        ->toContain('CoordinationServiceItemEdit')
        ->toContain('CoordinationServiceItemEdit::makeEditAction($row)')
        ->toContain('CoordinationServiceItemEdit::itemIsEditable($status, $hasServiceOrder)')
        ->toContain("'can_edit'")
        ->toContain("CoordinationServiceItemEdit::itemHasServiceOrder(\$orderLinks, 'Medicamento', \$label)")
        ->toContain("CoordinationServiceItemEdit::itemHasServiceOrder(\$orderLinks, 'Laboratorio', \$label)")
        ->toContain("CoordinationServiceItemEdit::itemHasServiceOrder(\$orderLinks, 'Estudio', \$label)")
        ->toContain("CoordinationServiceItemEdit::itemHasServiceOrder(\$orderLinks, 'Especialista', \$label)");
});

it('permite enlazar directamente al tab de ítems asociados desde la tabla', function (): void {
    $root = dirname(__DIR__, 2);
    $infolist = file_get_contents($root.'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/OperationCoordinationServiceInfolist.php');
    $manager = file_get_contents($root.'/app/Support/Operations/CoordinationServiceItemsManager.php');
    $theme = file_get_contents($root.'/resources/css/filament/admin/theme.css');

    expect($infolist)
        ->toContain("public const ASSOCIATED_ITEMS_TAB = 'items-asociados'")
        ->toContain('->id(self::TABS_ID)')
        ->toContain('->persistTabInQueryString()')
        ->toContain('->id(self::ASSOCIATED_ITEMS_TAB)')
        ->toContain('->key(self::ASSOCIATED_ITEMS_TAB)')
        ->and($manager)
        ->toContain('public static function associatedItemsTabUrl(OperationCoordinationService $record): string')
        ->toContain("'tab' => OperationCoordinationServiceInfolist::ASSOCIATED_ITEMS_TAB,")
        ->toContain('fi-coordination-clinical-item__label-link')
        ->and($theme)
        ->toContain('.fi-coordination-clinical-item__label-link');
});

it('no anida anclas dentro de la celda de ítems clínicos de la tabla', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    $clinicalColumn = mb_substr(
        $table,
        (int) mb_strpos($table, "TextColumn::make('clinical_management_items')"),
        1200,
    );

    expect($clinicalColumn)->not->toContain('->url(');
});
