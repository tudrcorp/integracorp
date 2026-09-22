<?php

declare(strict_types=1);

use App\Jobs\CreateAvisoDeCobro;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\User;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use App\Support\AffiliationCorporates\CorporateAffiliateUpgradeManager;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Esquema mínimo en una conexión sqlite `:memory:` propia: la config cacheada
 * apunta a MySQL y este test escribe, así que nunca debe tocar la base real.
 */
function seedUpgradeScenario(): void
{
    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name_corporate')->nullable();
        $table->string('rif')->nullable();
        $table->string('address')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->integer('poblation')->default(0);
        $table->string('fee_anual')->nullable();
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('first_name')->nullable();
        $table->string('last_name')->nullable();
        $table->string('age')->nullable();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->decimal('subtotal_anual', 8, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->decimal('subtotal_payment_frequency', 8, 2)->default(0);
        $table->decimal('subtotal_daily', 8, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('upgrade_benefits', function (Blueprint $table): void {
        $table->id();
        $table->string('code');
        $table->string('description');
        $table->decimal('price', 8, 2)->default(0);
        $table->string('status')->default('ACTIVO');
        $table->timestamps();
    });

    Schema::create('affiliate_corporate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_corporate_id');
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('upgrade_benefit_id')->nullable();
        $table->string('name', 120);
        $table->decimal('amount', 10, 2);
        $table->string('status', 20)->default('ACTIVO');
        $table->string('created_by')->nullable();
        $table->string('updated_by')->nullable();
        $table->timestamp('deactivated_at')->nullable();
        $table->string('deactivated_by')->nullable();
        $table->timestamps();
    });

    Schema::create('collections', function (Blueprint $table): void {
        $table->id();
        $table->string('collection_invoice_number')->nullable();
        $table->string('affiliation_code')->nullable();
        $table->string('affiliate_full_name')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->string('next_payment_date')->nullable();
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('afilliation_corporate_plans', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->timestamps();
    });

    /** `SecurityAudit` escribe aquí en cada acción auditada. */
    Schema::create('logs', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('user_id')->nullable();
        $table->string('action')->nullable();
        $table->string('route')->nullable();
        $table->text('response')->nullable();
        $table->string('method')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->timestamps();
    });

    DB::table('affiliation_corporates')->insert([
        ['id' => 10, 'code' => 'TDEC-COR-00010', 'name_corporate' => 'EMPRESA DEMO', 'payment_frequency' => 'TRIMESTRAL', 'poblation' => 2, 'fee_anual' => '1000', 'total_amount' => 250],
        ['id' => 11, 'code' => 'TDEC-COR-00011', 'name_corporate' => 'OTRA EMPRESA', 'payment_frequency' => 'ANUAL', 'poblation' => 1, 'fee_anual' => '300', 'total_amount' => 300],
    ]);

    DB::table('affiliate_corporates')->insert([
        ['id' => 1, 'affiliation_corporate_id' => 10, 'first_name' => 'ANA', 'last_name' => 'PEREZ', 'age' => '30', 'fee' => 500, 'subtotal_anual' => 500, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 125, 'status' => 'ACTIVO'],
        ['id' => 2, 'affiliation_corporate_id' => 10, 'first_name' => 'LUIS', 'last_name' => 'GOMEZ', 'age' => '40', 'fee' => 500, 'subtotal_anual' => 500, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 125, 'status' => 'PRE-APROBADA'],
        ['id' => 3, 'affiliation_corporate_id' => 10, 'first_name' => 'JOSE', 'last_name' => 'DIAZ', 'age' => '50', 'fee' => 400, 'subtotal_anual' => 400, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 100, 'status' => 'INACTIVO'],
        ['id' => 4, 'affiliation_corporate_id' => 10, 'first_name' => 'EVA', 'last_name' => 'RUIZ', 'age' => '20', 'fee' => 200, 'subtotal_anual' => 200, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 50, 'status' => 'PRE-AFILIADO'],
        ['id' => 5, 'affiliation_corporate_id' => 11, 'first_name' => 'AJENO', 'last_name' => 'OTRO', 'age' => '33', 'fee' => 300, 'subtotal_anual' => 300, 'payment_frequency' => 'ANUAL', 'subtotal_payment_frequency' => 300, 'status' => 'ACTIVO'],
    ]);

    DB::table('upgrade_benefits')->insert([
        'id' => 1, 'code' => '1111', 'description' => 'prueba', 'price' => 15, 'status' => 'ACTIVO',
    ]);

    DB::table('collections')->insert([
        ['id' => 100, 'collection_invoice_number' => 'AC-0100', 'affiliation_code' => 'TDEC-COR-00010', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '01/10/2026', 'total_amount' => 250, 'status' => 'POR PAGAR'],
        ['id' => 101, 'collection_invoice_number' => 'AC-0101', 'affiliation_code' => 'TDEC-COR-00010', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '01/01/2027', 'total_amount' => 250, 'status' => 'POR PAGAR'],
        ['id' => 102, 'collection_invoice_number' => 'AC-0102', 'affiliation_code' => 'TDEC-COR-00010', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '01/07/2026', 'total_amount' => 250, 'status' => 'PAGADO'],
    ]);
}

