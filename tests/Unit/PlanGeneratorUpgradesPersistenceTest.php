<?php

declare(strict_types=1);

use App\Models\AffiliateCorporateUpgrade;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\PlanGenerator;
use App\Models\User;
use App\Support\PlanGenerators\PlanGeneratorUpgradeAssignment;
use App\Support\PlanGenerators\PlanGeneratorUpgrades;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Conexión sqlite `:memory:` propia: la config cacheada apunta a MySQL y este
 * test escribe, así que nunca debe tocar la base real.
 */
beforeEach(function (): void {
    config()->set('database.connections.pg_upgrades_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'pg_upgrades_testing');
    DB::purge('pg_upgrades_testing');
    DB::setDefaultConnection('pg_upgrades_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    createUpgradeCatalogTestSchema();

    DB::table('upgrade_services')->insert([
        ['upgrade_id' => 1, 'name' => 'Limpieza', 'sort' => 0, 'status' => 'ACTIVO'],
        ['upgrade_id' => 1, 'name' => 'Retirado', 'sort' => 1, 'status' => 'INACTIVO'],
    ]);

    Schema::create('plan_generators', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->timestamps();
    });

    Schema::create('plan_generator_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_generator_id');
        $table->string('line_key', 36);
        $table->unsignedBigInteger('upgrade_id')->nullable();
        $table->unsignedBigInteger('upgrade_coverage_id')->nullable();
        $table->string('upgrade_code', 40)->nullable();
        $table->string('upgrade_name', 120);
        $table->decimal('coverage_amount', 12, 2)->nullable();
        $table->json('age_prices');
        $table->json('services')->nullable();
        $table->json('excluded_ranges')->nullable();
        $table->json('manual_ranges')->nullable();
        $table->unsignedSmallInteger('sort_order')->default(0);
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    /** `SecurityAudit` escribe aquí. */
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

    Schema::create('affiliations', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->string('code')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->decimal('fee_anual', 8, 2)->default(0);
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->unsignedBigInteger('affiliation_id');
        $table->string('full_name')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('age')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_id');
        upgradeAssignmentTestColumns($table);
    });

    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->string('code')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('nro_identificacion')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('age')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_corporate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_corporate_id');
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('upgrade_benefit_id')->nullable();
        upgradeAssignmentTestColumns($table);
    });

    DB::table('plan_generators')->insert([['id' => 1, 'name' => 'Plantilla'], ['id' => 2, 'name' => 'Derivada']]);

    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
});

afterEach(function (): void {
    DB::purge('pg_upgrades_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

function pgPlan(int $id = 1): PlanGenerator
{
    return PlanGenerator::query()->findOrFail($id);
}

it('guarda una copia congelada del catálogo con sus servicios activos', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);

    $line = PlanGeneratorUpgrades::linesFromModel(pgPlan())[0];

    expect($line['upgrade_name'])->toBe('ODONTOLOGÍA')
        ->and($line['upgrade_code'])->toBe('UPG-0001')
        ->and($line['coverage_amount'])->toBe(5000.0)
        ->and(array_column($line['age_prices'], 'price'))->toEqual([40, 60])
        ->and(array_column($line['services'], 'name'))->toBe(['Limpieza']);
});

it('una línea ya guardada conserva su precio aunque el catálogo cambie; una nueva toma el vigente', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);
    DB::table('upgrade_coverage_age_ranges')->update(['price' => 999]);

    PlanGeneratorUpgrades::sync(pgPlan(), [
        ['line_key' => 'l1', 'upgrade_coverage_id' => 10],
        ['line_key' => 'l2', 'upgrade_coverage_id' => 20],
    ]);

    $lines = collect(PlanGeneratorUpgrades::linesFromModel(pgPlan()))->keyBy('line_key');

    expect(array_column($lines['l1']['age_prices'], 'price'))->toEqual([40, 60])
        ->and(array_column($lines['l2']['age_prices'], 'price'))->toEqual([999]);
});

it('cambiar de cobertura en la misma fila vuelve a tomar el catálogo', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 11]]);

    expect(PlanGeneratorUpgrades::linesFromModel(pgPlan())[0]['coverage_amount'])->toBe(10000.0);
});

it('una línea congelada sigue aunque el upgrade ya esté inactivo; una nueva de un inactivo se rechaza', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);
    DB::table('upgrades')->where('id', 1)->update(['status' => 'INACTIVO']);

    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);

    expect(PlanGeneratorUpgrades::linesFromModel(pgPlan()))->toHaveCount(1)
        ->and(fn () => PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'otra', 'upgrade_coverage_id' => 30]]))
        ->toThrow(InvalidArgumentException::class, 'ya no está activo');
});

