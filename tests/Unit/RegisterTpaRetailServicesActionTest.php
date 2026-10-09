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

function tpaRetailRegistrationSource(): string
{
    return file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/RetailServiceRegistration.php');
}

function tpaRetailPageSource(): string
{
    return file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Pages/RegisterRetailServices.php');
}

it('la acción ya no abre una modal: lleva a la página de registro RETAIL', function (): void {
    expect(tpaRetailActionSource())
        ->toContain("TelemedicinePatientResource::getUrl('retail'")
        ->not->toContain('->form(')
        ->not->toContain('->modalHeading(');
});

it('ofrece listas unificadas por categoría sin separar cubiertos y no cubiertos', function (): void {
    $page = tpaRetailPageSource();

    expect(array_keys(App\Support\Operations\RetailServiceRegistration::categories()))->toBe(['labs', 'studies', 'specialists', 'medications'])
        ->and($page)
        ->toContain("\$this->catalogSection('labs'")
        ->toContain("\$this->catalogSection('studies'")
        ->toContain("\$this->catalogSection('specialists'")
        ->toContain("Repeater::make('medications')")
        ->not->toContain("'labs_covered'")
        ->not->toContain("'labs_non_covered'");
});

it('lista el catálogo completo sin filtrar por cobertura', function (): void {
    expect(tpaRetailRegistrationSource())
        ->toContain('public static function catalogOptions(string $catalog): array')
        ->not->toContain('->where($typeColumn, $type)');
});

it('permite seleccionar uno o varios servicios principales sin catálogo de ítems', function (): void {
    $options = RegisterTpaRetailServicesAction::standaloneServiceOptions();

    expect(tpaRetailPageSource())
        ->toContain("CheckboxList::make('services')")
        ->toContain('RetailServiceRegistration::mainServices()');

    expect(App\Support\Operations\RetailServiceRegistration::mainServices())->toBe(RegisterTpaRetailServicesAction::standaloneSpecificServices());

    expect($options)
        ->toHaveCount(9)
        ->toHaveKey('TELEMEDICINA')
        ->toHaveKey('AMD (ASISTENCIA MEDICA DOMICILIARIA)')
        ->toHaveKey('TRASLADO EN AMBULANCIA')
        ->toHaveKey('CONSULTA ONLINE CON MEDICO ESPECIALISTA')
        ->toHaveKey('URGEN CARE')
        ->toHaveKey('APS')
        ->toHaveKey('INGRESO A CLINICA')
        ->toHaveKey('LECTURA DE RESULTADOS (LABORATORIO(S))')
        ->toHaveKey('LECTURA DE RESULTADOS (IMAGENOLOGIA)');
});

it('mapea cada categoría a su tipo de servicio de coordinación', function (): void {
    expect(collect(App\Support\Operations\RetailServiceRegistration::categories())->pluck('specific_service')->all())
        ->toBe(['LABORATORIOS', 'IMAGENOLOGIA', 'ESPECIALISTA', 'MEDICAMENTOS']);
});

it('crea el servicio de coordinación y enlaza los ítems reutilizando la gestión existente', function (): void {
    expect(tpaRetailRegistrationSource())
        ->toContain('OperationCoordinationService::query()->create')
        ->toContain("'operation_coordination_service_id' => \$coordination->id")
        ->toContain("'status' => 'PENDIENTE'")
        ->toContain("'servicie' => self::SERVICIE")
        ->toContain('DB::transaction');
});

it('crea un caso de telemedicina para engranar las relaciones', function (): void {
    expect(tpaRetailRegistrationSource())
        ->toContain('TelemedicineCaseFactory::createForPatient')
        ->toContain("const CASE_STATUS = 'RETAIL'")
        ->toContain("'telemedicine_case_id' => \$case->id");
});

it('redirige al cuadro de servicios médicos con el grupo del caso desplegado', function (): void {
    $source = tpaRetailActionSource();
    $listPage = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Pages/ListOperationCoordinationServices.php'
    );

    expect($source)
        ->toContain('medicalServicesIndexUrl')
        ->toContain("tab' => 'pendiente'")
        ->toContain('expand_group');

    expect(tpaRetailPageSource())->toContain('$this->redirect(RegisterTpaRetailServicesAction::medicalServicesIndexUrl($case))');

    expect($listPage)
        ->toContain('expandRequestedTableGroup')
        ->toContain('toggleCollapseGroup')
        ->toContain("request()->query('expand_group'");
});

it('resuelve la cobertura del ítem desde el catálogo al registrar', function (): void {
    expect(tpaRetailRegistrationSource())
        ->toContain("public const COVERED = 'CUBIERTO';")
        ->toContain("public const NOT_COVERED = 'NO CUBIERTO';")
        ->toContain('TelemedicineCoverageCatalog::laboratoryIsCovered')
        ->toContain('TelemedicineCoverageCatalog::studyIsCovered')
        ->toContain('TelemedicineCoverageCatalog::specialistIsCovered')
        ->toContain("'coverage' => self::suggestedCoverage(\$category, \$name)");
});

it('siembra un ítem gestionable no cubierto para cotizar servicios standalone', function (): void {
    expect(tpaRetailRegistrationSource())
        ->toContain('TelemedicinePatientSpecialty::query()->create')
        ->toContain("'type' => self::NOT_COVERED");

    expect(tpaRetailActionSource())
        ->toContain('ensureStandaloneManagementItem')
        ->toContain('isTpaRetailStandaloneCoordination')
        ->toContain("type' => self::NOT_COVERED");
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
