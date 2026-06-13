<?php

declare(strict_types=1);

it('OperationCoordinationServicesTable resalta servicios con badge info/danger según derivado crítico', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;')
        ->and($contents)->toContain("TextColumn::make('servicie')")
        ->and($contents)->toContain("TextColumn::make('specific_service')");

    expect(substr_count($contents, 'TelemedicineDerivedServiceBadge::driftNameIsCritical'))->toBeGreaterThanOrEqual(4);
});

it('OperationCoordinationServicesTable define la acción modal de doctor TDG para traslado en ambulancia', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('selectTdgDoctorForAmbulanceFollowUp')")
        ->and($contents)->toContain('Seleccionar Doctor TDG para seguimiento de caso')
        ->and($contents)->toContain('TelemedicineDerivedServiceBadge::specificServiceIsTrasladoEnAmbulancia')
        ->and($contents)->toContain('TelemedicineDoctor::query()')
        ->and($contents)->toContain("'managed_by', 'TDG'")
        ->and($contents)->toContain('TelemedicineCase::query()')
        ->and($contents)->toContain('telemedicine_case_id')
        ->and($contents)->toContain('Width::TwoExtraLarge')
        ->and($contents)->toContain('FilamentIosButton::extraClassForFilamentColor');
});

it('OperationCoordinationServicesTable define acción de documentos de ingreso y egreso a clínica', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    $livewirePath = dirname(__DIR__, 2).'/app/Livewire/Operations/ClinicCoordinationDocumentsManager.php';

    expect($contents)->toBeString()
        ->and($contents)->toContain('clinicCoordinationDocuments')
        ->and($contents)->toContain('filament.operations.coordination.clinic-documents-modal')
        ->and(file_exists($livewirePath))->toBeTrue()
        ->and(file_get_contents($livewirePath))->toContain('class OperationCoordinationServicesTableServiceColumnsTest');

    $uploaderPartial = dirname(__DIR__, 2).'/resources/views/livewire/operations/partials/clinic-document-uploader-zone.blade.php';
    $partialContents = file_get_contents($uploaderPartial);
    expect($partialContents)->toContain('assignFilesFromDrop')
        ->and($partialContents)->toContain('wire:model="ingresoUploads"')
        ->and($partialContents)->toContain('removeIngresoUpload');
});

it('OperationCoordinationServicesTable define acción modal de negociación y precios', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("Action::make('editNegotiationAndPricing')")
        ->and($contents)->toContain('Negociación, cotización y facturación')
        ->and($contents)->toContain('Width::SevenExtraLarge')
        ->and($contents)->toContain('quote_price_preview')
        ->and($contents)->toContain('->steps([')
        ->and($contents)->toContain("Step::make('Servicio')")
        ->and($contents)->toContain('stickyModalFooter()')
        ->and($contents)->toContain('fi-modal-content]:overflow-y-auto')
        ->and($contents)->not->toContain("SelectColumn::make('type_service')")
        ->and($contents)->not->toContain('RecordActionsPosition::');
});

it('OperationCoordinationServicesTable enlaza a la página de gestión de ítems', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php');

    expect($table)
        ->toContain("Action::make('manage_service_items')")
        ->toContain('ManageCoordinationServiceItems::getUrl')
        ->toContain('CoordinationServiceItemsManager::manageServiceActionIsDisabled')
        ->not->toContain('modalHeading(\'Gestionar ítems del servicio\')');

    expect($form)
        ->toContain('fi-coordination-manage-items-wizard')
        ->toContain('manage_service_items_context')
        ->toContain("Step::make('Selección de ítems')")
        ->toContain("Step::make('Orden de servicio')")
        ->toContain("Step::make('Cotización')")
        ->toContain('CheckboxList::make(\'managed_service_item_keys\')')
        ->toContain('disableOptionWhen')
        ->toContain('manageServiceItemOptions')
        ->toContain('isManagementItemKeySelectable')
        ->toContain('CoordinationServiceItemsManager::nonCoveredSelectedManagementItemKeys');
});

it('isManagementItemSelectable bloquea items en gestion o finalizados', function (): void {
    expect(\App\Support\Operations\CoordinationServiceItemsManager::isManagementItemSelectable('PENDIENTE'))->toBeTrue()
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::isManagementItemSelectable('EN GESTION'))->toBeFalse()
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::isManagementItemSelectable('FINALIZADO'))->toBeFalse();
});

