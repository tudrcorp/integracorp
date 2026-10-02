<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Siembra un caso por equipo: uno TDG y uno por cada proveedor, todos asignados
 * a un médico distinto del que consulta, como pasa al cambiar la guardia.
 *
 * @return array{doctors: array<string, int>, cases: array<string, int>}
 */
function seedGuardTeamCases(): array
{
    $tdg = TelemedicineDoctor::query()->where('managed_by', 'TDG')->orderBy('id')->limit(2)->pluck('id')->all();
    $supplierIds = TelemedicineDoctor::query()->whereNotNull('supplier_id')->distinct()->orderBy('supplier_id')->limit(2)->pluck('supplier_id')->all();

    if (count($tdg) < 2 || count($supplierIds) < 2) {
        test()->markTestSkipped('La base no tiene dos médicos TDG y dos proveedores con médicos.');
    }

    $supplierA = TelemedicineDoctor::query()->where('supplier_id', $supplierIds[0])->orderBy('id')->pluck('id')->all();
    $supplierB = TelemedicineDoctor::query()->where('supplier_id', $supplierIds[1])->orderBy('id')->value('id');

    if (count($supplierA) < 2) {
        test()->markTestSkipped('El primer proveedor no tiene dos médicos.');
    }

    $patientId = TelemedicinePatient::query()->orderBy('id')->value('id');
    $now = now();

    $insert = fn (int $doctorId, string $managedBy, string $code): int => DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => $patientId, 'telemedicine_doctor_id' => $doctorId, 'code' => $code,
        'status' => 'ASIGNADO', 'managed_by' => $managedBy, 'created_at' => $now, 'updated_at' => $now,
    ]);

    return [
        'doctors' => [
            'tdg_on_duty' => (int) $tdg[0],
            'supplier_a_on_duty' => (int) $supplierA[0],
            'supplier_b' => (int) $supplierB,
        ],
        'cases' => [
            'tdg' => $insert((int) $tdg[1], 'TDG', 'ZZGUARD-TDG'),
            'supplier_a' => $insert((int) $supplierA[1], 'PROVEEDOR A', 'ZZGUARD-A'),
            'supplier_b' => $insert((int) $supplierB, 'PROVEEDOR B', 'ZZGUARD-B'),
        ],
    ];
}

/**
 * @param  array<string, int>  $cases
 * @return list<int>
 */
function teamCaseIdsFor(int $doctorId, array $cases): array
{
    $query = TelemedicineCase::query()->whereIn('id', array_values($cases));

    return TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases($query, $doctorId)
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();
}

function doctorUser(int $doctorId): User
{
    return (new User)->forceFill(['id' => 999_999, 'doctor_id' => $doctorId]);
}

it('el médico TDG de guardia ve los casos TDG asignados a otro médico, y no los de proveedores', function (): void {
    ['doctors' => $doctors, 'cases' => $cases] = seedGuardTeamCases();

    expect(teamCaseIdsFor($doctors['tdg_on_duty'], $cases))->toBe([$cases['tdg']]);
});

it('el médico de un proveedor ve los casos de todo su equipo, y no los de TDG ni de otro proveedor', function (): void {
    ['doctors' => $doctors, 'cases' => $cases] = seedGuardTeamCases();

    expect(teamCaseIdsFor($doctors['supplier_a_on_duty'], $cases))->toBe([$cases['supplier_a']])
        ->and(teamCaseIdsFor($doctors['supplier_b'], $cases))->toBe([$cases['supplier_b']]);
});

it('un médico sin ficha válida solo ve los casos asignados a él', function (): void {
    ['cases' => $cases] = seedGuardTeamCases();

    expect(teamCaseIdsFor(0, $cases))->toBe([]);
});

it('el médico de guardia puede gestionar el caso de su equipo pero no el de otro equipo', function (): void {
    ['doctors' => $doctors, 'cases' => $cases] = seedGuardTeamCases();

    $case = fn (string $key): TelemedicineCase => TelemedicineCase::query()->findOrFail($cases[$key]);

    expect(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(doctorUser($doctors['tdg_on_duty']), $case('tdg')))->toBeTrue()
        ->and(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(doctorUser($doctors['tdg_on_duty']), $case('supplier_a')))->toBeFalse()
        ->and(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(doctorUser($doctors['supplier_a_on_duty']), $case('supplier_a')))->toBeTrue()
        ->and(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(doctorUser($doctors['supplier_a_on_duty']), $case('tdg')))->toBeFalse()
        ->and(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(doctorUser($doctors['supplier_a_on_duty']), $case('supplier_b')))->toBeFalse()
        ->and(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam(new User, $case('tdg')))->toBeFalse();
});

it('los conteos de la ficha del paciente incluyen los casos del equipo', function (): void {
    $firstSupplierId = TelemedicineDoctor::query()->whereNotNull('supplier_id')->min('supplier_id');
    $doctorId = TelemedicineDoctor::query()->where('supplier_id', $firstSupplierId)->orderBy('id')->value('id');
    $before = ListTelemedicinePatients::summary((int) $doctorId);

    ['doctors' => $doctors] = seedGuardTeamCases();

    expect($doctors['supplier_a_on_duty'])->toBe((int) $doctorId)
        ->and(ListTelemedicinePatients::summary((int) $doctorId)['assigned'] - $before['assigned'])->toBe(1);
});

it('el escritorio valida por equipo y no por médico asignado', function (): void {
    $widget = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Widgets/TelemedicineCaseTableDash.php');

    expect(substr_count($widget, 'TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam('))->toBe(2)
        ->and($widget)->not->toContain('telemedicine_doctor_id !== $user->doctor_id')
        ->and($widget)->toContain('no pertenece a su equipo médico');
});
