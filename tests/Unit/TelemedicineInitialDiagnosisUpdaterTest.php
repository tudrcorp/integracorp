<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineInitialDiagnosisUpdater;
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

    Schema::dropIfExists('observation_cases');
    Schema::dropIfExists('telemedicine_consultation_patients');
    Schema::dropIfExists('telemedicine_cases');
    Schema::dropIfExists('telemedicine_patients');

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
        $table->string('patient_name')->nullable();
        $table->string('patient_age')->nullable();
        $table->string('patient_sex')->nullable();
        $table->string('status')->nullable();
        $table->string('code')->nullable();
        $table->timestamps();
    });

    Schema::create('telemedicine_consultation_patients', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->string('status')->nullable();
        $table->text('diagnostic_impression')->nullable();
        $table->string('code_reference')->nullable();
        $table->timestamps();
    });

    Schema::create('observation_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->text('description')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });
});

it('normaliza el diagnóstico a mayúsculas y compacta espacios', function (): void {
    expect(TelemedicineInitialDiagnosisUpdater::normalize("  faringitis   aguda \n"))
        ->toBe('FARINGITIS AGUDA')
        ->and(TelemedicineInitialDiagnosisUpdater::normalize('   '))->toBe('')
        ->and(TelemedicineInitialDiagnosisUpdater::diagnosesAreEqual('Faringitis Aguda', '  FARINGITIS   AGUDA  '))->toBeTrue();
});

it('construye la descripción de bitácora con diagnóstico anterior, nuevo y origen', function (): void {
    $description = TelemedicineInitialDiagnosisUpdater::buildBitacoraDescription(
        'faringitis',
        'faringitis aguda + otitis',
        'REF-12345',
    );

    expect($description)
        ->toContain(TelemedicineInitialDiagnosisUpdater::OBSERVATION_PREFIX)
        ->toContain('Diagnóstico anterior: FARINGITIS')
        ->toContain('Diagnóstico nuevo: FARINGITIS AGUDA + OTITIS')
        ->toContain('Origen: seguimiento REF-12345');
});

it('usa marcador cuando el diagnóstico anterior está vacío', function (): void {
    expect(TelemedicineInitialDiagnosisUpdater::buildBitacoraDescription(null, 'migraña'))
        ->toContain('Diagnóstico anterior: (sin registro)')
        ->toContain('Diagnóstico nuevo: MIGRAÑA')
        ->not->toContain('Origen:');
});

it('copia el campo del wizard a diagnostic_impression y lo retira del payload', function (): void {
    $data = TelemedicineInitialDiagnosisUpdater::mergeIntoConsultationFormData([
        'status' => 'EN SEGUIMIENTO',
        TelemedicineInitialDiagnosisUpdater::FORM_FIELD => '  sinusitis  ',
        'cuestion_1' => 'MEJOR',
    ]);

    expect($data)->not->toHaveKey(TelemedicineInitialDiagnosisUpdater::FORM_FIELD)
        ->and($data['diagnostic_impression'])->toBe('SINUSITIS')
        ->and($data['cuestion_1'])->toBe('MEJOR');
});

it('actualiza el diagnóstico de la consulta inicial y registra el cambio en bitácora', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Diagnóstico',
        'age' => '32',
        'sex' => 'F',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Diagnóstico',
        'status' => 'EN SEGUIMIENTO',
        'code' => 'TM-DX-1',
    ]);

    $initial = TelemedicineConsultationPatient::query()->create([
        'telemedicine_case_id' => $case->id,
        'status' => 'CONSULTA INICIAL',
        'diagnostic_impression' => 'FARINGITIS',
        'code_reference' => 'REF-11111',
    ]);

    $result = TelemedicineInitialDiagnosisUpdater::syncFromFollowUp(
        (int) $case->id,
        'faringitis aguda bacteriana',
        null,
        'REF-22222',
    );

    expect($result['updated'])->toBeTrue()
        ->and($initial->fresh()->diagnostic_impression)->toBe('FARINGITIS AGUDA BACTERIANA')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->count())->toBe(1)
        ->and((string) ObservationCase::query()->where('telemedicine_case_id', $case->id)->value('description'))
        ->toContain(TelemedicineInitialDiagnosisUpdater::OBSERVATION_PREFIX)
        ->toContain('Diagnóstico anterior: FARINGITIS')
        ->toContain('Diagnóstico nuevo: FARINGITIS AGUDA BACTERIANA')
        ->toContain('Origen: seguimiento REF-22222');
});

it('no escribe bitácora ni muta si el diagnóstico no cambió o viene vacío', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Igual',
        'age' => '40',
        'sex' => 'M',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Igual',
        'status' => 'EN SEGUIMIENTO',
        'code' => 'TM-DX-2',
    ]);

    TelemedicineConsultationPatient::query()->create([
        'telemedicine_case_id' => $case->id,
        'status' => 'CONSULTA INICIAL',
        'diagnostic_impression' => 'MIGRAÑA',
    ]);

    $unchanged = TelemedicineInitialDiagnosisUpdater::syncFromFollowUp((int) $case->id, '  migraña  ');
    $empty = TelemedicineInitialDiagnosisUpdater::syncFromFollowUp((int) $case->id, '   ');

    expect($unchanged['updated'])->toBeFalse()
        ->and($empty['updated'])->toBeFalse()
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->count())->toBe(0)
        ->and(TelemedicineInitialDiagnosisUpdater::currentDiagnosis((int) $case->id))->toBe('MIGRAÑA');
});

it('no actualiza si el caso no tiene consulta inicial', function (): void {
    $result = TelemedicineInitialDiagnosisUpdater::syncFromFollowUp(999, 'cualquier diagnóstico');

    expect($result['updated'])->toBeFalse()
        ->and($result['initial'])->toBeNull()
        ->and($result['observation'])->toBeNull();
});

it('el formulario de seguimiento incluye el campo de diagnóstico principal', function (): void {
    $form = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Schemas/TelemedicineConsultationPatientForm.php'
    );

    expect($form)
        ->toContain('TelemedicineInitialDiagnosisUpdater::FORM_FIELD')
        ->toContain("->label('Diagnóstico principal de la consulta inicial')")
        ->toContain('Cuestionario de Seguimiento');
});

it('la creación y edición de consulta sincronizan el diagnóstico principal en seguimientos', function (): void {
    $create = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php'
    );
    $edit = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/EditTelemedicineConsultationPatient.php'
    );

    expect($create)
        ->toContain('TelemedicineInitialDiagnosisUpdater::formStateForCase')
        ->toContain('TelemedicineInitialDiagnosisUpdater::mergeIntoConsultationFormData')
        ->toContain('TelemedicineInitialDiagnosisUpdater::syncFromFollowUp');

    expect($edit)
        ->toContain('mutateFormDataBeforeFill')
        ->toContain('mutateFormDataBeforeSave')
        ->toContain('TelemedicineInitialDiagnosisUpdater::syncFromFollowUp');
});
