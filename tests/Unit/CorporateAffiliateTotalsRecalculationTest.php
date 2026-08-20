<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Services\CorporateAffiliatePlanSyncService;
use App\Support\AffiliationCorporates\CorporateAffiliatePlanSynchronizer;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Reproduce el caso de INNOVAGRO (TDEC-COR-00055): población en PRE-APROBADA
 * cuyos totales quedaron en cero al recalcular contando solo ACTIVO.
 */
function seedCorporateTotalsScenario(string $affiliateStatus): AffiliationCorporate
{
    foreach (['affiliation_corporates', 'affiliate_corporates', 'afilliation_corporate_plans', 'age_ranges'] as $table) {
        Schema::dropIfExists($table);
    }

    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->integer('poblation')->default(0);
        $table->decimal('fee_anual', 15, 2)->default(0);
        $table->decimal('total_amount', 15, 2)->default(0);
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
        $table->decimal('fee', 15, 2)->default(0);
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('afilliation_corporate_plans', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->unsignedBigInteger('age_range_id')->nullable();
        $table->unsignedBigInteger('coverage_id')->nullable();
        $table->decimal('fee', 15, 2)->default(0);
        $table->integer('total_persons')->default(0);
        $table->decimal('subtotal_anual', 15, 2)->default(0);
        $table->decimal('subtotal_quarterly', 15, 2)->default(0);
        $table->decimal('subtotal_biannual', 15, 2)->default(0);
        $table->decimal('subtotal_monthly', 15, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->timestamps();
    });

    Schema::create('age_ranges', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('plan_id')->nullable();
        $table->string('range')->nullable();
        $table->integer('age_init')->default(0);
        $table->integer('age_end')->default(0);
        $table->timestamps();
    });

    DB::table('age_ranges')->insert([
        ['id' => 5, 'plan_id' => 3, 'range' => '0 A 30', 'age_init' => 0, 'age_end' => 30],
        ['id' => 6, 'plan_id' => 3, 'range' => '31 a 65', 'age_init' => 31, 'age_end' => 65],
    ]);

    DB::table('affiliation_corporates')->insert([
        'id' => 55,
        'code' => 'TDEC-COR-00055',
        'payment_frequency' => 'TRIMESTRAL',
        'poblation' => 6,
        'fee_anual' => 1950,
        'total_amount' => 487.50,
    ]);

    DB::table('afilliation_corporate_plans')->insert([
        ['id' => 89, 'affiliation_corporate_id' => 55, 'plan_id' => 3, 'age_range_id' => 5, 'coverage_id' => 11, 'fee' => 311, 'total_persons' => 3, 'payment_frequency' => 'TRIMESTRAL'],
        ['id' => 90, 'affiliation_corporate_id' => 55, 'plan_id' => 3, 'age_range_id' => 6, 'coverage_id' => 11, 'fee' => 339, 'total_persons' => 3, 'payment_frequency' => 'TRIMESTRAL'],
    ]);

    $ages = [['22', 311], ['23', 311], ['29', 311], ['34', 339], ['35', 339], ['41', 339]];

    foreach ($ages as $index => [$age, $fee]) {
        DB::table('affiliate_corporates')->insert([
            'affiliation_corporate_id' => 55,
            'first_name' => 'Afiliado'.$index,
            'last_name' => 'Prueba',
            'age' => $age,
            'plan_id' => 3,
            'coverage_id' => 11,
            'fee' => $fee,
            'status' => $affiliateStatus,
        ]);
    }

    return AffiliationCorporate::query()->findOrFail(55);
}

beforeEach(function (): void {
    /**
     * Conexión sqlite en memoria creada por el propio test: en este entorno el
     * `.env` gana sobre `phpunit.xml`, así que no se puede depender de la
     * conexión por defecto. Nunca se toca la base de datos real.
     */
    config()->set('database.connections.corporate_totals_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'corporate_totals_testing');
    DB::purge('corporate_totals_testing');
    DB::setDefaultConnection('corporate_totals_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');
});

afterEach(function (): void {
    DB::purge('corporate_totals_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('no deja en cero los totales de una afiliacion con poblacion PRE-APROBADA', function (): void {
    $owner = seedCorporateTotalsScenario('PRE-APROBADA');

    CorporateAffiliatePlanSyncService::syncPlanRowTotalsFromAffiliates(
        $owner,
        CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES,
    );
    CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates(
        $owner,
        CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES,
    );

    $owner->refresh();

    expect((int) $owner->poblation)->toBe(6)
        ->and((float) $owner->fee_anual)->toBe(1950.0)
        ->and((float) $owner->total_amount)->toBe(487.50);

    $rows = DB::table('afilliation_corporate_plans')->orderBy('id')->get();

    expect((int) $rows[0]->total_persons)->toBe(3)
        ->and((float) $rows[0]->subtotal_anual)->toBe(933.0)
        ->and((float) $rows[0]->subtotal_quarterly)->toBe(233.25)
        ->and((int) $rows[1]->total_persons)->toBe(3)
        ->and((float) $rows[1]->subtotal_anual)->toBe(1017.0)
        ->and((float) $rows[1]->subtotal_quarterly)->toBe(254.25);
});

it('reproduce el fallo original cuando solo se cuentan afiliados ACTIVO', function (): void {
    $owner = seedCorporateTotalsScenario('PRE-APROBADA');

    /** Comportamiento por defecto: es el que puso INNOVAGRO en cero. */
    CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($owner);

    $owner->refresh();

    expect((int) $owner->poblation)->toBe(0)
        ->and((float) $owner->fee_anual)->toBe(0.0);
});

it('mantiene el comportamiento historico para afiliados ACTIVO', function (): void {
    $owner = seedCorporateTotalsScenario('ACTIVO');

    CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates($owner);

    $owner->refresh();

    expect((int) $owner->poblation)->toBe(6)
        ->and((float) $owner->fee_anual)->toBe(1950.0)
        ->and((float) $owner->total_amount)->toBe(487.50);
});

it('excluye del conteo a los afiliados dados de baja', function (): void {
    $owner = seedCorporateTotalsScenario('PRE-APROBADA');

    DB::table('affiliate_corporates')->where('age', '41')->update(['status' => 'EXCLUIDO']);

    CorporateAffiliatePlanSyncService::syncOwnerTotalsFromAffiliates(
        $owner,
        CorporateAffiliatePlanSynchronizer::COUNTABLE_STATUSES,
    );

    $owner->refresh();

    expect((int) $owner->poblation)->toBe(5)
        ->and((float) $owner->fee_anual)->toBe(1611.0);
});

it('la salvaguarda y la auditoria estan cableadas en el sincronizador', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/app/Support/AffiliationCorporates/CorporateAffiliatePlanSynchronizer.php');

    expect($source)
        ->toContain('self::assertTotalsAreNotWipedOut($owner)')
        ->toContain('syncPlanRowTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES)')
        ->toContain('syncOwnerTotalsFromAffiliates($owner, self::COUNTABLE_STATUSES)')
        ->toContain("SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATE_PLAN_SYNCED'")
        ->toContain("'totals_before' => \$before")
        ->toContain("'totals_after' => \$after");
});
