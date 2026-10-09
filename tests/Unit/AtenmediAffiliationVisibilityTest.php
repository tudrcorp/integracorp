<?php

declare(strict_types=1);

use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ListTelemedicinePatients;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages\ViewTelemedicinePatient;
use App\Models\Supplier;
use App\Models\TelemedicineDoctor;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Support\Telemedicine\AtenmediAccess;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use App\Support\Telemedicine\TelemedicinePatientAffiliationValidity as Validity;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    AtenmediAccess::flush();
    Filament::setCurrentPanel('telemedicina');
});

afterEach(function (): void {
    DB::rollBack();
    AtenmediAccess::flush();
});

function atenmediSupplierId(): ?int
{
    /** De los proveedores operados por ATENMEDI, el que tiene médicos. */
    $id = Supplier::query()
        ->where('name', 'like', '%(ATENMEDI)%')
        ->whereIn('id', TelemedicineDoctor::query()->whereNotNull('supplier_id')->select('supplier_id'))
        ->orderBy('id')
        ->value('id');

    return $id !== null ? (int) $id : null;
}

function telemedicineUser(?int $doctorId, ?int $supplierId = null, array $departments = ['TELEMEDICINA']): User
{
    return User::factory()->create([
        'email' => 'qa.atenmedi.'.uniqid().'@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        'doctor_id' => $doctorId,
        'supplier_id' => $supplierId,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * Quién es ATENMEDI
 * ---------------------------------------------------------------------------
 */

it('reconoce a ATENMEDI por el proveedor, no por el texto managed_by', function (): void {
    $supplierId = atenmediSupplierId();
    $atenmediDoctor = TelemedicineDoctor::query()->where('supplier_id', $supplierId)->first();
    $tdgDoctor = TelemedicineDoctor::query()->where('managed_by', 'TDG')->whereNull('supplier_id')->first();

    if ($supplierId === null || $atenmediDoctor === null || $tdgDoctor === null) {
        $this->markTestSkipped('Faltan proveedor o médicos de ATENMEDI y TDG en esta base.');
    }

    expect(AtenmediAccess::supplierIds())->toContain($supplierId)
        ->and(AtenmediAccess::userIsAtenmedi(telemedicineUser($atenmediDoctor->id)))->toBeTrue()
        ->and(AtenmediAccess::userIsAtenmedi(telemedicineUser(null, $supplierId, ['OPERACIONES'])))->toBeTrue()
        ->and(AtenmediAccess::userIsAtenmedi(telemedicineUser(null, null, ['ATENMEDI'])))->toBeTrue()
        ->and(AtenmediAccess::userIsAtenmedi(telemedicineUser($tdgDoctor->id)))->toBeFalse()
        ->and(AtenmediAccess::userIsAtenmedi(telemedicineUser(null, null, ['SUPERADMIN', 'OPERACIONES'])))->toBeFalse()
        ->and(AtenmediAccess::userIsAtenmedi(null))->toBeFalse();
});

it('los proveedores fijados en configuración mandan sobre el nombre', function (): void {
    config(['services.atenmedi.supplier_ids' => [999999]]);
    AtenmediAccess::flush();

    expect(AtenmediAccess::supplierIds())->toBe([999999])
        ->and(AtenmediAccess::isAtenmediSupplier(999999))->toBeTrue()
        ->and(AtenmediAccess::isAtenmediSupplier(atenmediSupplierId()))->toBeFalse();
});

it('no enciende el contexto ATENMEDI viejo: los casos del médico no cambian', function (): void {
    $atenmediDoctor = TelemedicineDoctor::query()->where('supplier_id', atenmediSupplierId())->first();

    if ($atenmediDoctor === null) {
        $this->markTestSkipped('No hay médico de ATENMEDI.');
    }

    expect(TelemedicineCaseFilamentListQuery::userIsInAtenmediTelemedicinaContext(telemedicineUser($atenmediDoctor->id)))->toBeFalse();
});

/*
 * ---------------------------------------------------------------------------
 * Afiliación vigente
 * ---------------------------------------------------------------------------
 */

it('decide la vigencia con la afiliación y el afiliado reales', function (?string $affiliation, ?string $affiliate, string $expected): void {
    expect(Validity::fromStatuses($affiliation, $affiliate))->toBe($expected);
})->with([
    'activa y afiliado activo' => ['ACTIVA', 'ACTIVO', Validity::VALID],
    'activa sin afiliado encontrado' => ['ACTIVA', null, Validity::VALID],
    'pre-aprobada' => ['PRE-APROBADA', 'PRE-APROBADA', Validity::VALID],
    'minúsculas y espacios' => [' activa ', 'activo', Validity::VALID],
    'afiliación excluida' => ['EXCLUIDO', 'ACTIVO', Validity::NOT_VALID],
    'afiliado excluido de una afiliación activa' => ['ACTIVA', 'EXCLUIDO', Validity::NOT_VALID],
    'anulada' => ['ANULADA', null, Validity::NOT_VALID],
    'sin estatus' => [null, null, Validity::NOT_VALID],
]);

it('no se fía de la copia status_affiliation del paciente', function (): void {
    $excluido = TelemedicinePatient::query()
        ->whereHas('afilliation', fn ($query) => $query->where('status', 'EXCLUIDO'))
        ->first();

    if ($excluido === null) {
        $this->markTestSkipped('No hay paciente con afiliación excluida.');
    }

    TelemedicinePatient::query()->whereKey($excluido->id)->update(['status_affiliation' => 'ACTIVO']);

    expect(Validity::label($excluido->fresh()))->toBe(Validity::NOT_VALID);
});

it('sin afiliación ligada dice «Sin afiliación»', function (): void {
    $sin = TelemedicinePatient::query()->whereNull('afilliation_id')->whereNull('afilliation_corporate_id')->first();

    if ($sin === null) {
        $this->markTestSkipped('No hay paciente sin afiliación.');
    }

    expect(Validity::label($sin))->toBe(Validity::NO_AFFILIATION)
        ->and(Validity::color(Validity::NO_AFFILIATION))->toBe('gray');
});

it('la carga por lote da el mismo afiliado que la consulta por paciente, sin una consulta por fila', function (): void {
    $patients = TelemedicinePatient::query()->orderBy('id')->limit(25)->get();

    $uno = $patients->mapWithKeys(fn (TelemedicinePatient $patient): array => [
        $patient->id => [
            TelemedicinePatientPlanBridge::linkedAffiliate($patient->replicate()->setAttribute('id', $patient->id)->forceFill(['exists' => true]))?->id,
        ],
    ]);

    $lote = TelemedicinePatient::query()->orderBy('id')->limit(25)->get();

    DB::flushQueryLog();
    DB::enableQueryLog();
    Validity::preload($lote);
    $labels = $lote->map(fn (TelemedicinePatient $patient): string => Validity::label($patient));
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    $porPaciente = TelemedicinePatient::query()->orderBy('id')->limit(25)->get()
        ->mapWithKeys(fn (TelemedicinePatient $patient): array => [$patient->id => [
            TelemedicinePatientPlanBridge::linkedAffiliate($patient)?->id,
            TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient)?->id,
        ]]);

    $porLote = $lote->mapWithKeys(fn (TelemedicinePatient $patient): array => [$patient->id => [
        TelemedicinePatientPlanBridge::linkedAffiliate($patient)?->id,
        TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient)?->id,
    ]]);

    expect($porLote->all())->toBe($porPaciente->all())
        ->and($labels)->toHaveCount($lote->count())
        ->and($queries)->toBeLessThanOrEqual(4);
});

/*
 * ---------------------------------------------------------------------------
 * Lo que ve cada médico en Telemedicina → Pacientes
 * ---------------------------------------------------------------------------
 */

it('el médico de ATENMEDI no ve las columnas de afiliación y sí la señal de vigencia', function (): void {
    $atenmediDoctor = TelemedicineDoctor::query()->where('supplier_id', atenmediSupplierId())->first();

    if ($atenmediDoctor === null) {
        $this->markTestSkipped('No hay médico de ATENMEDI.');
    }

    $this->actingAs(telemedicineUser($atenmediDoctor->id));

    $componente = Livewire::test(ListTelemedicinePatients::class)->assertOk();

    foreach (['specific_business_unit', 'plan.description', 'coverage.price', 'code_affiliation', 'type_affiliation', 'status_affiliation'] as $column) {
        $componente->assertTableColumnHidden($column);
    }

    $componente->assertTableColumnVisible('affiliation_validity');
});

it('el médico de TDG sigue viendo todo y no ve la señal', function (): void {
    $tdgDoctor = TelemedicineDoctor::query()->where('managed_by', 'TDG')->whereNull('supplier_id')->first();

    if ($tdgDoctor === null) {
        $this->markTestSkipped('No hay médico de TDG.');
    }

    $this->actingAs(telemedicineUser($tdgDoctor->id));

    $componente = Livewire::test(ListTelemedicinePatients::class)->assertOk();

    foreach (['specific_business_unit', 'plan.description', 'coverage.price', 'code_affiliation', 'type_affiliation', 'status_affiliation'] as $column) {
        $componente->assertTableColumnVisible($column);
    }

    $componente->assertTableColumnHidden('affiliation_validity');
});

it('en la ficha, ATENMEDI no ve las pestañas Afiliación ni Beneficios del plan, y sí la señal', function (): void {
    $atenmediDoctor = TelemedicineDoctor::query()->where('supplier_id', atenmediSupplierId())->first();
    $tdgDoctor = TelemedicineDoctor::query()->where('managed_by', 'TDG')->whereNull('supplier_id')->first();
    $patient = TelemedicinePatient::query()->whereNotNull('afilliation_corporate_id')->where('type_affiliation', 'CORPORATIVO')->first();

    if ($atenmediDoctor === null || $tdgDoctor === null || $patient === null) {
        $this->markTestSkipped('Faltan médicos o un paciente corporativo.');
    }

    $this->actingAs(telemedicineUser($atenmediDoctor->id));

    Livewire::test(ViewTelemedicinePatient::class, ['record' => $patient->getKey()])
        ->assertOk()
        ->assertSee('Afiliación vigente')
        ->assertDontSee('Plan, cobertura y datos de afiliación cuando aplica.')
        ->assertDontSee('Uso clínico y límite comercial en una sola lista.');

    AtenmediAccess::flush();
    $this->actingAs(telemedicineUser($tdgDoctor->id));

    Livewire::test(ViewTelemedicinePatient::class, ['record' => $patient->getKey()])
        ->assertOk()
        ->assertDontSee('Afiliación vigente')
        ->assertSee('Plan, cobertura y datos de afiliación cuando aplica.');
});
