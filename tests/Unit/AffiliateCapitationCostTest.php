<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CapitationPlan;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\Plan;
use App\Models\PlanGenerator;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Business\CapitationPlans\AffiliateCapitationCost;
use App\Support\Business\CapitationPlans\CapitationPlanWriter;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(Tests\TestCase::class);

/**
 * Escribe en base de datos: todo ocurre dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    AffiliateCapitationCost::flush();
    Filament::setCurrentPanel('business');
    test()->actingAs(User::factory()->make([
        'id' => 999_999_081,
        'name' => 'QA CAPITADO COSTO',
        'email' => 'qa.capitado.costo@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]));
});

afterEach(function (): void {
    AffiliateCapitationCost::flush();
    DB::rollBack();
});

function capProveedor(string $alias, bool $outsourcing = true): Supplier
{
    return Supplier::query()->create([
        'name' => 'QA CAPITADO COSTO '.$alias,
        'rif' => 'J-'.random_int(10000000, 99999999),
        'integracorp_alias' => $alias,
        'is_outsourcing_medical_department' => $outsourcing,
    ]);
}

/**
 * @return array{0: int, 1: int}
 */
function capPlanesTdec(): array
{
    $ids = Plan::query()->where('status', 'ACTIVO')->orderBy('id')->limit(2)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();

    return [$ids[0], $ids[1]];
}

/**
 * @param  list<int>  $planIds
 * @param  array<string, mixed>  $overrides
 */
function capPlan(Supplier $supplier, array $planIds, string $cost = '4.45', array $overrides = []): CapitationPlan
{
    return CapitationPlanWriter::save(null, [
        'provider_key' => 'supplier:'.$supplier->id,
        'name' => 'Capitado '.uniqid(),
        'cost_per_affiliate_usd' => $cost,
        'payment_frequency' => 'MENSUAL',
        'status' => CapitationPlan::STATUS_ACTIVE,
        'valid_from' => null,
        'valid_until' => null,
        'negotiation_type' => 'Tarifa fija mensual por afiliado asignado.',
        'benefits' => [['benefit_id' => null, 'custom_name' => 'Consulta', 'note' => null]],
        'covered_plan_ids' => $planIds,
        ...$overrides,
    ], auth()->user());
}

function capAlias(): string
{
    return 'QACAP'.strtoupper(substr(uniqid(), -6));
}

it('lee el campo de proveedores en cualquiera de sus formas guardadas', function (mixed $value, array $expected): void {
    expect(AffiliateCapitationCost::aliases($value))->toBe($expected);
})->with([
    'arreglo' => [['ATENMEDI', 'ILS'], ['ATENMEDI', 'ILS']],
    'json' => ['["ATENMEDI", "ILS"]', ['ATENMEDI', 'ILS']],
    'texto vacío' => ['', []],
    'nulo' => [null, []],
    'json vacío' => ['[]', []],
    'separado por comas' => ['atenmedi , ils', ['ATENMEDI', 'ILS']],
    'espacios y repetidos' => [['  atenmedi ', 'ATENMEDI', null, ['x']], ['ATENMEDI']],
]);

it('devuelve el costo configurado tal cual cuando el proveedor Outsourcing cubre el plan del afiliado', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planA], '60', ['payment_frequency' => 'SEMESTRAL']);

    $result = AffiliateCapitationCost::resolve([$alias, 'ILS'], $planA, true);

    expect($result->amount)->toBe(60.0)
        ->and($result->applies())->toBeTrue()
        ->and($result->summary())->toContain($plan->name)
        ->and($result->summary())->toContain('semestral');
});

it('es 0.0 en cada caso en que no aplica', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);
    $fuera = capAlias();
    capProveedor($fuera, outsourcing: false);

    expect(AffiliateCapitationCost::resolve([$alias], $planA, false)->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::resolve([$alias], $planA, false)->reason)->toBe('Afiliado no activo')
        ->and(AffiliateCapitationCost::resolve(['ILS', 'TDEC'], $planA, true)->reason)->toBe('Sin proveedor Outsourcing')
        ->and(AffiliateCapitationCost::resolve([$fuera], $planA, true)->reason)->toBe('Sin proveedor Outsourcing')
        ->and(AffiliateCapitationCost::resolve([$alias], null, true)->reason)->toBe('Sin plan TDEC asignado')
        ->and(AffiliateCapitationCost::resolve([$alias], $planB, true)->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::resolve([$alias], $planB, true)->reason)->toBe('El proveedor no tiene plan de capitado vigente para este plan')
        ->and(AffiliateCapitationCost::resolve('', $planA, true)->amount)->toBe(0.0);
});

it('respeta la vigencia, el estado y la eliminación del plan de capitado', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planA], '5', ['valid_from' => '2026-11-01', 'valid_until' => '2026-12-31']);

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2026-10-31'))->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2026-11-01'))->amount)->toBe(5.0)
        ->and(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2026-12-31'))->amount)->toBe(5.0)
        ->and(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2027-01-01'))->amount)->toBe(0.0);

    CapitationPlanWriter::save($plan, [...capPlanData($plan), 'status' => CapitationPlan::STATUS_INACTIVE], auth()->user());

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2026-11-15'))->amount)->toBe(0.0);

    CapitationPlanWriter::save($plan, [...capPlanData($plan), 'status' => CapitationPlan::STATUS_ACTIVE], auth()->user());
    CapitationPlanWriter::delete($plan->fresh(), 'Prueba de eliminación del plan.', auth()->user());

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true, CarbonImmutable::parse('2026-11-15'))->amount)->toBe(0.0);
});

