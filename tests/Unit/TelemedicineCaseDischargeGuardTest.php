<?php

declare(strict_types=1);

use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Support\Telemedicine\TelemedicineCaseDischargeGuard;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

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

it('permite alta medica cuando no hay servicios asociados', function (): void {
    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(99))->toBeTrue()
        ->and(TelemedicineCaseDischargeGuard::caseHasOpenAssociatedServices(99))->toBeFalse();
});

it('bloquea alta medica con coordinacion pendiente o en gestion', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 10,
        'status' => 'PENDIENTE',
    ]);

    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(10))->toBeFalse()
        ->and(TelemedicineCaseDischargeGuard::blockingMessage(10))
        ->toContain('PENDIENTE o EN GESTIÓN')
        ->toContain('finalizados o caducados');
});

it('permite alta medica cuando los servicios estan finalizados o caducados', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 11,
        'status' => 'FINALIZADO',
    ]);
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 11,
        'status' => 'CADUCADA',
    ]);

    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(11))->toBeTrue();
});

it('permite alta medica con coordinacion reasignada a TDG o cancelada', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 12,
        'status' => 'REASIGNADO A TDG',
    ]);
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 12,
        'status' => 'CANCELADA',
    ]);

    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(12))->toBeTrue();
});

it('bloquea alta medica con item clinico en gestion', function (): void {
    TelemedicinePatientLab::query()->create([
        'telemedicine_case_id' => 13,
        'status' => 'EN GESTION',
    ]);

    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(13))->toBeFalse()
        ->and(TelemedicineCaseDischargeGuard::openAssociatedServicesCount(13))->toBe(1);
});

it('bloquea alta medica con medicamento pendiente aunque la coordinacion este finalizada', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 14,
        'status' => 'FINALIZADO',
    ]);
    TelemedicinePatientMedications::query()->create([
        'telemedicine_case_id' => 14,
        'status' => 'PENDIENTE',
    ]);

    expect(TelemedicineCaseDischargeGuard::caseCanBeDischarged(14))->toBeFalse();
});

it('assertCanBeDischarged lanza ValidationException cuando hay servicios abiertos', function (): void {
    OperationCoordinationService::query()->create([
        'telemedicine_case_id' => 15,
        'status' => 'EN GESTION',
    ]);

    expect(fn () => TelemedicineCaseDischargeGuard::assertCanBeDischarged(15))
        ->toThrow(ValidationException::class);
});

it('CreateTelemedicineConsultationPatient valida alta medica con el guard', function (): void {
    $createPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php');

    expect($createPage)
        ->toContain('TelemedicineCaseDischargeGuard::assertCanBeDischarged')
        ->toContain('TelemedicineCaseDischargeGuard::caseCanBeDischarged')
        ->toContain('Alta médica bloqueada');

    expect($form)
        ->toContain('TelemedicineCaseDischargeGuard')
        ->toContain('Alta médica no disponible')
        ->toContain('finalizados o caducados');
});