function upgradeOwner(int $id = 10): AffiliationCorporate
{
    return AffiliationCorporate::query()->findOrFail($id);
}

function affiliateFee(int $id): float
{
    return (float) AffiliateCorporate::query()->findOrFail($id)->fee;
}

function collectionTotal(int $id): float
{
    return (float) DB::table('collections')->where('id', $id)->value('total_amount');
}

beforeEach(function (): void {
    config()->set('database.connections.corporate_upgrades_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'corporate_upgrades_testing');
    DB::purge('corporate_upgrades_testing');
    DB::setDefaultConnection('corporate_upgrades_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    seedUpgradeScenario();
    Bus::fake([CreateAvisoDeCobro::class]);
    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
});

afterEach(function (): void {
    DB::purge('corporate_upgrades_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('suma el upgrade a la tarifa del afiliado, a la afiliación y a los avisos pendientes', function (): void {
    $result = CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [
        ['name' => '  odontología   ampliada ', 'amount' => '50'],
    ]);

    $affiliate = AffiliateCorporate::query()->findOrFail(1);
    $owner = upgradeOwner();

    expect($result['upgrades_created'])->toBe(1)
        ->and($result['annual_delta'])->toBe(50.0)
        ->and($result['collections_adjusted'])->toBe(2)
        ->and((float) $affiliate->fee)->toBe(550.0)
        ->and((float) $affiliate->subtotal_anual)->toBe(550.0)
        ->and((float) $affiliate->subtotal_payment_frequency)->toBe(137.5)
        ->and((float) $owner->fee_anual)->toBe(1050.0)
        ->and((float) $owner->total_amount)->toBe(262.5)
        ->and(collectionTotal(100))->toBe(262.5)
        ->and(collectionTotal(101))->toBe(262.5)
        ->and(collectionTotal(102))->toBe(250.0);

    $upgrade = AffiliateCorporateUpgrade::query()->sole();

    expect($upgrade->name)->toBe('ODONTOLOGÍA AMPLIADA')
        ->and($upgrade->created_by)->toBe('Analista Negocios')
        ->and(DB::table('upgrade_benefits')->where('description', 'ODONTOLOGÍA AMPLIADA')->value('price'))->toEqual(50)
        ->and((int) $upgrade->upgrade_benefit_id)->toBe((int) DB::table('upgrade_benefits')->where('description', 'ODONTOLOGÍA AMPLIADA')->value('id'));

    Bus::assertDispatchedTimes(CreateAvisoDeCobro::class, 2);
});

it('regenera el aviso con el total ajustado y la línea del upgrade', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [['name' => 'Odontología', 'amount' => 40]]);

    Bus::assertDispatched(CreateAvisoDeCobro::class, function (CreateAvisoDeCobro $job): bool {
        $data = (fn (): array => $this->data)->call($job);

        return $data['invoice_number'] === 'AC-0100'
            && (float) $data['total_amount'] === 260.0
            && $data['upgrades'] === [[
                'name' => 'ODONTOLOGÍA',
                'affiliates' => 1,
                'annual_amount' => 40.0,
                'amount' => 10.0,
            ]];
    });
});