it('es 0.0 si el proveedor salió de Outsourcing o fue eliminado', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $supplier = capProveedor($alias);
    capPlan($supplier, [$planA]);

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true)->amount)->toBe(4.45);

    $supplier->forceFill(['is_outsourcing_medical_department' => false])->save();
    AffiliateCapitationCost::flush();

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true)->amount)->toBe(0.0);

    $supplier->forceFill(['is_outsourcing_medical_department' => true])->save();
    $supplier->delete();
    AffiliateCapitationCost::flush();

    expect(AffiliateCapitationCost::resolve([$alias], $planA, true)->amount)->toBe(0.0);
});

it('suma si la afiliación lleva dos proveedores Outsourcing que aplican', function (): void {
    [$planA] = capPlanesTdec();
    $first = capAlias();
    $second = capAlias();
    capPlan(capProveedor($first), [$planA], '4.45');
    capPlan(capProveedor($second), [$planA], '2.10');

    $result = AffiliateCapitationCost::resolve([$first, $second], $planA, true);

    expect($result->amount)->toBe(6.55)
        ->and($result->lines)->toHaveCount(2);
});

it('no adivina si dos planes cruzados del mismo proveedor cubren el mismo plan', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $supplier = capProveedor($alias);
    $plan = capPlan($supplier, [$planA]);

    /** El writer lo impide; se fuerza por SQL para simular datos viejos o una carrera. */
    $copy = $plan->replicate(['benefits_count']);
    $copy->name = 'Copia cruzada';
    $copy->save();
    DB::table('capitation_plan_covered_plans')->insert(['capitation_plan_id' => $copy->id, 'plan_id' => $planA, 'created_at' => now(), 'updated_at' => now()]);
    AffiliateCapitationCost::flush();

    $result = AffiliateCapitationCost::resolve([$alias], $planA, true);

    expect($result->amount)->toBe(0.0)
        ->and($result->reason)->toContain('hay más de uno vigente');
});

it('el plan de capitado exige planes TDEC y no deja cruzar coberturas del mismo proveedor', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $supplier = capProveedor(capAlias());

    expect(fn () => capPlan($supplier, []))->toThrow(ValidationException::class, 'al menos un plan TDEC')
        ->and(fn () => capPlan($supplier, [999_999_999]))->toThrow(ValidationException::class, 'ya no existe');

    capPlan($supplier, [$planA], '4', ['valid_from' => '2026-01-01', 'valid_until' => '2026-06-30']);

    expect(fn () => capPlan($supplier, [$planA, $planB], '5', ['valid_from' => '2026-06-30']))
        ->toThrow(ValidationException::class, 'se cruza');

    $sucesor = capPlan($supplier, [$planA], '5', ['valid_from' => '2026-07-01']);
    $inactivo = capPlan($supplier, [$planA], '6', ['status' => CapitationPlan::STATUS_INACTIVE]);
    $otro = capPlan($supplier, [$planB], '7');

    expect($sucesor->coveredPlans->modelKeys())->toBe([$planA])
        ->and($inactivo->exists)->toBeTrue()
        ->and($otro->exists)->toBeTrue()
        ->and(CapitationPlanWriter::save($sucesor, [...capPlanData($sucesor), 'cost_per_affiliate_usd' => '5.5'], auth()->user())->cost_per_affiliate_usd)->toBe('5.50');
});

it('afiliado individual: exige afiliado ACTIVO y afiliación ACTIVA, y usa su plan', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);

    $affiliation = new Affiliation(['service_providers' => [$alias, 'ILS'], 'status' => 'ACTIVA', 'plan_id' => $planA]);
    $affiliate = new Affiliate(['status' => 'ACTIVO', 'plan_id' => null]);
    $affiliate->setRelation('affiliation', $affiliation);

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(4.45);

    $affiliate->plan_id = $planB;
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);

    $affiliate->plan_id = $planA;
    $affiliate->status = 'EXCLUIDO';
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);

    $affiliate->status = 'ACTIVO';
    $affiliation->status = 'PRE-APROBADA';
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);

    $affiliation->status = 'ACTIVA';
    $affiliate->deleted_at = now();
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);

    $orphan = new Affiliate(['status' => 'ACTIVO', 'plan_id' => $planA]);
    $orphan->setRelation('affiliation', null);
    expect(AffiliateCapitationCost::forAffiliate($orphan)->reason)->toBe('Sin afiliación activa');
});