it('OperationCoordinationServicesTable muestra ítems clínicos en la vista principal', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    expect($table)
        ->toContain("TextColumn::make('clinical_management_items')")
        ->toContain('renderCoordinationClinicalItemsCompactList')
        ->toContain('->getStateUsing(')
        ->toContain('telemedicinePatientLabs')
        ->toContain('telemedicinePatientMedications.operationInventory');

    expect($manager)
        ->toContain('renderCoordinationClinicalItemsCompactList')
        ->toContain('fi-coordination-clinical-items-compact')
        ->toContain('clinicalItemStatusAbbrev')
        ->toContain('clinicalItemsCompactHeaderSummary')
        ->toContain('serviceOrderLinksByClinicalItemKey')
        ->toContain('fi-coordination-clinical-item-order-link')
        ->toContain('fi-coordination-clinical-item-quote-link')
        ->toContain('quoteLinksByClinicalItemKey')
        ->toContain('fi-coordination-clinical-item')
        ->toContain('fi-coordination-clinical-item__order')
        ->toContain('fi-coordination-clinical-item__manage')
        ->toContain('fi-coordination-clinical-item-manage-link')
        ->toContain('clinicalItemPendingManageLinkHtml')
        ->toContain('ManageCoordinationServiceItems::getUrl')
        ->toContain('fi-coordination-clinical-item__meta')
        ->toContain('fi-coordination-clinical-item__lead')
        ->toContain('fi-coordination-clinical-item__trail')
        ->toContain('fi-coordination-clinical-items-list')
        ->toContain('OperationServiceOrderResource::getUrl');

    expect($table)
        ->toContain('fi-coordination-clinical-items-cell')
        ->toContain('fi-coordination-clinical-items-header')
        ->toContain('min-width: 22rem')
        ->toContain('max-width: 30rem');
});

it('clinicalItemPendingManageLinkHtml enlaza a gestionar servicio solo en items pendientes', function (): void {
    $item = ['selectable' => true];

    $html = \App\Support\Operations\CoordinationServiceItemsManager::clinicalItemPendingManageLinkHtml(
        'PENDIENTE',
        $item,
        '/operations/coordination-services/1/manage-items',
        true,
    );

    expect($html)
        ->toContain('fi-coordination-clinical-item-manage-link')
        ->toContain('Gestionar servicio')
        ->toContain('/operations/coordination-services/1/manage-items')
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemPendingManageLinkHtml(
            'EN GESTION',
            $item,
            '/operations/coordination-services/1/manage-items',
            true,
        ))->toBe('');
});

it('clinicalItemStatusAbbrev resume estatus para la vista compacta', function (): void {
    expect(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemStatusAbbrev('PENDIENTE'))->toBe('PEND')
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemStatusAbbrev('CADUCADA'))->toBe('CAD')
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemCoverageAbbrev(false))->toBe('NC');
});

it('clinicalItemsCompactHeaderSummary cuenta estatus activos en el encabezado', function (): void {
    $items = collect([
        ['status' => 'PENDIENTE'],
        ['status' => 'PENDIENTE'],
        ['status' => 'EN GESTION'],
    ]);

    $summary = \App\Support\Operations\CoordinationServiceItemsManager::clinicalItemsCompactHeaderSummary($items);

    expect($summary)
        ->toContain('2 PENDIENTES')
        ->toContain('1 EN GESTIÓN');
});

it('clinicalItemsCompactHeaderSummary oculta encabezado cuando todos los items estan cerrados', function (): void {
    $items = collect([
        ['status' => 'FINALIZADO'],
        ['status' => 'CADUCADA'],
    ]);

    expect(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemsCompactHeaderSummary($items))->toBeNull();
});

it('clinicalItemsCompactHeaderSummary incluye cancelados con color en mezcla activa', function (): void {
    $items = collect([
        ['status' => 'EN GESTION'],
        ['status' => 'CANCELADA'],
    ]);

    $summary = \App\Support\Operations\CoordinationServiceItemsManager::clinicalItemsCompactHeaderSummary($items);

    expect($summary)
        ->toContain('1 EN GESTIÓN')
        ->toContain('1 CANCELADO')
        ->toContain('text-red-700');
});

