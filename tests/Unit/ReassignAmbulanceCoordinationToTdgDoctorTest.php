<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use App\Models\ObservationCase;
use App\Models\OperationAccountsReceivable;
use App\Models\OperationCoordinationService;
use App\Models\OperationServiceOrder;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Operations\ReassignAmbulanceCoordinationToTdgDoctor;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

function createAmbulanceReassignmentTables(): void
{
    Schema::dropIfExists('operation_accounts_receivables');
    Schema::dropIfExists('observation_cases');
    Schema::dropIfExists('operation_service_orders');
    Schema::dropIfExists('telemedicine_patient_medications');
    Schema::dropIfExists('telemedicine_patient_labs');
    Schema::dropIfExists('telemedicine_patient_studies');
    Schema::dropIfExists('telemedicine_patient_specialties');
    Schema::dropIfExists('operation_coordination_services');
    Schema::dropIfExists('telemedicine_consultation_patients');
    Schema::dropIfExists('telemedicine_cases');
    Schema::dropIfExists('telemedicine_patients');
    Schema::dropIfExists('telemedicine_doctors');

    Schema::create('telemedicine_doctors', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('specialty')->nullable();
        $table->string('status')->nullable();
        $table->string('managed_by')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_patients', function (Blueprint $table): void {
        $table->id();
        $table->string('full_name')->nullable();
        $table->string('age')->nullable();
        $table->string('sex')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
        $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
        $table->string('patient_name')->nullable();
        $table->string('patient_age')->nullable();
        $table->string('patient_sex')->nullable();
        $table->string('status')->nullable();
        $table->string('code')->nullable();
        $table->string('managed_by')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_consultation_patients', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    foreach (['telemedicine_patient_medications', 'telemedicine_patient_labs', 'telemedicine_patient_studies', 'telemedicine_patient_specialties'] as $clinicalTable) {
        Schema::create($clinicalTable, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });
    }

    Schema::create('operation_coordination_services', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->unsignedBigInteger('telemedicine_doctor_id')->nullable();
        $table->string('patient')->nullable();
        $table->string('reference_number')->nullable();
        $table->string('specific_service')->nullable();
        $table->string('status')->nullable();
        $table->string('managed_by')->nullable();
        $table->unsignedBigInteger('supplier_id')->nullable();
        $table->text('observations')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

    Schema::create('operation_service_orders', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
        $table->string('managed_by')->nullable();
        $table->timestamp('approved_at')->nullable();
        $table->timestamps();
    });

    Schema::create('observation_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->text('description')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('operation_accounts_receivables', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('operation_coordination_service_id')->nullable();
        $table->unsignedBigInteger('telemedicine_patient_id')->nullable();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->unsignedBigInteger('operation_quote_generator_id')->nullable();
        $table->unsignedBigInteger('operation_service_order_id')->nullable();
        $table->string('quote_number')->nullable();
        $table->string('service_order_number')->nullable();
        $table->decimal('quote_amount_usd', 12, 2)->nullable();
        $table->decimal('quote_amount_ves', 14, 2)->nullable();
        $table->decimal('bcv_rate', 12, 4)->nullable();
        $table->text('reassignment_reason')->nullable();
        $table->unsignedBigInteger('reassignment_supplier_id')->nullable();
        $table->string('reassignment_supplier_name')->nullable();
        $table->unsignedBigInteger('reassigned_by_user_id')->nullable();
        $table->string('reassigned_by_analyst_name')->nullable();
        $table->string('status')->nullable();
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });
}

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    createAmbulanceReassignmentTables();
});

it('solo es elegible traslado en ambulancia no cancelado', function (): void {
    $eligible = new OperationCoordinationService([
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
    ]);

    $cancelled = new OperationCoordinationService([
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'CANCELADA',
    ]);

    $other = new OperationCoordinationService([
        'specific_service' => 'INGRESO A CLINICA',
        'status' => 'PENDIENTE',
    ]);

    expect(ReassignAmbulanceCoordinationToTdgDoctor::isEligible($eligible))->toBeTrue()
        ->and(ReassignAmbulanceCoordinationToTdgDoctor::isEligible($cancelled))->toBeFalse()
        ->and(ReassignAmbulanceCoordinationToTdgDoctor::isEligible($other))->toBeFalse();
});