it('afiliado corporativo: sale de la afiliación corporativa y del plan de cada afiliado', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);

    $affiliation = new AffiliationCorporate(['service_providers' => '["'.$alias.'"]', 'status' => 'ACTIVA']);
    $affiliate = new AffiliateCorporate(['status' => 'ACTIVO', 'plan_id' => $planA]);
    $affiliate->setRelation('affiliationCorporate', $affiliation);

    $other = new AffiliateCorporate(['status' => 'ACTIVO', 'plan_id' => $planB]);
    $inactive = new AffiliateCorporate(['status' => 'INACTIVO', 'plan_id' => $planA]);

    expect(AffiliateCapitationCost::forAffiliateCorporate($affiliate)->amount)->toBe(4.45)
        ->and(AffiliateCapitationCost::forAffiliateCorporate($other, $affiliation)->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::forAffiliateCorporate($inactive, $affiliation)->amount)->toBe(0.0);

    $affiliation->status = 'EXCLUIDO';
    expect(AffiliateCapitationCost::forAffiliateCorporate($affiliate)->amount)->toBe(0.0);
});

it('Nuevos Negocios: proveedor de la empresa y plan de catálogo de su generador', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);

    $company = new Company(['service_providers' => [$alias]]);
    $company->setRelation('planGenerator', new PlanGenerator(['catalog_plan_id' => $planA, 'plan_id' => $planB]));
    $associate = new CompanyAssociate(['status' => 'ACTIVO-SIN-VAUCHER-ILS']);
    $associate->setRelation('company', $company);

    expect(AffiliateCapitationCost::forCompanyAssociate($associate)->amount)->toBe(4.45);

    $company->setRelation('planGenerator', new PlanGenerator(['catalog_plan_id' => null, 'plan_id' => $planA]));
    expect(AffiliateCapitationCost::forCompanyAssociate($associate)->amount)->toBe(4.45);

    $associate->status = 'ANULADO';
    expect(AffiliateCapitationCost::forCompanyAssociate($associate)->amount)->toBe(0.0);

    $associate->status = 'ACTIVO';
    $company->setRelation('planGenerator', null);
    expect(AffiliateCapitationCost::forCompanyAssociate($associate)->reason)->toBe('Sin plan TDEC asignado');

    $associate->setRelation('company', null);
    expect(AffiliateCapitationCost::forCompanyAssociate($associate)->reason)->toBe('Sin empresa');
});

it('una página completa de afiliados resuelve con a lo sumo cinco consultas, sin importar las filas', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);
    AffiliateCapitationCost::flush();

    DB::flushQueryLog();
    DB::enableQueryLog();

    for ($row = 0; $row < 50; $row++) {
        AffiliateCapitationCost::resolve([$alias], $planA, true);
    }

    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($queries)->toBeLessThanOrEqual(5);
});

it('la columna y el ajuste masivo están en las seis tablas de afiliados de Operaciones y Negocios', function (string $file, string $method): void {
    $source = (string) file_get_contents(base_path($file));

    expect($source)->toContain('CapitationCostColumn::make(')
        ->and($source)->toContain('AffiliateCapitationCost::'.$method.'(')
        ->and($source)->toContain('CapitationCostColumn::bulkAction()');
})->with([
    'Operaciones individuales' => ['app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php', 'forAffiliate'],
    'Operaciones corporativos' => ['app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php', 'forAffiliateCorporate'],
    'Operaciones Nuevos Negocios' => ['app/Filament/Operations/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php', 'forCompanyAssociate'],
    'Negocios individuales' => ['app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php', 'forAffiliate'],
    'Negocios corporativos' => ['app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php', 'forAffiliateCorporate'],
    'Negocios Nuevos Negocios' => ['app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php', 'forCompanyAssociate'],
]);

it('la tabla de afiliados de Operaciones muestra el monto del plan de capitado', function (): void {
    $affiliate = Affiliate::query()
        ->where('status', 'ACTIVO')
        ->whereNotNull('plan_id')
        ->whereHas('affiliation', fn ($query) => $query->where('status', 'ACTIVA'))
        ->with('affiliation')
        ->first();

    if (! $affiliate instanceof Affiliate) {
        test()->markTestSkipped('No hay afiliados individuales activos en la base para probar la tabla.');
    }

    $alias = capAlias();
    capPlan(capProveedor($alias), [(int) $affiliate->plan_id], '7.77');
    $affiliate->affiliation->forceFill(['service_providers' => [$alias]])->saveQuietly();
    AffiliateCapitationCost::flush();

    Filament::setCurrentPanel('operations');

    Livewire\Livewire::test(App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class)
        ->searchTable((string) $affiliate->nro_identificacion)
        ->assertSee('Capitado por afiliado')
        ->assertSee('7,77')
        ->assertSee('QA CAPITADO COSTO '.$alias);
});

/**
 * Afiliado individual en memoria con clave propia: el ajuste se guarda por `tipo:id`.
 */
function capAfiliado(string $alias, int $planId, string $status = 'ACTIVO', ?int $id = null): Affiliate
{
    $affiliate = new Affiliate(['status' => $status, 'plan_id' => $planId, 'full_name' => 'QA AJUSTE '.uniqid()]);
    $affiliate->id = $id ?? random_int(900_000_000, 999_999_999);
    $affiliate->exists = true;
    $affiliate->setRelation('affiliation', new Affiliation(['service_providers' => [$alias], 'status' => 'ACTIVA', 'plan_id' => $planId]));

    return $affiliate;
}

