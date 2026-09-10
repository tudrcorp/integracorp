<?php

declare(strict_types=1);

use App\Filament\Agents\Resources\TelemedicineCases\TelemedicineCaseResource as AgentsCaseResource;
use App\Filament\Agents\Resources\TelemedicinePatients\TelemedicinePatientResource as AgentsPatientResource;
use App\Filament\General\Resources\TelemedicineCases\TelemedicineCaseResource as GeneralCaseResource;
use App\Filament\General\Resources\TelemedicinePatients\TelemedicinePatientResource as GeneralPatientResource;
use App\Filament\Master\Resources\TelemedicineCases\TelemedicineCaseResource as MasterCaseResource;
use App\Filament\Master\Resources\TelemedicinePatients\TelemedicinePatientResource as MasterPatientResource;
use App\Models\Permission;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Filament\CommercialNetworkAccess;
use App\Support\Filament\CommercialNetworkPermissionRegistry;
use App\Support\Filament\CommercialNetworkTelemedicineScope;
use Illuminate\Foundation\Testing\WithFaker;

uses(WithFaker::class);

function makeCommercialNetworkUser(array $attributes = [], array $permissionSlugs = []): User
{
    $user = new User;
    $user->forceFill(array_merge([
        'id' => fake()->unique()->randomNumber(5),
        'name' => 'Agente de prueba',
        'email' => 'agente@example.com',
        'status' => 'ACTIVO',
        'is_agent' => true,
        'is_agency' => false,
        'agent_id' => 41,
        'code_agency' => null,
        'agency_type' => null,
        'departament' => [],
    ], $attributes));

    $permissions = collect();

    foreach ($permissionSlugs as $slug) {
        $permissions->push(
            tap(new Permission, fn (Permission $permission) => $permission->forceFill([
                'id' => fake()->unique()->randomNumber(5),
                'name' => $slug,
                'slug' => $slug,
                'module' => CommercialNetworkPermissionRegistry::MODULE,
            ]))
        );
    }

    $user->setRelation('permissions', $permissions);

    return $user;
}

it('niega pacientes y casos si el usuario comercial no tiene el permiso asignado', function (): void {
    $user = makeCommercialNetworkUser();

    expect(CommercialNetworkAccess::isCommercialNetworkUser($user))->toBeTrue()
        ->and(CommercialNetworkAccess::canViewPatients($user))->toBeFalse()
        ->and(CommercialNetworkAccess::canViewCases($user))->toBeFalse();
});

it('permite ver pacientes solo con el permiso de red asignado', function (): void {
    $user = makeCommercialNetworkUser([], [CommercialNetworkPermissionRegistry::PATIENTS]);

    expect(CommercialNetworkAccess::canViewPatients($user))->toBeTrue()
        ->and(CommercialNetworkAccess::canViewCases($user))->toBeFalse();
});

it('permite ver casos solo con el permiso de gestion asignado', function (): void {
    $user = makeCommercialNetworkUser(
        ['is_agent' => false, 'is_agency' => true, 'agency_type' => 'MASTER', 'code_agency' => 'AGE-200', 'agent_id' => null],
        [CommercialNetworkPermissionRegistry::CASES],
    );

    expect(CommercialNetworkAccess::canViewCases($user))->toBeTrue()
        ->and(CommercialNetworkAccess::canViewPatients($user))->toBeFalse();
});

it('no concede el modulo de red a un analista interno', function (): void {
    $user = makeCommercialNetworkUser([
        'is_agent' => false,
        'is_agency' => false,
        'email' => 'analista@tudrencasa.com',
        'departament' => ['OPERACIONES'],
    ], [CommercialNetworkPermissionRegistry::PATIENTS, CommercialNetworkPermissionRegistry::CASES]);

    expect(CommercialNetworkAccess::isCommercialNetworkUser($user))->toBeFalse()
        ->and(CommercialNetworkAccess::canViewPatients($user))->toBeFalse();
});

