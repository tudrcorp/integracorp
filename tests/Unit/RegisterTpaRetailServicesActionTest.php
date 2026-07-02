<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use Filament\Actions\Action;

function tpaRetailActionSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/RegisterTpaRetailServicesAction.php'
    );
}

it('expone una acción Filament con nombre propio', function (): void {
    $action = RegisterTpaRetailServicesAction::make();

    expect($action)->toBeInstanceOf(Action::class)
        ->and($action->getName())->toBe('register_tpa_retail_services');
});

it('ofrece selección de cubiertos y no cubiertos para labs, estudios y especialistas', function (): void {
    $source = tpaRetailActionSource();

    expect($source)
        ->toContain("'labs_covered'")
        ->toContain("'labs_non_covered'")
        ->toContain("'studies_covered'")
        ->toContain("'studies_non_covered'")
        ->toContain("'specialists_covered'")
        ->toContain("'specialists_non_covered'");
});

it('mapea cada categoría a su tipo de servicio de coordinación', function (): void {
    $source = tpaRetailActionSource();

    expect($source)
        ->toContain("'specific_service' => 'LABORATORIOS'")
        ->toContain("'specific_service' => 'IMAGENOLOGIA'")
        ->toContain("'specific_service' => 'ESPECIALISTA'");
});

it('crea el servicio de coordinación y enlaza los ítems reutilizando la gestión existente', function (): void {
    $source = tpaRetailActionSource();

    expect($source)
        ->toContain('OperationCoordinationService::create')
        ->toContain("'operation_coordination_service_id' => \$service->id")
        ->toContain("'status' => 'PENDIENTE'")
        ->toContain('DB::transaction');
});

it('crea un caso de telemedicina para engranar las relaciones', function (): void {
    $source = tpaRetailActionSource();

    expect($source)
        ->toContain('TelemedicineCase::create')
        ->toContain('UtilsController::generateCaseCode()')
        ->toContain("const CASE_STATUS = 'TPA/RETAIL'")
        ->toContain("'telemedicine_case_id' => \$case->id");
});

it('marca la cobertura del ítem con CUBIERTO o NO CUBIERTO desde el catálogo', function (): void {
    $source = tpaRetailActionSource();

    expect($source)
        ->toContain("private const COVERED = 'CUBIERTO';")
        ->toContain("private const NOT_COVERED = 'NO CUBIERTO';")
        ->toContain("->where('type', \$type)");
});

it('se monta en la ficha del paciente de Operaciones', function (): void {
    $page = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/ViewTelemedicinePatient.php'
    );

    expect($page)->toContain('RegisterTpaRetailServicesAction::make()');
});

it('hace nullable las referencias clínicas de los ítems para servicios sin consulta', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_06_30_093921_make_telemedicine_item_clinical_references_nullable.php'
    );

    expect($migration)
        ->toContain("'telemedicine_patient_labs'")
        ->toContain("'telemedicine_patient_studies'")
        ->toContain("'telemedicine_patient_specialties'")
        ->toContain("'telemedicine_case_id'")
        ->toContain("'telemedicine_doctor_id'")
        ->toContain("'telemedicine_consultation_patient_id'")
        ->toContain('->nullable()->change()');
});

it('hace nullable el médico del caso para casos TPA/RETAIL sin doctor', function (): void {
    $migration = file_get_contents(
        dirname(__DIR__, 2).'/database/migrations/2026_06_30_095156_make_telemedicine_cases_doctor_nullable.php'
    );

    expect($migration)
        ->toContain("'telemedicine_cases'")
        ->toContain("integer('telemedicine_doctor_id')->nullable()->change()");
});

it('la tabla de casos tolera casos sin médico, sin prioridad y estatus TPA/RETAIL', function (): void {
    $table = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php'
    );

    expect($table)
        ->toContain('$record->telemedicineDoctor?->full_name')
        ->toContain("'TPA/RETAIL' => 'info'")
        ->toContain("'TPA/RETAIL' => 'heroicon-s-clipboard-document-check'")
        ->toContain('default => \'gray\',')
        ->toContain('->color(fn (?string $state): string => TelemedicinePriorityFilamentBadge::color((string) $state))');
});