it('el monto negociado reemplaza al del plan y deja historial y auditoría', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $affiliate = capAfiliado($alias, $planA);

    expect(App\Support\Business\CapitationPlans\CapitationOverrideWriter::set([$affiliate], '3.10', '  Negociación directa   por volumen.  ', auth()->user()))->toBe(1);

    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(3.1)
        ->and($result->planAmount)->toBe(4.45)
        ->and($result->isOverridden())->toBeTrue()
        ->and($result->summary())->toContain('Negociado · plan 4,45 US$ · Negociación directa por volumen.')
        ->and(AffiliateCapitationCost::forAffiliate($affiliate, applyOverride: false)->amount)->toBe(4.45);

    $log = App\Models\AffiliateCapitationOverrideLog::query()->where('subject_type', 'affiliate')->where('subject_id', $affiliate->id)->sole();

    expect($log->action)->toBe('AJUSTE')
        ->and((float) $log->new_amount_usd)->toBe(3.1)
        ->and((float) $log->plan_amount_usd)->toBe(4.45)
        ->and($log->created_by_name)->toBe('QA CAPITADO COSTO')
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_OVERRIDE_SET')->latest('id')->value('response'))->toContain('affiliate:'.$affiliate->id);
});

it('acepta 0,00 y rechaza montos inválidos o sin razón', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);
    $affiliate = capAfiliado($alias, $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    expect(fn () => $writer::set([$affiliate], '-1', 'Razón suficientemente larga.', auth()->user()))->toThrow(ValidationException::class, 'monto válido')
        ->and(fn () => $writer::set([$affiliate], 'abc', 'Razón suficientemente larga.', auth()->user()))->toThrow(ValidationException::class, 'monto válido')
        ->and(fn () => $writer::set([$affiliate], '2', 'corta', auth()->user()))->toThrow(ValidationException::class, 'razón del ajuste');

    $writer::set([$affiliate], '0', 'Exonerado por convenio con la empresa.', auth()->user());

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::forAffiliate($affiliate)->isOverridden())->toBeTrue();
});

it('solo se ajusta con plan de capitado aplicable y es todo o nada', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA]);
    $ok = capAfiliado($alias, $planA);
    $sinPlan = capAfiliado($alias, $planB);
    $baja = capAfiliado($alias, $planA, 'EXCLUIDO');

    expect(fn () => App\Support\Business\CapitationPlans\CapitationOverrideWriter::set([$ok, $sinPlan, $baja], '2', 'Negociación por volumen con la empresa.', auth()->user()))
        ->toThrow(ValidationException::class, 'No se ajustó ninguno');

    expect(App\Models\AffiliateCapitationOverride::query()->whereIn('subject_id', [$ok->id, $sinPlan->id, $baja->id])->exists())->toBeFalse();
});

it('el ajuste no aplica si el afiliado se da de baja o el plan deja de aplicar, y vuelve si se reactiva', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planA], '4.45');
    $affiliate = capAfiliado($alias, $planA);
    App\Support\Business\CapitationPlans\CapitationOverrideWriter::set([$affiliate], '3', 'Negociación por volumen con la empresa.', auth()->user());

    $affiliate->status = 'EXCLUIDO';
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);

    $affiliate->status = 'ACTIVO';
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(3.0);

    CapitationPlanWriter::save($plan, [...capPlanData($plan), 'status' => CapitationPlan::STATUS_INACTIVE], auth()->user());
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);
});

it('el ajuste se conserva si el plan cambia de precio y lo avisa', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planA], '4.45');
    $affiliate = capAfiliado($alias, $planA);
    App\Support\Business\CapitationPlans\CapitationOverrideWriter::set([$affiliate], '3', 'Negociación por volumen con la empresa.', auth()->user());

    CapitationPlanWriter::save($plan, [...capPlanData($plan), 'cost_per_affiliate_usd' => '5'], auth()->user());
    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(3.0)
        ->and($result->planAmount)->toBe(5.0)
        ->and($result->planChangedSinceOverride())->toBeTrue()
        ->and($result->summary())->toContain('(era 4,45 US$ al negociar)');
});

it('volver al monto del plan retira el ajuste con razón y lo registra', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $first = capAfiliado($alias, $planA);
    $second = capAfiliado($alias, $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    expect(fn () => $writer::remove([$first], 'Fin de la negociación directa.', auth()->user()))->toThrow(ValidationException::class, 'ya usa el monto del plan');

    $writer::set([$first, $second], '3', 'Negociación por volumen con la empresa.', auth()->user());

    expect($writer::remove([$first, $second], 'Fin de la negociación directa.', auth()->user()))->toBe(2)
        ->and(AffiliateCapitationCost::forAffiliate($first)->amount)->toBe(4.45)
        ->and(AffiliateCapitationCost::forAffiliate($first)->isOverridden())->toBeFalse()
        ->and($writer::history($first)->pluck('action')->all())->toBe(['RETIRO', 'AJUSTE'])
        ->and((float) $writer::history($first)->first()->previous_amount_usd)->toBe(3.0);
});

it('ajustar exige el permiso asignable en Negocios y Operaciones', function (): void {
    expect(App\Support\Filament\BusinessFilamentActionPermissionRegistry::modulesForSlug('editar-capitado-por-afiliado'))->toBe(['NEGOCIOS', 'OPERACIONES'])
        ->and(App\Filament\Shared\Affiliations\CapitationOverrideActions::canEdit())->toBeTrue();

    test()->actingAs(User::factory()->make([
        'id' => 999_999_082,
        'name' => 'QA SIN PERMISO CAPITADO',
        'email' => 'qa.sinpermiso.capitado@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS', 'OPERACIONES'],
    ]));

    expect(App\Filament\Shared\Affiliations\CapitationOverrideActions::canEdit())->toBeFalse();
});