it('selecciona el upgrade del catálogo cuando ya existe, sin duplicarlo', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [['name' => 'PRUEBA', 'amount' => 20]]);

    expect(DB::table('upgrade_benefits')->count())->toBe(1)
        ->and((int) AffiliateCorporateUpgrade::query()->sole()->upgrade_benefit_id)->toBe(1)
        ->and(CorporateAffiliateUpgradeManager::catalogPriceFor(' prueba '))->toBe(15.0);
});

it('agrega varios upgrades a varios afiliados y solo cuenta en la afiliación a la población vigente', function (): void {
    $result = CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1, 2, 3, 4], [
        ['name' => 'Odontología', 'amount' => 30],
        ['name' => 'Óptica', 'amount' => 20],
    ]);

    expect($result['upgrades_created'])->toBe(6)
        ->and($result['affiliates_updated'])->toBe(3)
        ->and(affiliateFee(1))->toBe(550.0)
        ->and(affiliateFee(2))->toBe(550.0)
        ->and(affiliateFee(3))->toBe(400.0)
        ->and(affiliateFee(4))->toBe(250.0)
        ->and((float) upgradeOwner()->fee_anual)->toBe(1100.0)
        ->and(collectionTotal(100))->toBe(275.0)
        ->and(collect($result['skipped'])->pluck('reason')->all())->toBe([CorporateAffiliateUpgradeManager::SKIP_AFFILIATE_INACTIVE]);
});

it('no repite un upgrade que el afiliado ya tiene activo', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [['name' => 'Odontología', 'amount' => 30]]);
    $result = CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1, 2], [['name' => 'odontología', 'amount' => 30]]);

    expect($result['upgrades_created'])->toBe(1)
        ->and(affiliateFee(1))->toBe(530.0)
        ->and(affiliateFee(2))->toBe(530.0)
        ->and($result['skipped'][0]['reason'])->toBe(CorporateAffiliateUpgradeManager::SKIP_ALREADY_ACTIVE);
});

it('ignora a los afiliados de otra afiliación', function (): void {
    $result = CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1, 5], [['name' => 'Óptica', 'amount' => 10]]);

    expect(affiliateFee(5))->toBe(300.0)
        ->and((float) upgradeOwner(11)->fee_anual)->toBe(300.0)
        ->and($result['skipped'][0]['reason'])->toBe(CorporateAffiliateUpgradeManager::SKIP_NOT_IN_AFFILIATION);
});

it('rechaza datos inválidos sin escribir nada', function (array $items): void {
    expect(fn () => CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], $items))
        ->toThrow(InvalidArgumentException::class);

    expect(AffiliateCorporateUpgrade::query()->count())->toBe(0)
        ->and(affiliateFee(1))->toBe(500.0)
        ->and(DB::table('upgrade_benefits')->count())->toBe(1);
})->with([
    'sin upgrades' => [[]],
    'nombre vacío' => [[['name' => '   ', 'amount' => 10]]],
    'monto cero' => [[['name' => 'Óptica', 'amount' => 0]]],
    'monto negativo' => [[['name' => 'Óptica', 'amount' => -5]]],
    'monto no numérico' => [[['name' => 'Óptica', 'amount' => 'diez']]],
    'monto excesivo' => [[['name' => 'Óptica', 'amount' => 100000]]],
    'repetido con otra grafía' => [[['name' => 'Óptica', 'amount' => 5], ['name' => ' óptica ', 'amount' => 5]]],
]);

