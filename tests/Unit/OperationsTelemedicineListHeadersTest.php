<?php

declare(strict_types=1);

use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Support\Operations\OperationsListHeaderCounts;
use App\Support\Telemedicine\TelemedicineUrgentPriorities;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('agrega total, condiciones y distintos en una sola consulta aunque la base tenga orden y relaciones', function (): void {
    $query = TelemedicineCase::query()->where('status', '!=', 'ALTA MEDICA')->with('priority')->orderBy('created_at', 'desc');
    [$urgentCondition, $urgentBindings] = TelemedicineUrgentPriorities::sqlCondition();

    DB::enableQueryLog();
    $summary = OperationsListHeaderCounts::aggregate(
        $query,
        [
            'assigned' => ['telemedicine_cases.status = ?', ['ASIGNADO']],
            'urgent' => [$urgentCondition, $urgentBindings],
        ],
        ['patients' => 'telemedicine_cases.telemedicine_patient_id'],
    );
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $base = TelemedicineCase::query()->where('status', '!=', 'ALTA MEDICA');

    expect($queries)->toHaveCount(1)
        ->and($summary['total'])->toBe((clone $base)->count())
        ->and($summary['assigned'])->toBe((clone $base)->where('status', 'ASIGNADO')->count())
        ->and($summary['urgent'])->toBe((clone $base)->whereHas('priority', fn ($q) => $q->whereIn('name', TelemedicineUrgentPriorities::NAMES))->count())
        ->and($summary['patients'])->toBe((clone $base)->distinct()->count('telemedicine_patient_id'))
        ->and($query->getQuery()->orders)->not->toBeEmpty();
});

it('cuenta un paciente con caso abierto solo si el caso no está en alta ni eliminado', function (): void {
    $patientId = TelemedicinePatient::query()->value('id');
    $doctorId = TelemedicineDoctor::query()->value('id');
    $openCase = [
        'EXISTS (SELECT 1 FROM telemedicine_cases AS tc WHERE tc.telemedicine_patient_id = telemedicine_patients.id AND tc.status NOT IN (?, ?))',
        ['ALTA MEDICA', 'ELIMINADO'],
    ];
    $count = fn (): int => OperationsListHeaderCounts::aggregate(TelemedicinePatient::query()->whereKey($patientId), ['open_case' => $openCase])['open_case'];

    DB::table('telemedicine_cases')->where('telemedicine_patient_id', $patientId)->update(['status' => 'ALTA MEDICA']);
    $insert = fn (string $status, string $code) => DB::table('telemedicine_cases')->insert([
        'telemedicine_patient_id' => $patientId, 'telemedicine_doctor_id' => $doctorId, 'code' => $code,
        'status' => $status, 'managed_by' => 'TDG', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $insert('ELIMINADO', 'ZZOPS-1');
    expect($count())->toBe(0);

    $insert('EN SEGUIMIENTO', 'ZZOPS-2');
    expect($count())->toBe(1);
});

it('sin condiciones devuelve solo el total', function (): void {
    expect(OperationsListHeaderCounts::aggregate(TelemedicineDoctor::query()))
        ->toBe(['total' => TelemedicineDoctor::query()->count()]);
});

it('los listados de Telemedicina en Operaciones usan el encabezado sobre la consulta de su tabla', function (string $page): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/'.$page);

    expect($source)
        ->toContain('filament.operations.partials.list-header')
        ->toContain('OperationsListHeaderCounts::aggregate(')
        ->toContain('$this->getTable()->getQuery()');
})->with([
    'casos' => ['TelemedicineCases/Pages/ListTelemedicineCases.php'],
    'historias' => ['TelemedicineHistoryPatients/Pages/ListTelemedicineHistoryPatients.php'],
    'doctores' => ['TelemedicineDoctors/Pages/ListTelemedicineDoctors.php'],
    'pacientes' => ['TelemedicinePatients/Pages/ListTelemedicinePatients.php'],
]);