it('construye bitacora con prefijo medico y motivo', function (): void {
    $description = ReassignAmbulanceCoordinationToTdgDoctor::buildBitacoraDescription(
        'Dr. Pérez',
        'Seguimiento clínico TDG por traslado.',
    );

    expect($description)
        ->toContain(ReassignAmbulanceCoordinationToTdgDoctor::OBSERVATION_PREFIX)
        ->toContain('Médico TDG: Dr. Pérez')
        ->toContain('Motivo: Seguimiento clínico TDG por traslado.');
});

it('reasigna coordinacion de ambulancia a medico TDG con bitacora y cxc', function (): void {
    $doctor = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. TDG Ambulancia',
        'specialty' => 'Medicina General',
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
    ]);

    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Prueba',
        'age' => '40',
        'sex' => 'F',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'telemedicine_doctor_id' => null,
        'patient_name' => 'Paciente Prueba',
        'status' => 'ASIGNADO',
        'code' => 'TM-AMB-1',
        'managed_by' => 'ATENMEDI',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'telemedicine_case_id' => $case->id,
        'telemedicine_doctor_id' => null,
        'patient' => 'Paciente Prueba',
        'reference_number' => 'REF-AMB-1',
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
        'supplier_id' => null,
    ]);

    OperationServiceOrder::query()->create([
        'operation_coordination_service_id' => $coordination->id,
        'managed_by' => 'ATENMEDI',
    ]);

    $result = ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination,
        (int) $doctor->id,
        'Escalamiento por traslado en ambulancia a TDG.',
    );

    $coordination->refresh();
    $case->refresh();

    expect($result['first_reassignment'])->toBeTrue()
        ->and($result['created_receivable'])->toBeTrue()
        ->and($coordination->status)->toBe(ReassignAmbulanceCoordinationToTdgDoctor::STATUS_REASSIGNED_TO_TDG)
        ->and($coordination->managed_by)->toBe('TDG')
        ->and((int) $coordination->telemedicine_doctor_id)->toBe((int) $doctor->id)
        ->and($case->managed_by)->toBe('TDG')
        ->and((int) $case->telemedicine_doctor_id)->toBe((int) $doctor->id)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->count())->toBe(1)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->value('description'))
        ->toContain(ReassignAmbulanceCoordinationToTdgDoctor::OBSERVATION_PREFIX)
        ->and(OperationServiceOrder::query()->where('operation_coordination_service_id', $coordination->id)->value('managed_by'))
        ->toBe('TDG')
        ->and(OperationAccountsReceivable::query()->where('operation_coordination_service_id', $coordination->id)->count())
        ->toBe(1);
});

it('al cambiar medico TDG no duplica cuenta por cobrar', function (): void {
    $doctorA = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. A',
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
    ]);
    $doctorB = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. B',
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
    ]);

    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Cambio',
        'age' => '30',
        'sex' => 'M',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Cambio',
        'status' => 'ASIGNADO',
        'code' => 'TM-AMB-2',
        'managed_by' => 'ATENMEDI',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'telemedicine_case_id' => $case->id,
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
    ]);

    ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination,
        (int) $doctorA->id,
        'Primera reasignación por traslado en ambulancia.',
    );

    $second = ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination->fresh(),
        (int) $doctorB->id,
        'Cambio de médico TDG responsable del caso.',
    );

    expect($second['first_reassignment'])->toBeFalse()
        ->and($second['created_receivable'])->toBeFalse()
        ->and($second['doctor_changed'])->toBeTrue()
        ->and(OperationAccountsReceivable::query()->count())->toBe(1)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->count())->toBe(2)
        ->and((int) $case->fresh()->telemedicine_doctor_id)->toBe((int) $doctorB->id);
});