it('quita el upgrade y revierte los montos exactamente', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [
        ['name' => 'Odontología', 'amount' => 30],
        ['name' => 'Óptica', 'amount' => 20],
    ]);
    $optica = AffiliateCorporateUpgrade::query()->where('name', 'ÓPTICA')->sole();

    $result = CorporateAffiliateUpgradeManager::deactivate(upgradeOwner(), [$optica->id]);

    expect($result['upgrades_removed'])->toBe(1)
        ->and($result['annual_delta'])->toBe(-20.0)
        ->and(affiliateFee(1))->toBe(530.0)
        ->and((float) upgradeOwner()->fee_anual)->toBe(1030.0)
        ->and(collectionTotal(100))->toBe(257.5)
        ->and(collectionTotal(102))->toBe(250.0)
        ->and($optica->fresh()->status)->toBe(AffiliateCorporateUpgrade::STATUS_INACTIVE)
        ->and($optica->fresh()->deactivated_by)->toBe('Analista Negocios')
        ->and(CorporateAffiliateUpgradeManager::activeTotalFor(AffiliateCorporate::query()->findOrFail(1)))->toBe(30.0);
});

it('quita en lote por nombre solo a los seleccionados que lo tienen', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1, 2], [['name' => 'Odontología', 'amount' => 30]]);

    expect(CorporateAffiliateUpgradeManager::activeNamesAmong(upgradeOwner(), [1, 2, 4]))->toBe(['ODONTOLOGÍA' => 2]);

    $ids = CorporateAffiliateUpgradeManager::activeUpgradeIdsForNames(upgradeOwner(), [1], ['odontología']);
    CorporateAffiliateUpgradeManager::deactivate(upgradeOwner(), $ids);

    expect(affiliateFee(1))->toBe(500.0)
        ->and(affiliateFee(2))->toBe(530.0)
        ->and((float) upgradeOwner()->fee_anual)->toBe(1030.0);
});

it('no quita dos veces el mismo upgrade', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(), [1], [['name' => 'Óptica', 'amount' => 20]]);
    $id = AffiliateCorporateUpgrade::query()->sole()->id;

    CorporateAffiliateUpgradeManager::deactivate(upgradeOwner(), [$id]);

    expect(fn () => CorporateAffiliateUpgradeManager::deactivate(upgradeOwner(), [$id]))
        ->toThrow(RuntimeException::class, 'ya no están activos');

    expect(affiliateFee(1))->toBe(500.0)
        ->and((float) upgradeOwner()->fee_anual)->toBe(1000.0);
});

it('no permite quitar upgrades de otra afiliación', function (): void {
    CorporateAffiliateUpgradeManager::add(upgradeOwner(11), [5], [['name' => 'Óptica', 'amount' => 20]]);
    $id = AffiliateCorporateUpgrade::query()->sole()->id;

    expect(fn () => CorporateAffiliateUpgradeManager::deactivate(upgradeOwner(10), [$id]))
        ->toThrow(RuntimeException::class);

    expect(affiliateFee(5))->toBe(320.0);
});

it('la sincronización con la afiliación conserva los upgrades en la tarifa esperada', function (): void {
    $affiliate = new AffiliateCorporate(['fee' => 427, 'plan_id' => 5, 'coverage_id' => 3]);
    $affiliate->setRawAttributes([...$affiliate->getAttributes(), 'active_upgrades_total' => '50.00']);
    $planRow = new AfilliationCorporatePlan(['plan_id' => 5, 'coverage_id' => 3, 'fee' => 377]);

    expect(CorporateAffiliatePlanSynchronizer::expectedFeeFor($affiliate, $planRow))->toBe(427.0)
        ->and(CorporateAffiliatePlanSynchronizer::expectedFeeFor(new AffiliateCorporate(['fee' => 377]), $planRow))->toBe(377.0);
});

it('registra el permiso asignable en el módulo de Negocios', function (): void {
    $definition = BusinessFilamentActionPermissionRegistry::all()[BusinessFilamentActionPermissionRegistry::MANAGE_CORPORATE_AFFILIATE_UPGRADES] ?? null;

    expect($definition)->not->toBeNull()
        ->and($definition['modules'])->toBe(['NEGOCIOS'])
        ->and($definition['group'])->toBe('AFILIACIONES');
});