it('desde la tabla de Operaciones se ajusta un afiliado y varios a la vez', function (): void {
    $affiliates = Affiliate::query()
        ->where('status', 'ACTIVO')
        ->whereNotNull('plan_id')
        ->whereHas('affiliation', fn ($query) => $query->where('status', 'ACTIVA'))
        ->with('affiliation')
        ->limit(2)
        ->get();

    if ($affiliates->count() < 2) {
        test()->markTestSkipped('No hay dos afiliados individuales activos en la base para probar la tabla.');
    }

    $alias = capAlias();
    capPlan(capProveedor($alias), $affiliates->pluck('plan_id')->unique()->map(fn (mixed $id): int => (int) $id)->values()->all(), '7.77');
    $affiliates->each(fn (Affiliate $affiliate) => $affiliate->affiliation->forceFill(['service_providers' => [$alias]])->saveQuietly());
    AffiliateCapitationCost::flush();
    Filament::setCurrentPanel('operations');

    $first = $affiliates->first();

    Livewire\Livewire::test(App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class)
        ->searchTable((string) $first->nro_identificacion)
        ->mountAction(\Filament\Actions\Testing\TestAction::make('adjustCapitationCost')->table($first))
        ->assertSet('mountedActions.0.data.amount', '7.77')
        ->set('mountedActions.0.data.amount', '6.50')
        ->set('mountedActions.0.data.reason', 'Negociación directa con el titular.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Capitado ajustado')
        ->assertSee('6,50')
        ->assertSee('Negociado · plan 7,77 US$');

    Livewire\Livewire::test(App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class)
        ->mountTableBulkAction('adjustCapitationCostBulk', $affiliates)
        ->set('mountedActions.0.data.mode', 'set')
        ->set('mountedActions.0.data.amount', '5')
        ->set('mountedActions.0.data.reason', 'Negociación por volumen con la empresa.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('2 afiliados ajustados');

    expect(App\Models\AffiliateCapitationOverride::query()->where('subject_type', 'affiliate')->whereIn('subject_id', $affiliates->modelKeys())->pluck('amount_usd')->map(fn ($v) => (float) $v)->all())
        ->toBe([5.0, 5.0]);
    Livewire\Livewire::test(App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class)
        ->mountTableBulkAction('adjustCapitationCostBulk', $affiliates)
        ->set('mountedActions.0.data.mode', 'undo')
        ->set('mountedActions.0.data.reason', 'Se aplicó el lote por error.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Cambios deshechos en 2 afiliados');

    Livewire\Livewire::test(App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class)
        ->searchTable((string) $first->nro_identificacion)
        ->mountAction(\Filament\Actions\Testing\TestAction::make('adjustCapitationCost')->table($first))
        ->set('mountedActions.0.data.mode', 'undo')
        ->set('mountedActions.0.data.reason', 'Tampoco correspondía el ajuste individual.')
        ->callMountedAction()
        ->assertHasNoActionErrors()
        ->assertNotified('Cambio deshecho');

    /** El masivo devolvió al primero a su 6,50 individual y al segundo al plan; deshacer el individual lo deja en el plan. */
    expect(App\Models\AffiliateCapitationOverride::query()->where('subject_type', 'affiliate')->whereIn('subject_id', $affiliates->modelKeys())->exists())->toBeFalse();
});

it('deshacer vuelve paso a paso al estado exacto anterior y no revierte dos veces', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $affiliate = capAfiliado($alias, $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    $writer::set([$affiliate], '3', 'Primera negociación con la empresa.', auth()->user());
    $writer::set([$affiliate], '5', 'Segunda negociación con la empresa.', auth()->user());

    expect($writer::revert([$affiliate], 'Se cargó el monto equivocado.', auth()->user()))->toBe(1);

    $result = AffiliateCapitationCost::forAffiliate($affiliate);
    expect($result->amount)->toBe(3.0)
        ->and($result->override['reason'])->toBe('Primera negociación con la empresa.')
        ->and($result->override['plan_amount'])->toBe(4.45);

    $writer::revert([$affiliate], 'Tampoco correspondía el primero.', auth()->user());

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->isOverridden())->toBeFalse()
        ->and(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(4.45)
        ->and(fn () => $writer::revert([$affiliate], 'Intento de deshacer de más.', auth()->user()))->toThrow(ValidationException::class, 'no tiene cambios por deshacer');

    $logs = App\Models\AffiliateCapitationOverrideLog::query()->where('subject_id', $affiliate->id)->orderBy('id')->get();

    expect($logs->pluck('action')->all())->toBe(['AJUSTE', 'AJUSTE', 'REVERSION', 'REVERSION'])
        ->and($logs[0]->reverted_at)->not->toBeNull()
        ->and($logs[1]->reverted_at)->not->toBeNull()
        ->and($logs[2]->reverts_log_id)->toBe($logs[1]->id)
        ->and($logs[3]->reverts_log_id)->toBe($logs[0]->id)
        ->and($logs[2]->reverted_by_name)->toBeNull()
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_OVERRIDE_REVERTED')->exists())->toBeTrue();
});

it('deshacer un regreso al plan restaura el monto negociado y su razón', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $affiliate = capAfiliado($alias, $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    $writer::set([$affiliate], '2.5', 'Convenio especial del titular.', auth()->user());
    $writer::remove([$affiliate], 'Se retiró por error.', auth()->user());
    $writer::revert([$affiliate], 'El retiro fue un error.', auth()->user());

    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(2.5)
        ->and($result->override['reason'])->toBe('Convenio especial del titular.');
});

it('deshacer en masivo es todo o nada', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $first = capAfiliado($alias, $planA);
    $second = capAfiliado($alias, $planA);
    $untouched = capAfiliado($alias, $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    $writer::set([$first, $second], '3', 'Negociación por volumen con la empresa.', auth()->user());

    expect(fn () => $writer::revert([$first, $second, $untouched], 'Deshacer el lote completo.', auth()->user()))
        ->toThrow(ValidationException::class, 'No se deshizo ningún cambio');

    expect(AffiliateCapitationCost::forAffiliate($first)->amount)->toBe(3.0)
        ->and($writer::revert([$first, $second], 'Deshacer el lote completo.', auth()->user()))->toBe(2)
        ->and(AffiliateCapitationCost::forAffiliate($first)->amount)->toBe(4.45)
        ->and(AffiliateCapitationCost::forAffiliate($second)->amount)->toBe(4.45);
});

it('no deshace si el monto cambió por fuera del flujo o si el plan ya no aplica', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planA], '4.45');
    $writer = App\Support\Business\CapitationPlans\CapitationOverrideWriter::class;

    $tocado = capAfiliado($alias, $planA);
    $writer::set([$tocado], '3', 'Negociación por volumen con la empresa.', auth()->user());
    App\Models\AffiliateCapitationOverride::query()->where('subject_id', $tocado->id)->update(['amount_usd' => 9]);
    AffiliateCapitationCost::flush();

    expect(fn () => $writer::revert([$tocado], 'Intento de deshacer.', auth()->user()))
        ->toThrow(ValidationException::class, 'su monto cambió después');

    $retirado = capAfiliado($alias, $planA);
    $writer::set([$retirado], '3', 'Negociación por volumen con la empresa.', auth()->user());
    $writer::remove([$retirado], 'Fin de la negociación directa.', auth()->user());
    CapitationPlanWriter::save($plan, [...capPlanData($plan), 'status' => CapitationPlan::STATUS_INACTIVE], auth()->user());

    expect(fn () => $writer::revert([$retirado], 'Restaurar el convenio.', auth()->user()))
        ->toThrow(ValidationException::class, 'ya no le aplica un plan de capitado')
        ->and(fn () => $writer::revert([$retirado], 'corta', auth()->user()))->toThrow(ValidationException::class, 'razón del ajuste');
});

