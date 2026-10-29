<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateUpgrade;
use App\Models\User;
use App\Support\Upgrades\UpgradeRenewalRepricer;
use Carbon\Carbon;
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
    config()->set('database.connections.upgrade_repricer_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'upgrade_repricer_testing');
    DB::purge('upgrade_repricer_testing');
    DB::setDefaultConnection('upgrade_repricer_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    createUpgradeCatalogTestSchema();

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('full_name')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('age')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('age')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_id');
        upgradeAssignmentTestColumns($table);
    });

    Schema::create('affiliate_corporate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_corporate_id');
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('upgrade_benefit_id')->nullable();
        upgradeAssignmentTestColumns($table);
    });

    /** Cumple 36 el 15/03/2027: en la renovación pasa del rango 0–35 (40) al 36–64 (60). */
    DB::table('affiliates')->insert(['id' => 1, 'affiliation_id' => 10, 'full_name' => 'ANA', 'birth_date' => '15/03/1991', 'age' => '35', 'fee' => 640, 'status' => 'ACTIVO']);
    DB::table('affiliate_upgrades')->insert([
        'id' => 1, 'affiliate_id' => 1, 'upgrade_id' => 1, 'upgrade_coverage_id' => 10, 'upgrade_coverage_age_range_id' => 100,
        'name' => 'ODONTOLOGÍA', 'age_at_assignment' => 35, 'amount' => 40, 'status' => 'ACTIVO', 'created_by' => 'Analista',
    ]);

    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Renovaciones']));
});

afterEach(function (): void {
    DB::purge('upgrade_repricer_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('proyecta el precio del rango nuevo sin escribir nada', function (): void {
    $affiliate = Affiliate::query()->findOrFail(1);

    expect(UpgradeRenewalRepricer::projectedTotal($affiliate, Carbon::parse('2027-03-15')))->toBe(60.0)
        ->and(UpgradeRenewalRepricer::projectedTotal($affiliate, Carbon::parse('2027-03-14')))->toBe(40.0)
        ->and(AffiliateUpgrade::query()->count())->toBe(1);
});

it('al renovar recotiza por edad y deja la asignación vieja como historia', function (): void {
    $total = UpgradeRenewalRepricer::reprice(Affiliate::query()->findOrFail(1), Carbon::parse('2027-03-15'));

    $old = AffiliateUpgrade::query()->findOrFail(1);
    $new = AffiliateUpgrade::query()->where('status', 'ACTIVO')->sole();

    expect($total)->toBe(60.0)
        ->and($old->status)->toBe('INACTIVO')
        ->and($old->deactivation_reason)->toBe(UpgradeRenewalRepricer::REASON)
        ->and($old->deactivated_by)->toBe('Analista Renovaciones')
        ->and((float) $new->amount)->toBe(60.0)
        ->and((int) $new->upgrade_coverage_age_range_id)->toBe(101)
        ->and($new->age_at_assignment)->toBe(36)
        ->and((int) $new->replaces_id)->toBe(1)
        ->and($new->name)->toBe('ODONTOLOGÍA');
});

it('si el precio y el rango no cambian no crea filas nuevas', function (): void {
    $total = UpgradeRenewalRepricer::reprice(Affiliate::query()->findOrFail(1), Carbon::parse('2026-06-01'));

    expect($total)->toBe(40.0)
        ->and(AffiliateUpgrade::query()->count())->toBe(1);
});

it('usa el precio vigente del catálogo al renovar', function (): void {
    DB::table('upgrade_coverage_age_ranges')->where('id', 100)->update(['price' => 45]);

    expect(UpgradeRenewalRepricer::reprice(Affiliate::query()->findOrFail(1), Carbon::parse('2026-06-01')))->toBe(45.0)
        ->and(AffiliateUpgrade::query()->where('status', 'ACTIVO')->value('amount'))->toEqual(45);
});

it('conserva el monto si ya no se puede cotizar', function (Closure $arrange, string $asOf): void {
    $arrange();

    expect(UpgradeRenewalRepricer::reprice(Affiliate::query()->findOrFail(1), Carbon::parse($asOf)))->toBe(40.0)
        ->and(AffiliateUpgrade::query()->count())->toBe(1)
        ->and(AffiliateUpgrade::query()->findOrFail(1)->status)->toBe('ACTIVO');
})->with([
    'upgrade inactivo' => [fn () => DB::table('upgrades')->where('id', 1)->update(['status' => 'INACTIVO']), '2027-03-15'],
    'cobertura inactiva' => [fn () => DB::table('upgrade_coverages')->where('id', 10)->update(['status' => 'INACTIVO']), '2027-03-15'],
    'edad fuera de todo rango' => [fn () => null, '2056-03-15'],
    'sin edad' => [fn () => DB::table('affiliates')->where('id', 1)->update(['birth_date' => null, 'age' => null]), '2027-03-15'],
]);

it('no consulta a quien el agregado dice que no tiene upgrades', function (): void {
    $affiliate = Affiliate::query()->withActiveUpgradesTotal()->findOrFail(1);
    $affiliate->setRawAttributes(['active_upgrades_total' => null] + $affiliate->getAttributes(), true);

    DB::enableQueryLog();
    $total = UpgradeRenewalRepricer::projectedTotal($affiliate, Carbon::parse('2027-03-15'));

    expect($total)->toBe(0.0)
        ->and(DB::getQueryLog())->toBe([]);
});

it('recotiza también los upgrades corporativos', function (): void {
    DB::table('affiliate_corporates')->insert(['id' => 7, 'affiliation_corporate_id' => 5, 'birth_date' => null, 'age' => '36', 'fee' => 540, 'status' => 'ACTIVO']);
    DB::table('affiliate_corporate_upgrades')->insert([
        'affiliate_corporate_id' => 7, 'affiliation_corporate_id' => 5, 'upgrade_id' => 1, 'upgrade_coverage_id' => 10,
        'upgrade_coverage_age_range_id' => 100, 'name' => 'ODONTOLOGÍA', 'amount' => 40, 'status' => 'ACTIVO',
    ]);

    $total = UpgradeRenewalRepricer::reprice(AffiliateCorporate::query()->findOrFail(7), Carbon::parse('2027-01-01'));

    expect($total)->toBe(60.0)
        ->and(DB::table('affiliate_corporate_upgrades')->where('status', 'ACTIVO')->value('amount'))->toEqual(60)
        ->and(DB::table('affiliate_corporate_upgrades')->where('status', 'INACTIVO')->value('deactivation_reason'))->toBe('RENOVACIÓN');
});