it('renderClinicalItemsStatusCounterPills muestra badges con colores por estatus', function (): void {
    $items = collect([
        ['status' => 'EN GESTION'],
        ['status' => 'CANCELADA'],
    ]);

    $html = \App\Support\Operations\CoordinationServiceItemsManager::renderClinicalItemsStatusCounterPills($items);

    expect($html)
        ->toContain('1 EN GESTIÓN')
        ->toContain('1 CANCELADO')
        ->toContain('#ffc107')
        ->toContain('#ff3b30');
});

it('effectiveClinicalItemDisplayStatus refleja orden cancelada en items en gestion', function (): void {
    $item = ['status' => 'EN GESTION'];
    $orderLink = ['status' => 'CANCELADA'];

    expect(\App\Support\Operations\CoordinationServiceItemsManager::effectiveClinicalItemDisplayStatus($item, $orderLink))
        ->toBe('CANCELADA');
});

it('effectiveDisplayStatusForClinicalItem usa vinculo de orden en infolist', function (): void {
    $orderLinks = [
        'LABORATORIOS|CREATININA' => ['id' => 20, 'order_number' => 'ORD-0020', 'status' => 'CANCELADA', 'url' => '/orders/20'],
    ];

    $record = new \App\Models\OperationCoordinationService(['id' => 1]);

    expect(\App\Support\Operations\CoordinationServiceItemsManager::effectiveDisplayStatusForClinicalItem(
        $record,
        'Laboratorio',
        'CREATININA',
        'EN GESTION',
        $orderLinks,
    ))->toBe('CANCELADA');
});

it('clinicalItemServiceOrderKey vincula categoria clinica con tipo de servicio', function (): void {
    expect(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemServiceOrderKey('Laboratorio', 'Creatinina'))
        ->toBe('LABORATORIOS|CREATININA');
});

it('OperationCoordinationServicesTable pinta fila gris cuando todos los items asociados estan cerrados', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');

    expect($table)
        ->toContain('private static function recordRowClasses')
        ->toContain('allAssociatedItemsAreClosed')
        ->toContain('coordinationClosedRowClasses');

    expect($manager)
        ->toContain('function allAssociatedItemsAreClosed')
        ->toContain('clinicalItemsWithEffectiveDisplayStatus($record)')
        ->toContain("'FINALIZADO'")
        ->toContain("'CANCELADA'")
        ->toContain("'CADUCADA'");
});

it('effectiveClinicalItemDisplayStatus refleja orden finalizada en items en gestion', function (): void {
    $item = ['status' => 'EN GESTION'];
    $orderLink = ['status' => 'FINALIZADO'];

    expect(\App\Support\Operations\CoordinationServiceItemsManager::effectiveClinicalItemDisplayStatus($item, $orderLink))
        ->toBe('FINALIZADO')
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemsCollectionAllClosed(collect([
            ['status' => 'FINALIZADO'],
            ['status' => 'FINALIZADO'],
            ['status' => 'FINALIZADO'],
        ])))->toBeTrue()
        ->and(\App\Support\Operations\CoordinationServiceItemsManager::clinicalItemsCollectionAllClosed(collect([
            ['status' => 'EN GESTION'],
            ['status' => 'FINALIZADO'],
        ])))->toBeFalse();
});

it('detecta cuando todos los items asociados de coordinacion estan cerrados', function (): void {
    $closedStatuses = \App\Support\Operations\CoordinationServiceItemsManager::closedItemStatuses();

    expect($closedStatuses)->toContain('FINALIZADO', 'CANCELADO', 'CANCELADA', 'CADUCADA');
});

it('OperationCoordinationServicesTable permite agrupar por código del caso', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use Filament\\Tables\\Grouping\\Group;')
        ->toContain("Group::make('telemedicineCase.code')")
        ->toContain("->label('Código del caso')")
        ->toContain('Sin caso vinculado')
        ->toContain('->orderQueryUsing(')
        ->toContain("'telemedicineCase', 'created_at'")
        ->toContain("'desc'")
        ->toContain('->collapsedGroupsByDefault()');
});

it('OperationCoordinationServicesTable muestra código de caso TM con badge y enlace a vista', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain("TextColumn::make('telemedicineCase.code')")
        ->toContain('TelemedicineCaseResource::getUrl')
        ->toContain('healthicons-f-health-literacy')
        ->toContain("'telemedicineCase'");
});