it('el agente solo accede a pacientes de su agent_id y no a los de un subagente', function (): void {
    $agent = makeCommercialNetworkUser(
        ['agent_id' => 41],
        [CommercialNetworkPermissionRegistry::PATIENTS],
    );

    $ownAffiliation = (object) ['agent_id' => 41, 'code_agency' => 'AGE-1', 'owner_code' => 'TDG-100'];
    $subagentAffiliation = (object) ['agent_id' => 99, 'code_agency' => 'AGE-1', 'owner_code' => 'TDG-100'];

    expect(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($ownAffiliation, $agent))->toBeTrue()
        ->and(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($subagentAffiliation, $agent))->toBeFalse();
});

it('la agencia master usa owner_code y la general usa code_agency', function (): void {
    $master = makeCommercialNetworkUser([
        'is_agent' => false,
        'is_agency' => true,
        'agency_type' => 'MASTER',
        'agent_id' => null,
        'code_agency' => 'AGE-MASTER',
    ], [CommercialNetworkPermissionRegistry::PATIENTS]);

    $general = makeCommercialNetworkUser([
        'is_agent' => false,
        'is_agency' => true,
        'agency_type' => 'GENERAL',
        'agent_id' => null,
        'code_agency' => 'AGE-GEN',
    ], [CommercialNetworkPermissionRegistry::PATIENTS]);

    $underMaster = (object) ['agent_id' => 7, 'code_agency' => 'AGE-GEN', 'owner_code' => 'AGE-MASTER'];
    $otherNetwork = (object) ['agent_id' => 8, 'code_agency' => 'AGE-OTRA', 'owner_code' => 'AGE-OTRA'];

    expect(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($underMaster, $master))->toBeTrue()
        ->and(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($otherNetwork, $master))->toBeFalse()
        ->and(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($underMaster, $general))->toBeTrue()
        ->and(CommercialNetworkTelemedicineScope::affiliationBelongsToUser($otherNetwork, $general))->toBeFalse();
});

it('oculta casos de alta medica para la red comercial', function (): void {
    $user = makeCommercialNetworkUser([], [
        CommercialNetworkPermissionRegistry::PATIENTS,
        CommercialNetworkPermissionRegistry::CASES,
    ]);

    $discharged = new TelemedicineCase;
    $discharged->forceFill(['status' => 'ALTA MEDICA', 'telemedicine_patient_id' => 1]);

    $open = new TelemedicineCase;
    $open->forceFill(['status' => 'EN SEGUIMIENTO', 'telemedicine_patient_id' => 1]);

    $patient = new TelemedicinePatient;
    $patient->forceFill(['id' => 1]);
    $patient->setRelation('afilliation', (object) ['agent_id' => 41, 'code_agency' => 'AGE-1', 'owner_code' => 'TDG-100']);
    $patient->setRelation('afilliationCorporate', null);

    $discharged->setRelation('telemedicinePatient', $patient);
    $open->setRelation('telemedicinePatient', $patient);

    expect(CommercialNetworkTelemedicineScope::isDischarged($discharged))->toBeTrue()
        ->and(CommercialNetworkTelemedicineScope::userCanAccessCase($user, $discharged))->toBeFalse()
        ->and(CommercialNetworkTelemedicineScope::userCanAccessCase($user, $open))->toBeTrue();
});

it('autoriza el caso si el paciente llega como en el eager load de la tabla, con claves de afiliacion', function (): void {
    $user = makeCommercialNetworkUser(
        ['is_agent' => false, 'is_agency' => true, 'agency_type' => 'MASTER', 'code_agency' => 'TDG-102', 'agent_id' => null],
        [CommercialNetworkPermissionRegistry::CASES],
    );

    $patient = new TelemedicinePatient;
    $patient->forceFill([
        'id' => 1,
        'full_name' => 'Paciente Demo',
        'nro_identificacion' => '12345678',
        'code_affiliation' => 'AFF-1',
        'afilliation_id' => 10,
        'afilliation_corporate_id' => null,
    ]);
    $patient->setRelation('afilliation', (object) ['agent_id' => 7, 'code_agency' => 'AGE-GEN', 'owner_code' => 'TDG-102']);
    $patient->setRelation('afilliationCorporate', null);

    $case = new TelemedicineCase;
    $case->forceFill(['id' => 198, 'status' => 'EN SEGUIMIENTO', 'telemedicine_patient_id' => 1]);
    $case->setRelation('telemedicinePatient', $patient);

    expect(CommercialNetworkTelemedicineScope::patientCaseRelationEagerLoad())
        ->toBe('telemedicinePatient:id,full_name,nro_identificacion,code_affiliation,afilliation_id,afilliation_corporate_id')
        ->and(CommercialNetworkTelemedicineScope::userCanAccessCase($user, $case))->toBeTrue();
});

