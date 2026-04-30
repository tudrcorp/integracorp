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
        ->and(file_get_contents($livewirePath))->toContain('class ClinicCoordinationDocumentsManager');

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
        ->and($contents)->toContain('Width::FiveExtraLarge')
        ->and($contents)->toContain('quote_price_preview')
        ->and($contents)->toContain('->steps([')
        ->and($contents)->toContain("Step::make('Servicio')")
        ->and($contents)->toContain('stickyModalFooter()')
        ->and($contents)->toContain('fi-modal-content]:overflow-y-auto')
        ->and($contents)->not->toContain("SelectColumn::make('type_service')");
});
