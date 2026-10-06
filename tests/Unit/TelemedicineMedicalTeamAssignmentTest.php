<?php

declare(strict_types=1);

use App\Models\Supplier;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicineMedicalTeam;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function teamAnalyst(?int $supplierId = null, bool $isAmd = false): User
{
    return (new User)->forceFill(['id' => 999_998, 'supplier_id' => $supplierId, 'is_proveedor_amd' => $isAmd, 'departament' => ['OPERACIONES']]);
}

/**
 * @return list<int>
 */
function suppliersWithDoctors(): array
{
    $ids = TelemedicineDoctor::query()->whereNotNull('supplier_id')->distinct()->orderBy('supplier_id')->pluck('supplier_id')
        ->map(fn ($id): int => (int) $id)->all();

    if ($ids === []) {
        test()->markTestSkipped('La base no tiene proveedores con médicos de telemedicina.');
    }

    return $ids;
}

/**
 * @return list<int>
 */
function managedSuppliersWithDoctors(): array
{
    $ids = Supplier::query()
        ->where('gestion_integracorp', true)
        ->whereIn('id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('supplier_id'))
        ->orderBy('id')
        ->pluck('id')
        ->map(fn ($id): int => (int) $id)
        ->all();

    if ($ids === []) {
        test()->markTestSkipped('La base no tiene proveedores con gestión en Integracorp y médicos.');
    }

    return $ids;
}

function insertTeamCase(?int $teamSupplierId, string $managedBy, string $code): int
{
    return DB::table('telemedicine_cases')->insertGetId([
        'telemedicine_patient_id' => TelemedicinePatient::query()->orderBy('id')->value('id'),
        'telemedicine_doctor_id' => null,
        'assigned_to_medical_team' => true,
        'medical_team_supplier_id' => $teamSupplierId,
        'code' => $code, 'status' => 'ASIGNADO', 'managed_by' => $managedBy,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

it('el analista TDG elige entre el equipo TDG y los proveedores con gestión en Integracorp y médicos', function (): void {
    $managed = managedSuppliersWithDoctors();
    $this->actingAs(teamAnalyst());

    $options = TelemedicineMedicalTeam::optionsForCurrentUser();

    expect(array_key_first($options))->toBe(TelemedicineMedicalTeam::TDG)
        ->and($options[TelemedicineMedicalTeam::TDG])->toBe('Equipo Médico TDG')
        ->and(array_map('strval', array_keys($options)))->toEqualCanonicalizing([TelemedicineMedicalTeam::TDG, ...array_map('strval', $managed)]);
});

it('excluye al proveedor con médicos pero sin «Habilitar gestión en Integracorp»', function (): void {
    $supplierId = (int) suppliersWithDoctors()[0];
    $this->actingAs(teamAnalyst());

    Supplier::query()->whereKey($supplierId)->update(['gestion_integracorp' => false]);

    expect(TelemedicineMedicalTeam::optionsForCurrentUser())->not->toHaveKey($supplierId)
        ->and(TelemedicineMedicalTeam::resolveForCurrentUser((string) $supplierId))->toBeNull();

    $this->actingAs(teamAnalyst($supplierId));
    expect(TelemedicineMedicalTeam::optionsForCurrentUser())->toBe([]);

    Supplier::query()->whereKey($supplierId)->update(['gestion_integracorp' => true]);
    expect(TelemedicineMedicalTeam::optionsForCurrentUser())->toHaveKey($supplierId);
});

it('el analista de un proveedor solo puede asignar a su propio equipo', function (): void {
    $supplierId = managedSuppliersWithDoctors()[0];
    $this->actingAs(teamAnalyst($supplierId));

    expect(array_map('strval', array_keys(TelemedicineMedicalTeam::optionsForCurrentUser())))->toBe([(string) $supplierId])
        ->and(TelemedicineMedicalTeam::resolveForCurrentUser(TelemedicineMedicalTeam::TDG))->toBeNull();
});

it('sin equipo disponible no ofrece la opción', function (): void {
    $this->actingAs(teamAnalyst(isAmd: true));
    expect(TelemedicineMedicalTeam::optionsForCurrentUser())->toBe([]);

    $withoutDoctors = Supplier::query()->whereNotIn('id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('supplier_id'))->value('id');

    if ($withoutDoctors !== null) {
        $this->actingAs(teamAnalyst((int) $withoutDoctors));
        expect(TelemedicineMedicalTeam::optionsForCurrentUser())->toBe([]);
    }
});

it('valida en el servidor el equipo elegido', function (mixed $selection): void {
    suppliersWithDoctors();
    $this->actingAs(teamAnalyst());

    expect(TelemedicineMedicalTeam::resolveForCurrentUser($selection))->toBeNull();
})->with([
    'vacío' => [''],
    'nulo' => [null],
    'inexistente' => ['999999999'],
    'arreglo' => [['TDG']],
    'inyección' => ["15' OR 1=1"],
]);

it('resuelve el equipo TDG y el de un proveedor con el managed_by que usan sus médicos', function (): void {
    $supplierId = managedSuppliersWithDoctors()[0];
    $this->actingAs(teamAnalyst());

    $expectedManagedBy = TelemedicineDoctor::query()->where('supplier_id', $supplierId)
        ->groupBy('managed_by')->orderByRaw('COUNT(*) DESC')->value('managed_by');

    expect(TelemedicineMedicalTeam::resolveForCurrentUser('TDG'))->toBe(['supplier_id' => null, 'managed_by' => 'TDG'])
        ->and(TelemedicineMedicalTeam::resolveForCurrentUser((string) $supplierId))->toBe([
            'supplier_id' => $supplierId,
            'managed_by' => (string) $expectedManagedBy,
        ]);
});

it('el caso del equipo TDG lo ven los médicos TDG y no los de proveedores', function (): void {
    $supplierId = suppliersWithDoctors()[0];
    $tdgDoctor = (int) TelemedicineDoctor::query()->where('managed_by', 'TDG')->value('id');
    $supplierDoctor = (int) TelemedicineDoctor::query()->where('supplier_id', $supplierId)->value('id');

    $caseId = insertTeamCase(null, 'TDG', 'ZZTEAM-TDG');
    $visibleTo = fn (int $doctorId): bool => TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases(
        TelemedicineCase::query()->whereKey($caseId), $doctorId
    )->exists();

    expect($visibleTo($tdgDoctor))->toBeTrue()
        ->and($visibleTo($supplierDoctor))->toBeFalse();
});

it('el caso del equipo de un proveedor lo ve su equipo aunque nadie lo haya tomado, y nadie más', function (): void {
    $suppliers = suppliersWithDoctors();
    $supplierId = $suppliers[0];
    $teamDoctor = (int) TelemedicineDoctor::query()->where('supplier_id', $supplierId)->value('id');
    $tdgDoctor = (int) TelemedicineDoctor::query()->where('managed_by', 'TDG')->value('id');

    $caseId = insertTeamCase($supplierId, 'PROVEEDOR', 'ZZTEAM-SUP');
    $visibleTo = fn (int $doctorId): bool => TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases(
        TelemedicineCase::query()->whereKey($caseId), $doctorId
    )->exists();

    expect($visibleTo($teamDoctor))->toBeTrue()
        ->and($visibleTo($tdgDoctor))->toBeFalse();

    if (isset($suppliers[1])) {
        $otherSupplierDoctor = (int) TelemedicineDoctor::query()->where('supplier_id', $suppliers[1])->value('id');
        expect($visibleTo($otherSupplierDoctor))->toBeFalse();
    }

    $case = TelemedicineCase::query()->findOrFail($caseId);
    expect(TelemedicineCaseFilamentListQuery::caseBelongsToUserDoctorTeam((new User)->forceFill(['doctor_id' => $teamDoctor]), $case))->toBeTrue();
});

it('el caso de equipo pasa al médico que actualiza y cambia con cada actualización', function (): void {
    [$first, $second] = TelemedicineDoctor::query()->where('managed_by', 'TDG')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $case = TelemedicineCase::query()->findOrFail(insertTeamCase(null, 'TDG', 'ZZTEAM-UPD'));

    TelemedicineMedicalTeam::applyUpdatingDoctor($case, $first);
    $case->save();
    expect($case->fresh()->telemedicine_doctor_id)->toBe($first);

    TelemedicineMedicalTeam::applyUpdatingDoctor($case, $second);
    $case->save();
    expect($case->fresh()->telemedicine_doctor_id)->toBe($second)
        ->and($case->fresh()->assigned_to_medical_team)->toBeTrue();
});

it('el caso de un médico particular pasa al médico que lo atiende y no acepta un médico inválido', function (): void {
    [$assigned, $other] = TelemedicineDoctor::query()->where('managed_by', 'TDG')->orderBy('id')->limit(2)->pluck('id')->map(fn ($id): int => (int) $id)->all();

    $individual = new TelemedicineCase(['telemedicine_doctor_id' => $assigned, 'assigned_to_medical_team' => false]);
    TelemedicineMedicalTeam::applyUpdatingDoctor($individual, $other);

    $invalid = new TelemedicineCase(['telemedicine_doctor_id' => $assigned, 'assigned_to_medical_team' => false]);
    TelemedicineMedicalTeam::applyUpdatingDoctor($invalid, 0);
    TelemedicineMedicalTeam::applyUpdatingDoctor($invalid, null);
    TelemedicineMedicalTeam::applyUpdatingDoctor(null, $other);

    $team = new TelemedicineCase(['telemedicine_doctor_id' => null, 'assigned_to_medical_team' => true]);
    TelemedicineMedicalTeam::applyUpdatingDoctor($team, 0);

    expect($individual->telemedicine_doctor_id)->toBe($other)
        ->and($invalid->telemedicine_doctor_id)->toBe($assigned)
        ->and($team->telemedicine_doctor_id)->toBeNull();
});

it('muestra el equipo mientras nadie toma el caso y el médico cuando ya lo tomó', function (): void {
    $supplierId = suppliersWithDoctors()[0];
    $supplier = Supplier::query()->findOrFail($supplierId);
    $supplierName = trim((string) $supplier->integracorp_alias) !== '' ? trim((string) $supplier->integracorp_alias) : trim((string) $supplier->name);

    $tdgTeam = new TelemedicineCase(['assigned_to_medical_team' => true, 'medical_team_supplier_id' => null]);
    $supplierTeam = TelemedicineCase::query()->findOrFail(insertTeamCase($supplierId, 'PROVEEDOR', 'ZZTEAM-LBL'));
    $noDoctor = new TelemedicineCase(['assigned_to_medical_team' => false]);

    $doctor = TelemedicineDoctor::query()->where('managed_by', 'TDG')->firstOrFail();
    $taken = new TelemedicineCase(['assigned_to_medical_team' => true]);
    $taken->setRelation('telemedicineDoctor', $doctor);

    expect(TelemedicineMedicalTeam::assigneeLabel($tdgTeam))->toBe('Equipo Médico TDG')
        ->and(TelemedicineMedicalTeam::assigneeLabel($supplierTeam))->toBe('Equipo Médico · '.$supplierName)
        ->and(TelemedicineMedicalTeam::assigneeLabel($noDoctor))->toBe('Dr(a). —')
        ->and(TelemedicineMedicalTeam::assigneeLabel($taken))->toBe('Dr(a). '.$doctor->full_name);
});

it('la modal de asignación oculta doctor y «Pertenece a?» con el check y no notifica al equipo', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/TelemedicinePatients/Actions/AssignDoctorAction.php');

    expect($source)
        ->toContain("Toggle::make('assign_to_medical_team')")
        ->toContain("->label('Asignar al Equipo Médico')")
        ->toContain("->hidden(fn (Get \$get): bool => (bool) \$get('assign_to_medical_team'))")
        ->toContain("&& ! \$get('assign_to_medical_team')")
        ->toContain("Select::make('medical_team')")
        ->toContain('TelemedicineMedicalTeam::resolveForCurrentUser(')
        ->toContain("'telemedicine_doctor_id' => null,")
        ->toContain("'belongs_to' => null,")
        ->not->toContain("'managed_by' => \$doctor->managed_by,\n                            'supplier_id'")
        ->and(substr_count($source, '...$assignment,'))->toBe(3)
        ->and(substr_count($source, "if (\$doctor !== null) {\n                                AssignedCase::dispatch("))->toBe(3);
});

it('la consulta asigna el caso de equipo al médico que la registra en los tres caminos', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Telemedicina/Resources/TelemedicineConsultationPatients/Pages/CreateTelemedicineConsultationPatient.php');

    expect(substr_count($source, "TelemedicineMedicalTeam::applyUpdatingDoctor(\$case, (int) \$record['telemedicine_doctor_id']);"))->toBe(3);
});
