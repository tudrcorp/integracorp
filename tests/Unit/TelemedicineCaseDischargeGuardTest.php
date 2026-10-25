<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Support\Telemedicine\TelemedicineCaseDischargeGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::dropIfExists('telemedicine_patient_medications');
    Schema::dropIfExists('telemedicine_patient_labs');
    Schema::dropIfExists('telemedicine_patient_studies');
    Schema::dropIfExists('telemedicine_patient_specialties');
    Schema::dropIfExists('operation_coordination_services');

    Schema::create('operation_coordination_services', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    foreach (['telemedicine_patient_medications', 'telemedicine_patient_labs', 'telemedicine_patient_studies', 'telemedicine_patient_specialties'] as $tableName) {
        Schema::create($tableName, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('telemedicine_case_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }
});

it('sin servicios abiertos no hay aviso', function (): void {
    expect(TelemedicineCaseDischargeGuard::caseHasOpenAssociatedServices(99))->toBeFalse()
        ->and(TelemedicineCaseDischargeGuard::openServicesNotice(99))->toBeNull();
});

it('con una coordinación pendiente avisa, pero el alta sigue disponible', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 10,
        'status' => 'PENDIENTE',
    ]);

    expect(TelemedicineCaseDischargeGuard::openAssociatedServicesCount(10))->toBe(1)
        ->and(TelemedicineCaseDischargeGuard::openServicesNotice(10))
        ->toContain('1 servicio/ítem asociado en PENDIENTE o EN GESTIÓN')
        ->toContain('Puede dar el alta igual')
        ->toContain('no los cancela');
});

it('no cuenta servicios finalizados, caducados, cancelados ni reasignados', function (): void {
    foreach (['FINALIZADO', 'CADUCADA', 'REASIGNADO A TDG', 'CANCELADA'] as $status) {
        OperationCoordinationService::query()->create([
            'telemedicine_case_id' => 11,
            'status' => $status,
        ]);
    }

    expect(TelemedicineCaseDischargeGuard::openAssociatedServicesCount(11))->toBe(0)
        ->and(TelemedicineCaseDischargeGuard::openServicesNotice(11))->toBeNull();
});

it('cuenta coordinaciones e ítems clínicos abiertos juntos', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 14,
        'status' => 'EN GESTION',
    ]);
    TelemedicinePatientLab::query()->create([
        'telemedicine_case_id' => 14,
        'status' => 'EN GESTION',
    ]);
    TelemedicinePatientMedications::query()->create([
        'telemedicine_case_id' => 14,
        'status' => 'PENDIENTE',
    ]);

    expect(TelemedicineCaseDischargeGuard::openAssociatedServicesCount(14))->toBe(3)
        ->and(TelemedicineCaseDischargeGuard::openServicesNotice(14))->toContain('3 servicios/ítems asociados');
});

it('la nota de bitácora deja constancia de los servicios que quedaron abiertos', function (): void {
    expect(TelemedicineCaseDischargeGuard::dischargeBitacoraNote(2, 'DRA. QA'))
        ->toBe('ALTA MÉDICA CON SERVICIOS ABIERTOS · 2 servicios/ítems asociados quedaron en PENDIENTE o EN GESTIÓN; el alta no los canceló y Operaciones los seguirá gestionando. Alta dada por: DRA. QA.');
});

it('el alta ya no se bloquea: avisa en el formulario y anota en la bitácora al guardar', function (): void {
    $createPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');
    $guard = file_get_contents(dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseDischargeGuard.php');

    expect($createPage)
        ->not->toContain('assertCanBeDischarged')
        ->not->toContain('caseCanBeDischarged')
        ->not->toContain('Alta médica bloqueada')
        ->toContain('$openServicesCount = TelemedicineCaseDischargeGuard::openAssociatedServicesCount($caseId);')
        ->toContain('TelemedicineCaseDischargeGuard::dischargeBitacoraNote(')
        ->toContain("\$case->status = 'ALTA MEDICA';");

    expect($form)
        ->toContain('TelemedicineCaseDischargeGuard::openServicesNotice((int) $caseId)')
        ->not->toContain("\$set('feedbackOne', false)")
        ->not->toContain('Alta médica no disponible');

    expect($guard)
        ->not->toContain('function assertCanBeDischarged')
        ->not->toContain('function caseCanBeDischarged');
});