it('rechaza filas sin cobertura y el mismo upgrade dos veces sin escribir', function (array $state, string $message): void {
    expect(fn () => PlanGeneratorUpgrades::sync(pgPlan(), $state))->toThrow(InvalidArgumentException::class, $message);

    expect(DB::table('plan_generator_upgrades')->count())->toBe(0);
})->with([
    'sin cobertura' => [[['line_key' => 'l1', 'upgrade_coverage_id' => null]], 'no tiene cobertura elegida'],
    'mismo upgrade dos veces' => [[['line_key' => 'l1', 'upgrade_coverage_id' => 10], ['line_key' => 'l2', 'upgrade_coverage_id' => 11]], 'está dos veces'],
    'cobertura inexistente' => [[['line_key' => 'l1', 'upgrade_coverage_id' => 999]], 'ya no existe'],
]);

it('valida contra la población de la cotización antes de guardar', function (): void {
    $rateRows = [
        ['age_range_label' => '0 a 35', 'population' => 10],
        ['age_range_label' => '36 a 70', 'population' => 2],
    ];

    expect(PlanGeneratorUpgrades::validationErrors(null, [['line_key' => 'l1', 'upgrade_coverage_id' => 10]], $rateRows))->toBe([])
        ->and(PlanGeneratorUpgrades::validationErrors(null, [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'excluded_ranges' => ['0 a 35']]], $rateRows))
        ->toHaveCount(1);
});

it('guarda los rangos que el analista sacó, normalizados', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'excluded_ranges' => [' 36 A 70 ', '36 a 70', '']]]);

    expect(PlanGeneratorUpgrades::linesFromModel(pgPlan())[0]['excluded_ranges'])->toBe(['36 a 70'])
        ->and(PlanGeneratorUpgrades::formState(pgPlan()))->toHaveCount(1)
        ->and(array_values(PlanGeneratorUpgrades::formState(pgPlan()))[0]['excluded_ranges'])->toBe(['36 a 70']);
});

it('la derivada copia los upgrades congelados de la plantilla', function (): void {
    PlanGeneratorUpgrades::sync(pgPlan(1), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);
    DB::table('upgrade_coverage_age_ranges')->update(['price' => 999]);

    PlanGeneratorUpgrades::copy(pgPlan(1), pgPlan(2));

    $copy = PlanGeneratorUpgrades::linesFromModel(pgPlan(2));

    expect($copy)->toHaveCount(1)
        ->and($copy[0]['line_key'])->not->toBe('l1')
        ->and(array_column($copy[0]['age_prices'], 'price'))->toEqual([40, 60])
        ->and(PlanGeneratorUpgrades::linesFromModel(pgPlan(1)))->toHaveCount(1);
});

it('al aprobar en individual cada familiar recibe el upgrade por su edad real y el total se ajusta', function (): void {
    DB::table('affiliations')->insert(['id' => 5, 'code' => 'TDEC-IND-5', 'payment_frequency' => 'TRIMESTRAL', 'fee_anual' => 1080, 'total_amount' => 270]);
    DB::table('affiliates')->insert([
        ['id' => 1, 'affiliation_id' => 5, 'full_name' => 'TITULAR', 'age' => '30', 'status' => 'PRE-APROBADA'],
        ['id' => 2, 'affiliation_id' => 5, 'full_name' => 'ABUELA', 'age' => '70', 'status' => 'PRE-APROBADA'],
    ]);

    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10]]);

    $result = PlanGeneratorUpgradeAssignment::assignIndividual(Affiliation::query()->findOrFail(5), [
        'upgrade_lines' => PlanGeneratorUpgrades::linesFromModel(pgPlan()),
        'rate_rows' => [['age_range_label' => '0 a 35'], ['age_range_label' => '36 a 75']],
        'upgrade_row_label' => '0 a 35',
        'upgrade_annual_estimate' => 80,
    ]);

    $affiliation = Affiliation::query()->findOrFail(5);

    expect($result['created'])->toBe(1)
        ->and($result['annual'])->toBe(40.0)
        ->and($result['delta'])->toBe(-40.0)
        ->and(AffiliateUpgrade::query()->pluck('affiliate_id')->all())->toBe([1])
        ->and((float) $affiliation->fee_anual)->toBe(1040.0)
        ->and((float) $affiliation->total_amount)->toBe(260.0)
        ->and(PlanGeneratorUpgradeAssignment::assignIndividual($affiliation, [])['created'])->toBe(0);
});

it('al aprobar en corporativo empareja a cada afiliado copiado con su persona del padrón', function (): void {
    DB::table('affiliation_corporates')->insert(['id' => 9, 'code' => 'TDEC-COR-9']);
    DB::table('affiliate_corporates')->insert([
        ['id' => 21, 'affiliation_corporate_id' => 9, 'nro_identificacion' => 'V-1', 'age' => '30'],
        ['id' => 22, 'affiliation_corporate_id' => 9, 'nro_identificacion' => 'V-2', 'age' => '50'],
    ]);

    $upgrade = fn (float $amount, int $rangeId): array => ['upgrade_id' => 1, 'upgrade_coverage_id' => 10, 'upgrade_coverage_age_range_id' => $rangeId, 'name' => 'ODONTOLOGÍA', 'coverage_amount' => 5000, 'amount' => $amount];

    $created = PlanGeneratorUpgradeAssignment::assignCorporate(
        AffiliationCorporate::query()->findOrFail(9),
        [
            ['population_id' => 501, 'nro_identificacion' => 'V-1', 'age' => '30'],
            ['population_id' => 502, 'nro_identificacion' => 'V-2', 'age' => '50'],
        ],
        [501 => ['upgrades' => [$upgrade(40, 100)]], 502 => ['upgrades' => [$upgrade(60, 101)]]],
    );

    expect($created)->toBe(2)
        ->and(AffiliateCorporateUpgrade::query()->where('affiliate_corporate_id', 22)->value('amount'))->toEqual(60)
        ->and(AffiliateCorporateUpgrade::query()->where('affiliate_corporate_id', 21)->value('upgrade_coverage_age_range_id'))->toBe(100);
});