it('reabre caso en ALTA MEDICA a EN SEGUIMIENTO al reasignar ambulancia a TDG', function (): void {
    $doctor = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. Reopen',
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
    ]);

    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Alta',
        'age' => '50',
        'sex' => 'M',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Alta',
        'status' => 'ALTA MEDICA',
        'code' => 'TM-AMB-REOPEN',
        'managed_by' => 'ATENMEDI',
    ]);

    TelemedicineConsultationPatient::query()->create([
        'telemedicine_case_id' => $case->id,
        'status' => 'ALTA MEDICA',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'telemedicine_case_id' => $case->id,
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
    ]);

    $result = ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination,
        (int) $doctor->id,
        'Reasignación con caso previamente en alta médica.',
    );

    expect($result['case_reopened'])->toBeTrue()
        ->and($case->fresh()->status)->toBe('EN SEGUIMIENTO')
        ->and($case->fresh()->managed_by)->toBe('TDG')
        ->and(TelemedicineConsultationPatient::query()->where('telemedicine_case_id', $case->id)->value('status'))
        ->toBe('EN SEGUIMIENTO')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->value('description'))
        ->toContain('Caso reabierto desde ALTA MEDICA');
});

it('rechaza medico que no sea TDG activo', function (): void {
    $doctor = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. Atenmedi',
        'status' => 'ACTIVO',
        'managed_by' => 'ATENMEDI',
    ]);

    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Reject',
        'age' => '25',
        'sex' => 'F',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Reject',
        'status' => 'ASIGNADO',
        'code' => 'TM-AMB-3',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'telemedicine_case_id' => $case->id,
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
    ]);

    expect(fn () => ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination,
        (int) $doctor->id,
        'Motivo válido de reasignación a TDG.',
    ))->toThrow(InvalidArgumentException::class, 'pertenecer a TDG');
});

it('rechaza coordinacion sin caso vinculado', function (): void {
    $doctor = TelemedicineDoctor::query()->create([
        'full_name' => 'Dr. TDG',
        'status' => 'ACTIVO',
        'managed_by' => 'TDG',
    ]);

    $coordination = OperationCoordinationService::query()->create([
        'telemedicine_case_id' => null,
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
    ]);

    expect(fn () => ReassignAmbulanceCoordinationToTdgDoctor::execute(
        $coordination,
        (int) $doctor->id,
        'Motivo válido de reasignación a TDG.',
    ))->toThrow(InvalidArgumentException::class, 'caso de telemedicina');
});

it('OperationCoordinationServicesTable usa el servicio de reasignacion ambulancia a TDG', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('use App\Support\Operations\ReassignAmbulanceCoordinationToTdgDoctor;')
        ->toContain("Action::make('selectTdgDoctorForAmbulanceFollowUp')")
        ->toContain('Reasignar TRASLADO EN AMBULANCIA a TDG')
        ->toContain("Textarea::make('reassignment_observation')")
        ->toContain('ReassignAmbulanceCoordinationToTdgDoctor::execute')
        ->toContain('ReassignAmbulanceCoordinationToTdgDoctor::isEligible')
        ->toContain('applyHideReassignedToTdgScope')
        ->toContain('STATUS_REASSIGNED_TO_TDG')
        ->toContain('REASIGNADO A TDG');
});

it('applyHideFullyFinalizedScope excluye reasignados a TDG', function (): void {
    OperationCoordinationService::query()->create([
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => ReassignAmbulanceCoordinationToTdgDoctor::STATUS_REASSIGNED_TO_TDG,
        'managed_by' => 'TDG',
    ]);

    OperationCoordinationService::query()->create([
        'specific_service' => 'TRASLADO EN AMBULANCIA',
        'status' => 'PENDIENTE',
        'managed_by' => 'ATENMEDI',
    ]);

    $query = OperationCoordinationServicesTable::applyHideFullyFinalizedScope(
        OperationCoordinationService::query()
    );

    expect($query->count())->toBe(1)
        ->and($query->value('status'))->toBe('PENDIENTE');
});
