<?php

declare(strict_types=1);

use App\Models\Upgrade;
use App\Models\User;
use App\Support\Upgrades\UpgradeCatalogPersistence;
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
    config()->set('database.connections.upgrade_catalog_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'upgrade_catalog_testing');
    DB::purge('upgrade_catalog_testing');
    DB::setDefaultConnection('upgrade_catalog_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    createUpgradeCatalogTestSchema(withFixture: false);

    foreach (['affiliate_corporate_upgrades', 'affiliate_upgrades'] as $name) {
        Schema::create($name, function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('affiliate_corporate_id')->nullable();
            $table->unsignedBigInteger('affiliation_corporate_id')->nullable();
            $table->unsignedBigInteger('affiliate_id')->nullable();
            upgradeAssignmentTestColumns($table);
        });
    }

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

    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
});

afterEach(function (): void {
    DB::purge('upgrade_catalog_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

/**
 * @return array<string, mixed>
 */
function catalogFormState(): array
{
    return [
        'name' => 'odontología',
        'description' => 'Ortodoncia y limpiezas',
        'status' => 'ACTIVO',
        'services' => [
            ['name' => 'Limpieza', 'description' => 'Semestral'],
            ['name' => 'Ortodoncia', 'description' => null],
        ],
        'coverages' => [
            ['amount' => '5000', 'ranges' => [
                ['age_from' => 0, 'age_to' => 17, 'price' => 20],
                ['age_from' => 18, 'age_to' => 64, 'price' => 35],
            ]],
        ],
    ];
}

it('crea el upgrade con código, servicios, coberturas y rangos', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());

    expect($upgrade->code)->toBe('UPG-'.str_pad((string) $upgrade->id, 4, '0', STR_PAD_LEFT))
        ->and($upgrade->name)->toBe('ODONTOLOGÍA')
        ->and($upgrade->created_by)->toBe('Analista Negocios')
        ->and($upgrade->activeServices()->pluck('name')->all())->toBe(['Limpieza', 'Ortodoncia'])
        ->and($upgrade->activeCoverages()->count())->toBe(1)
        ->and($upgrade->activeCoverages()->first()->activeAgeRanges()->pluck('price')->map(fn ($p) => (float) $p)->all())->toBe([20.0, 35.0])
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_UPGRADE_CREATED')->exists())->toBeTrue();
});

it('rechaza rangos solapados sin escribir nada', function (): void {
    $state = catalogFormState();
    $state['coverages'][0]['ranges'][1]['age_from'] = 10;

    expect(fn () => UpgradeCatalogPersistence::save(null, $state))
        ->toThrow(InvalidArgumentException::class, 'se solapa');

    expect(Upgrade::query()->count())->toBe(0)
        ->and(DB::table('upgrade_coverage_age_ranges')->count())->toBe(0);
});

it('lo que sale del formulario pasa a INACTIVO, no se borra', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());
    $state = UpgradeCatalogPersistence::formState($upgrade);

    array_pop($state['services']);
    $coverageKey = array_key_first($state['coverages']);
    array_pop($state['coverages'][$coverageKey]['ranges']);

    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(DB::table('upgrade_services')->count())->toBe(2)
        ->and(DB::table('upgrade_services')->where('status', 'INACTIVO')->value('name'))->toBe('Ortodoncia')
        ->and(DB::table('upgrade_coverage_age_ranges')->count())->toBe(2)
        ->and(DB::table('upgrade_coverage_age_ranges')->where('status', 'INACTIVO')->value('age_from'))->toEqual(18);
});

it('un rango que nadie usa se corrige en el lugar', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());
    $state = UpgradeCatalogPersistence::formState($upgrade);
    $coverageKey = array_key_first($state['coverages']);
    $rangeKey = array_key_last($state['coverages'][$coverageKey]['ranges']);
    $rangeId = $state['coverages'][$coverageKey]['ranges'][$rangeKey]['id'];

    $state['coverages'][$coverageKey]['ranges'][$rangeKey]['price'] = '40';
    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(DB::table('upgrade_coverage_age_ranges')->count())->toBe(2)
        ->and((float) DB::table('upgrade_coverage_age_ranges')->where('id', $rangeId)->value('price'))->toBe(40.0);
});

it('un rango que ya tiene afiliados se versiona: el viejo queda como historia', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());
    $state = UpgradeCatalogPersistence::formState($upgrade);
    $coverageKey = array_key_first($state['coverages']);
    $rangeKey = array_key_last($state['coverages'][$coverageKey]['ranges']);
    $rangeId = $state['coverages'][$coverageKey]['ranges'][$rangeKey]['id'];

    DB::table('affiliate_upgrades')->insert([
        'affiliate_id' => 1, 'upgrade_id' => $upgrade->id, 'upgrade_coverage_id' => $state['coverages'][$coverageKey]['id'],
        'upgrade_coverage_age_range_id' => $rangeId, 'name' => 'ODONTOLOGÍA', 'amount' => 35, 'status' => 'ACTIVO',
    ]);

    $state['coverages'][$coverageKey]['ranges'][$rangeKey]['price'] = '40';
    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(DB::table('upgrade_coverage_age_ranges')->where('id', $rangeId)->value('status'))->toBe('INACTIVO')
        ->and((float) DB::table('upgrade_coverage_age_ranges')->where('id', $rangeId)->value('price'))->toBe(35.0)
        ->and((float) DB::table('upgrade_coverage_age_ranges')->where('status', 'ACTIVO')->where('age_from', 18)->value('price'))->toBe(40.0)
        ->and((float) DB::table('affiliate_upgrades')->value('amount'))->toBe(35.0);
});

it('cambiar el monto de una cobertura en uso crea otra y conserva la vieja', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());
    $state = UpgradeCatalogPersistence::formState($upgrade);
    $coverageKey = array_key_first($state['coverages']);
    $coverageId = $state['coverages'][$coverageKey]['id'];

    DB::table('affiliate_corporate_upgrades')->insert([
        'affiliate_corporate_id' => 1, 'affiliation_corporate_id' => 1, 'upgrade_id' => $upgrade->id,
        'upgrade_coverage_id' => $coverageId, 'name' => 'ODONTOLOGÍA', 'amount' => 20, 'status' => 'ACTIVO',
    ]);

    $state['coverages'][$coverageKey]['amount'] = '6000';
    UpgradeCatalogPersistence::save($upgrade, $state);

    expect(DB::table('upgrade_coverages')->where('id', $coverageId)->value('status'))->toBe('INACTIVO')
        ->and((float) DB::table('upgrade_coverages')->where('status', 'ACTIVO')->value('amount'))->toBe(6000.0)
        ->and(DB::table('upgrade_coverage_age_ranges')->where('status', 'ACTIVO')->count())->toBe(2)
        ->and(DB::table('upgrade_coverage_age_ranges')->where('upgrade_coverage_id', $coverageId)->where('status', 'ACTIVO')->count())->toBe(0);
});

it('activa e inactiva sin tocar la estructura y deja auditoría', function (): void {
    $upgrade = UpgradeCatalogPersistence::save(null, catalogFormState());

    UpgradeCatalogPersistence::changeStatus($upgrade, Upgrade::STATUS_INACTIVE);

    expect($upgrade->fresh()->status)->toBe('INACTIVO')
        ->and(DB::table('upgrade_coverages')->where('status', 'ACTIVO')->count())->toBe(1)
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_UPGRADE_STATUS_CHANGED')->exists())->toBeTrue();

    expect(fn () => UpgradeCatalogPersistence::changeStatus($upgrade, 'BORRADO'))
        ->toThrow(InvalidArgumentException::class);
});