it('si el orden de los afiliados copiados no cuadra con el padrón, lanza para revertir todo', function (): void {
    DB::table('affiliation_corporates')->insert(['id' => 9, 'code' => 'TDEC-COR-9']);
    DB::table('affiliate_corporates')->insert([
        ['id' => 21, 'affiliation_corporate_id' => 9, 'nro_identificacion' => 'V-2', 'age' => '30'],
    ]);

    expect(fn () => PlanGeneratorUpgradeAssignment::assignCorporate(
        AffiliationCorporate::query()->findOrFail(9),
        [['population_id' => 501, 'nro_identificacion' => 'V-1', 'age' => '30']],
        [501 => ['upgrades' => [['upgrade_id' => 1, 'upgrade_coverage_id' => 10, 'name' => 'X', 'amount' => 40]]]],
    ))->toThrow(RuntimeException::class, 'no coincide');

    expect(AffiliateCorporateUpgrade::query()->count())->toBe(0);
});

it('una inclusión manual exige cantidad de personas y una nota de al menos 15 caracteres', function (array $manual, string $message): void {
    expect(fn () => PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'manual_ranges' => [$manual]]]))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(DB::table('plan_generator_upgrades')->count())->toBe(0);
})->with([
    'nota corta' => [['label' => '60 a 70', 'persons' => 2, 'note' => 'porque sí'], 'al menos 15 caracteres'],
    'nota solo con etiquetas' => [['label' => '60 a 70', 'persons' => 2, 'note' => '<b>          </b>'], 'al menos 15 caracteres'],
    'sin personas' => [['label' => '60 a 70', 'persons' => 0, 'note' => 'Según el padrón del cliente'], 'cuántas personas'],
    'personas no enteras' => [['label' => '60 a 70', 'persons' => '2.5', 'note' => 'Según el padrón del cliente'], 'cuántas personas'],
]);

it('quién y cuándo los fija el servidor, y se conservan si la inclusión no cambia', function (): void {
    $manual = ['label' => '60 a 70', 'persons' => 2, 'note' => 'Según el padrón del cliente', 'by' => 'Alguien falso', 'at' => '2000-01-01T00:00:00Z'];

    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'manual_ranges' => [$manual]]]);
    $first = PlanGeneratorUpgrades::linesFromModel(pgPlan())[0]['manual_ranges'][0];

    expect($first['by'])->toBe('Analista Negocios')
        ->and($first['persons'])->toBe(2)
        ->and($first['at'])->not->toBe('2000-01-01T00:00:00Z')
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_PLAN_GENERATOR_UPGRADE_MANUAL_RANGES')->count())->toBe(1);

    Auth::setUser(User::factory()->make(['id' => 98, 'name' => 'Otro Analista']));
    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'manual_ranges' => [['by' => 'x'] + $first]]]);
    $again = PlanGeneratorUpgrades::linesFromModel(pgPlan())[0]['manual_ranges'][0];

    expect($again['by'])->toBe('Analista Negocios')
        ->and($again['at'])->toBe($first['at'])
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_PLAN_GENERATOR_UPGRADE_MANUAL_RANGES')->count())->toBe(1);

    PlanGeneratorUpgrades::sync(pgPlan(), [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'manual_ranges' => [['persons' => 3] + $first]]]);

    expect(PlanGeneratorUpgrades::linesFromModel(pgPlan())[0]['manual_ranges'][0]['by'])->toBe('Otro Analista')
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_PLAN_GENERATOR_UPGRADE_MANUAL_RANGES')->count())->toBe(2);
});

it('con un rango cubierto en parte e incluido a mano, el upgrade ya no bloquea', function (): void {
    $rateRows = [['age_range_label' => '60 a 70', 'population' => 3]];
    $manual = [['label' => '60 a 70', 'persons' => 2, 'note' => 'Según el padrón del cliente']];

    expect(PlanGeneratorUpgrades::validationErrors(null, [['line_key' => 'l1', 'upgrade_coverage_id' => 10]], $rateRows))->toHaveCount(1)
        ->and(PlanGeneratorUpgrades::validationErrors(null, [['line_key' => 'l1', 'upgrade_coverage_id' => 10, 'manual_ranges' => $manual]], $rateRows))->toBe([]);
});