/**
 * @return array<string, mixed>
 */
function capPlanData(CapitationPlan $plan): array
{
    $plan->loadMissing(['benefits', 'coveredPlans']);

    return [
        'provider_key' => $plan->providerKey(),
        'name' => $plan->name,
        'cost_per_affiliate_usd' => (string) $plan->cost_per_affiliate_usd,
        'payment_frequency' => $plan->payment_frequency->value,
        'status' => $plan->status,
        'valid_from' => $plan->valid_from?->toDateString(),
        'valid_until' => $plan->valid_until?->toDateString(),
        'negotiation_type' => $plan->negotiation_type,
        'benefits' => $plan->benefits->map(fn ($benefit): array => ['benefit_id' => $benefit->benefit_id, 'custom_name' => $benefit->benefit_id === null ? $benefit->name : null, 'note' => $benefit->note])->all(),
        'covered_plan_ids' => $plan->coveredPlans->modelKeys(),
    ];
}

/**
 * Afiliado individual en memoria con varios proveedores en la afiliación.
 *
 * @param  list<string>  $aliases
 */
function capAfiliadoConProveedores(array $aliases, int $planId, string $status = 'ACTIVO'): Affiliate
{
    $affiliate = new Affiliate(['status' => $status, 'plan_id' => $planId, 'full_name' => 'QA ASIGNACION '.uniqid()]);
    $affiliate->id = random_int(900_000_000, 999_999_999);
    $affiliate->exists = true;
    $affiliate->setRelation('affiliation', new Affiliation(['service_providers' => $aliases, 'status' => 'ACTIVA', 'plan_id' => $planId]));

    return $affiliate;
}