it('niega el caso si el paciente se cargo sin afiliaciones de la red', function (): void {
    $user = makeCommercialNetworkUser(
        ['is_agent' => false, 'is_agency' => true, 'agency_type' => 'MASTER', 'code_agency' => 'TDG-102', 'agent_id' => null],
        [CommercialNetworkPermissionRegistry::CASES],
    );

    $patient = new TelemedicinePatient;
    $patient->forceFill([
        'id' => 1,
        'full_name' => 'Paciente Demo',
        'nro_identificacion' => '12345678',
        'code_affiliation' => 'AFF-1',
    ]);
    $patient->setRelation('afilliation', null);
    $patient->setRelation('afilliationCorporate', null);

    $case = new TelemedicineCase;
    $case->forceFill(['id' => 198, 'status' => 'EN SEGUIMIENTO', 'telemedicine_patient_id' => 1]);
    $case->setRelation('telemedicinePatient', $patient);

    expect(CommercialNetworkTelemedicineScope::userCanAccessCase($user, $case))->toBeFalse();
});

it('los recursos comerciales son solo lectura y viven en los tres paneles', function (): void {
    $patient = new TelemedicinePatient;
    $patient->forceFill(['id' => 1]);
    $case = new TelemedicineCase;
    $case->forceFill(['id' => 1]);

    foreach ([AgentsPatientResource::class, MasterPatientResource::class, GeneralPatientResource::class] as $resource) {
        expect($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($patient))->toBeFalse()
            ->and($resource::canDelete($patient))->toBeFalse()
            ->and($resource::getNavigationGroup())->toBe('SEGUIMIENTO');
    }

    foreach ([AgentsCaseResource::class, MasterCaseResource::class, GeneralCaseResource::class] as $resource) {
        expect($resource::canCreate())->toBeFalse()
            ->and($resource::canEdit($case))->toBeFalse()
            ->and($resource::canDelete($case))->toBeFalse()
            ->and($resource::getNavigationGroup())->toBe('SEGUIMIENTO');
    }
});

it('la ui compartida no incluye historia clinica ni mutaciones operativas', function (): void {
    $patientInfolist = file_get_contents(__DIR__.'/../../app/Filament/Shared/CommercialTelemedicine/Schemas/CommercialTelemedicinePatientInfolist.php');
    $caseInfolist = file_get_contents(__DIR__.'/../../app/Filament/Shared/CommercialTelemedicine/Schemas/CommercialTelemedicineCaseInfolist.php');
    $consultationInfolist = file_get_contents(__DIR__.'/../../app/Filament/Shared/CommercialTelemedicine/Schemas/CommercialTelemedicineConsultationInfolist.php');
    $casesTable = file_get_contents(__DIR__.'/../../app/Filament/Shared/CommercialTelemedicine/Tables/CommercialTelemedicineCasesTable.php');
    $patientsTable = file_get_contents(__DIR__.'/../../app/Filament/Shared/CommercialTelemedicine/Tables/CommercialTelemedicinePatientsTable.php');
    $userForm = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    expect($patientInfolist)->not->toContain('Historia clínica')
        ->and($patientInfolist)->not->toContain('telemedicinePatientHistory')
        ->and($consultationInfolist)->not->toContain('reason_consultation')
        ->and($consultationInfolist)->not->toContain('diagnostic_impression')
        ->and($consultationInfolist)->not->toContain('current_illness_history')
        ->and($caseInfolist)->toContain('Solo consulta')
        ->and($casesTable)->toContain('excludeDischarged: true')
        ->and($casesTable)->toContain('CommercialNetworkTelemedicineScope::applyToCases')
        ->and($casesTable)->toContain('patientCaseRelationEagerLoad')
        ->and($patientsTable)->toContain('afilliationCorporate:id,code,agent_id,code_agency,owner_code,name_corporate')
        ->and($patientsTable)->not->toContain('name_corporative')
        ->and($userForm)->toContain('Permisos de red')
        ->and($userForm)->toContain('CommercialNetworkPermissionRegistry::FIELD_KEY');
});
