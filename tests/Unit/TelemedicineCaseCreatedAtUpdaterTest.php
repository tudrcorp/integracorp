<?php

declare(strict_types=1);

use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicineCaseCreatedAtUpdater;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'app.timezone' => 'America/Caracas',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::dropIfExists('observation_cases');
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

    Schema::create('observation_cases', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('telemedicine_case_id')->nullable();
        $table->text('description')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });
});

it('construye la descripcion de bitacora con fechas y motivo', function (): void {
    $description = TelemedicineCaseCreatedAtUpdater::buildBitacoraDescription(
        Carbon::parse('2026-08-01 10:00:00', 'America/Caracas'),
        Carbon::parse('2026-08-04 15:50:50', 'America/Caracas'),
        'Corrección administrativa de la fecha real.',
    );

    expect($description)
        ->toContain(TelemedicineCaseCreatedAtUpdater::OBSERVATION_PREFIX)
        ->toContain('Fecha anterior: 01/08/2026 10:00:00')
        ->toContain('Fecha nueva: 04/08/2026 15:50:50')
        ->toContain('Motivo: Corrección administrativa de la fecha real.');
});

it('actualiza created_at y registra el cambio en bitacora', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Fecha',
        'age' => '40',
        'sex' => 'F',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Fecha',
        'status' => 'ASIGNADO',
        'code' => 'TM-DATE-1',
    ]);

    TelemedicineCase::query()->whereKey($case->id)->update([
        'created_at' => '2026-08-04 15:50:50',
        'updated_at' => '2026-08-04 15:50:50',
    ]);
    $case->refresh();

    $result = TelemedicineCaseCreatedAtUpdater::execute(
        $case,
        '2026-08-01 09:30:00',
        'Ajuste por registro tardío del caso en el sistema.',
    );

    expect($result['case']->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-08-01 09:30:00')
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->count())->toBe(1)
        ->and(ObservationCase::query()->where('telemedicine_case_id', $case->id)->value('description'))
        ->toContain(TelemedicineCaseCreatedAtUpdater::OBSERVATION_PREFIX)
        ->toContain('Fecha anterior: 04/08/2026 15:50:50')
        ->toContain('Fecha nueva: 01/08/2026 09:30:00');
});

it('rechaza motivo corto y fechas futuras o iguales', function (): void {
    $patient = TelemedicinePatient::query()->create([
        'full_name' => 'Paciente Reject',
        'age' => '30',
        'sex' => 'M',
    ]);

    $case = TelemedicineCase::query()->create([
        'telemedicine_patient_id' => $patient->id,
        'patient_name' => 'Paciente Reject',
        'status' => 'ASIGNADO',
        'code' => 'TM-DATE-2',
    ]);

    TelemedicineCase::query()->whereKey($case->id)->update([
        'created_at' => '2026-08-01 10:00:00',
        'updated_at' => now(),
    ]);
    $case->refresh();

    expect(fn () => TelemedicineCaseCreatedAtUpdater::execute($case, '2026-08-01 11:00:00', 'corto'))
        ->toThrow(InvalidArgumentException::class, 'al menos 10 caracteres');

    expect(fn () => TelemedicineCaseCreatedAtUpdater::execute(
        $case,
        Carbon::parse('2026-08-01 10:00:00', 'America/Caracas'),
        'Motivo válido con más de diez caracteres.',
    ))->toThrow(InvalidArgumentException::class, 'igual a la fecha actual');

    expect(fn () => TelemedicineCaseCreatedAtUpdater::execute(
        $case,
        now()->addDay(),
        'Motivo válido con más de diez caracteres.',
    ))->toThrow(InvalidArgumentException::class, 'no puede ser futura');
});
it('TelemedicineCasesTable define la accion de cambio de fecha de creacion en record actions', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Tables/TelemedicineCasesTable.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('TelemedicineCaseCreatedAtChangeAction::make()');
});

it('ViewTelemedicineCase agrega header action de cambio de fecha para casos en alta medica', function (): void {
    $view = file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicineCases/Pages/ViewTelemedicineCase.php'
    );
    $action = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Telemedicine/TelemedicineCaseCreatedAtChangeAction.php'
    );

    expect($view)
        ->toContain('TelemedicineCaseCreatedAtChangeAction::make($case)')
        ->toContain("'ALTA MEDICA'")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')");

    expect($action)
        ->toContain("Action::make('changeCaseCreatedAt')")
        ->toContain('TelemedicineCaseCreatedAtUpdater::execute')
        ->toContain("DateTimePicker::make('created_at')")
        ->toContain("Textarea::make('change_reason')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('warning')")
        ->toContain("FilamentIosButton::extraClassForFilamentColor('gray')");
});