it('el plan asignado directamente reemplaza al cálculo automático', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $aliasA = capAlias();
    $aliasB = capAlias();
    capPlan(capProveedor($aliasA), [$planA], '4.45');
    $direct = capPlan(capProveedor($aliasB), [$planB], '9.00');
    $affiliate = capAfiliadoConProveedores([$aliasA, $aliasB], $planA);

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(4.45);

    expect(App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$affiliate], $direct->id, 'Asignación directa por contrato.', auth()->user()))->toBe(1);

    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(9.0)
        ->and($result->isAssigned())->toBeTrue()
        ->and($result->lines)->toHaveCount(1)
        ->and($result->lines[0]['capitation_plan_id'])->toBe($direct->id)
        ->and($result->summary())->toStartWith('Asignado · '.$direct->name);

    $log = App\Models\AffiliateCapitationAssignmentLog::query()->where('subject_type', 'affiliate')->where('subject_id', $affiliate->id)->sole();

    expect($log->action)->toBe('ASIGNACION')
        ->and($log->new_capitation_plan_id)->toBe($direct->id)
        ->and($log->previous_capitation_plan_id)->toBeNull()
        ->and(DB::table('logs')->where('action', 'AUDIT_CAPITATION_PLAN_ASSIGNED')->latest('id')->value('response'))->toContain('affiliate:'.$affiliate->id);
});

it('exige que el proveedor del plan esté en la afiliación: sin él la lista sale vacía y no asigna a ninguno', function (): void {
    [$planA, $planB] = capPlanesTdec();
    $alias = capAlias();
    $plan = capPlan(capProveedor($alias), [$planB], '5.00');
    $ok = capAfiliadoConProveedores([$alias], $planA);
    $withoutProvider = capAfiliadoConProveedores([], $planA);

    expect(App\Support\Business\CapitationPlans\CapitationAssignmentWriter::planOptionsFor($withoutProvider))->toBe([])
        ->and(App\Support\Business\CapitationPlans\CapitationAssignmentWriter::planOptionsFor($ok))->toHaveKey($plan->id)
        ->and(fn () => App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$ok, $withoutProvider], $plan->id, 'Asignación de prueba mixta.', auth()->user()))
        ->toThrow(ValidationException::class);

    expect(App\Models\AffiliateCapitationAssignment::query()->whereIn('subject_id', [$ok->id, $withoutProvider->id])->count())->toBe(0);

    // Con el proveedor en la afiliación se asigna aunque el plan no cubra su plan TDEC.
    App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$ok], $plan->id, 'Asignación aunque no cubra el plan TDEC.', auth()->user());

    expect(AffiliateCapitationCost::forAffiliate($ok)->amount)->toBe(5.0);
});

it('no asigna planes inactivos ni de proveedores fuera de Outsourcing', function (): void {
    [$planA] = capPlanesTdec();
    $inactive = capPlan(capProveedor(capAlias()), [$planA], '5.00', ['status' => 'INACTIVO']);
    $affiliate = capAfiliadoConProveedores([], $planA);

    expect(App\Support\Business\CapitationPlans\CapitationAssignmentWriter::planOptions())->not->toHaveKey($inactive->id)
        ->and(fn () => App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$affiliate], $inactive->id, 'Intento con plan inactivo.', auth()->user()))
        ->toThrow(ValidationException::class);

    expect(App\Models\AffiliateCapitationAssignment::query()->where('subject_id', $affiliate->id)->exists())->toBeFalse();
});

it('si el plan asignado se inactiva o vence da 0,00 con aviso y no vuelve en silencio a lo automático', function (): void {
    [$planA] = capPlanesTdec();
    $aliasA = capAlias();
    $aliasB = capAlias();
    capPlan(capProveedor($aliasA), [$planA], '4.45');
    $direct = capPlan(capProveedor($aliasB), [$planA], '9.00');
    $affiliate = capAfiliadoConProveedores([$aliasA, $aliasB], $planA);
    App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$affiliate], $direct->id, 'Asignación directa por contrato.', auth()->user());

    DB::table('capitation_plans')->where('id', $direct->id)->update(['status' => 'INACTIVO']);
    AffiliateCapitationCost::flush();

    $inactive = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($inactive->amount)->toBe(0.0)
        ->and($inactive->isAssigned())->toBeTrue()
        ->and($inactive->reason)->toContain('vencido o inactivo: reasígnelo');

    DB::table('capitation_plans')->where('id', $direct->id)->update(['status' => 'ACTIVO', 'valid_until' => CarbonImmutable::now()->subDay()->toDateString()]);
    AffiliateCapitationCost::flush();

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0);
});

it('si el proveedor sale de la afiliación, el plan asignado deja de aplicar con aviso', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $direct = capPlan(capProveedor($alias), [$planA], '7.00');
    $affiliate = capAfiliadoConProveedores([$alias], $planA);
    App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$affiliate], $direct->id, 'Asignación directa por contrato.', auth()->user());

    $affiliate->affiliation->service_providers = ['OTRO'];
    AffiliateCapitationCost::flush();

    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(0.0)
        ->and($result->isAssigned())->toBeTrue()
        ->and($result->reason)->toContain('ya no está en la afiliación');
});

it('el monto negociado manda sobre el plan asignado; inactivo sigue en 0,00', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $direct = capPlan(capProveedor($alias), [$planA], '9.00');
    $affiliate = capAfiliadoConProveedores([$alias], $planA);
    App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$affiliate], $direct->id, 'Asignación directa por contrato.', auth()->user());
    App\Support\Business\CapitationPlans\CapitationOverrideWriter::set([$affiliate], '6.50', 'Negociado sobre el plan asignado.', auth()->user());

    $result = AffiliateCapitationCost::forAffiliate($affiliate);

    expect($result->amount)->toBe(6.5)
        ->and($result->planAmount)->toBe(9.0)
        ->and($result->isOverridden())->toBeTrue()
        ->and($result->isAssigned())->toBeTrue();

    $affiliate->status = 'INACTIVO';
    AffiliateCapitationCost::flush();

    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(0.0)
        ->and(AffiliateCapitationCost::forAffiliate($affiliate)->isAssigned())->toBeTrue();
});

it('quitar vuelve al cálculo automático y deshacer restaura el estado anterior paso a paso', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    capPlan(capProveedor($alias), [$planA], '4.45');
    $first = capPlan(capProveedor(capAlias()), [$planA], '8.00');
    $second = capPlan(capProveedor(capAlias()), [$planA], '9.00');
    $affiliate = capAfiliadoConProveedores([$alias, $first->supplier->integracorp_alias, $second->supplier->integracorp_alias], $planA);
    $writer = App\Support\Business\CapitationPlans\CapitationAssignmentWriter::class;
    $user = auth()->user();

    $writer::assign([$affiliate], $first->id, 'Primera asignación directa.', $user);
    $writer::assign([$affiliate], $second->id, 'Cambio al segundo plan.', $user);
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(9.0);

    $writer::revert([$affiliate], 'Deshacer el cambio de plan.', $user);
    AffiliateCapitationCost::flush();
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(8.0);

    $writer::remove([$affiliate], 'Ya no aplica la asignación.', $user);
    AffiliateCapitationCost::flush();
    $automatic = AffiliateCapitationCost::forAffiliate($affiliate);
    expect($automatic->isAssigned())->toBeFalse();

    $writer::revert([$affiliate], 'Deshacer el retiro.', $user);
    AffiliateCapitationCost::flush();
    expect(AffiliateCapitationCost::forAffiliate($affiliate)->amount)->toBe(8.0)
        ->and(fn () => $writer::remove([capAfiliadoConProveedores([$alias], $planA)], 'Retiro sin asignación previa.', $user))->toThrow(ValidationException::class)
        ->and(fn () => $writer::assign([$affiliate], $first->id, 'corta', $user))->toThrow(ValidationException::class);
});

it('también asigna a afiliados corporativos y a asociados de Nuevos Negocios', function (): void {
    [$planA] = capPlanesTdec();
    $alias = capAlias();
    $direct = capPlan(capProveedor($alias), [$planA], '3.30');

    $corporate = new AffiliateCorporate(['status' => 'ACTIVO', 'plan_id' => $planA, 'first_name' => 'QA', 'last_name' => 'CORP']);
    $corporate->id = random_int(900_000_000, 999_999_999);
    $corporate->exists = true;
    $corporate->setRelation('affiliationCorporate', new AffiliationCorporate(['service_providers' => [$alias], 'status' => 'ACTIVA']));

    App\Support\Business\CapitationPlans\CapitationAssignmentWriter::assign([$corporate], $direct->id, 'Asignación a corporativo.', auth()->user());

    expect(AffiliateCapitationCost::forAffiliateCorporate($corporate)->amount)->toBe(3.3)
        ->and(App\Models\AffiliateCapitationAssignment::query()->where('subject_type', 'affiliate_corporate')->where('subject_id', $corporate->id)->exists())->toBeTrue();
});

it('las seis tablas de afiliados ofrecen la acción masiva y la celda con su propio permiso', function (): void {
    $tables = [
        'app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
        'app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
        'app/Filament/Business/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php',
        'app/Filament/Operations/Resources/AffiliateCorporates/Tables/AffiliateCorporatesTable.php',
        'app/Filament/Operations/Resources/CompanyAssociates/Tables/CompanyAssociatesTable.php',
        'app/Filament/Operations/Resources/Affiliates/Tables/AffiliatesTable.php',
    ];

    foreach ($tables as $table) {
        expect(file_get_contents(dirname(__DIR__, 2).'/'.$table))->toContain('CapitationCostColumn::assignBulkAction()');
    }

    expect(App\Support\Filament\BusinessFilamentActionPermissionRegistry::modulesForSlug('asignar-plan-capitado-afiliado'))->toBe(['NEGOCIOS', 'OPERACIONES'])
        ->and(App\Filament\Shared\Affiliations\CapitationOverrideActions::canAssign())->toBeTrue();

    test()->actingAs(User::factory()->make([
        'id' => 999_999_082,
        'name' => 'QA SIN PERMISO',
        'email' => 'qa.sin.permiso@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['NEGOCIOS'],
    ]));

    expect(App\Filament\Shared\Affiliations\CapitationOverrideActions::canAssign())->toBeFalse();
});

it('las tablas de afiliados individuales y corporativos de Operaciones muestran la acción masiva', function (string $page): void {
    Filament::setCurrentPanel('operations');

    Livewire\Livewire::test($page)
        ->assertSuccessful()
        ->assertTableBulkActionExists('assignCapitationPlanBulk')
        ->assertTableBulkActionExists('adjustCapitationCostBulk');
})->with([
    'individuales' => [App\Filament\Operations\Resources\Affiliates\Pages\ListAffiliates::class],
    'corporativos' => [App\Filament\Operations\Resources\AffiliateCorporates\Pages\ListAffiliateCorporates::class],
]);
