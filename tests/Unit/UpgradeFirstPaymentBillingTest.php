<?php

declare(strict_types=1);

use App\Models\Affiliation;
use App\Models\PaidMembership;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\Upgrades\UpgradeFirstPaymentBilling;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Upgrades de cobro único (29/10/2026): se pagan completos en el primer pago del año y las
 * cuotas siguientes llevan solo el plan. Esquema mínimo en sqlite `:memory:` propio: la config
 * cacheada apunta a MySQL y este test escribe.
 */
beforeEach(function (): void {
    config()->set('database.connections.first_payment_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'first_payment_testing');
    DB::purge('first_payment_testing');
    DB::setDefaultConnection('first_payment_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    Schema::create('affiliations', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->string('code')->nullable();
        $table->string('status')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->decimal('fee_anual', 8, 2)->default(0);
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->string('activated_at')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliates', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->unsignedBigInteger('affiliation_id');
        $table->string('status')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('affiliate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_id');
        upgradeAssignmentTestColumns($table);
    });

    Schema::create('paid_memberships', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->decimal('total_amount', 10, 2)->nullable();
        $table->decimal('upgrades_amount', 10, 2)->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    DB::table('affiliations')->insert(['id' => 1, 'code' => 'TDEC-IND-1', 'status' => 'PRE-APROBADA', 'payment_frequency' => 'TRIMESTRAL', 'fee_anual' => 435, 'total_amount' => 100, 'activated_at' => null]);
    DB::table('affiliates')->insert([
        ['id' => 1, 'affiliation_id' => 1, 'status' => 'PRE-APROBADA', 'fee' => 235],
        ['id' => 2, 'affiliation_id' => 1, 'status' => 'INACTIVO', 'fee' => 200],
    ]);
    DB::table('affiliate_upgrades')->insert([
        ['affiliate_id' => 1, 'name' => 'FUNERARIO 2K', 'amount' => 35, 'status' => 'ACTIVO', 'billing_mode' => UpgradeFirstPaymentBilling::MODE],
        /** Una asignación de antes, repartida en cuotas: no se cobra aparte. */
        ['affiliate_id' => 1, 'name' => 'ODONTOLOGÍA', 'amount' => 40, 'status' => 'ACTIVO', 'billing_mode' => null],
        /** De un familiar dado de baja: no cuenta. */
        ['affiliate_id' => 2, 'name' => 'FUNERARIO 2K', 'amount' => 35, 'status' => 'ACTIVO', 'billing_mode' => UpgradeFirstPaymentBilling::MODE],
    ]);
});

afterEach(function (): void {
    DB::purge('first_payment_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('el primer comprobante sugiere cuota + upgrades y anota la parte de upgrades al crearse', function (): void {
    $affiliation = Affiliation::query()->findOrFail(1);

    expect(UpgradeFirstPaymentBilling::individualOneTimeTotal($affiliation))->toBe(35.0)
        ->and(UpgradeFirstPaymentBilling::suggestedProofAmount($affiliation))->toBe(135.0)
        ->and(UpgradeFirstPaymentBilling::regularInstallment(435.0, 35.0, 'TRIMESTRAL'))->toBe(100.0);

    $withUpgrades = PaidMembership::query()->create(['affiliation_id' => 1, 'total_amount' => 135, 'status' => 'PENDIENTE']);
    $onlyInstallment = PaidMembership::query()->create(['affiliation_id' => 1, 'total_amount' => 100, 'status' => 'PENDIENTE']);

    expect((float) $withUpgrades->fresh()->upgrades_amount)->toBe(35.0)
        ->and($onlyInstallment->fresh()->upgrades_amount)->toBeNull()
        ->and(UpgradeFirstPaymentBilling::installmentAfterFirstPayment(135.0, $withUpgrades->fresh()->upgrades_amount, 'TRIMESTRAL'))->toBe(100.0)
        ->and(UpgradeFirstPaymentBilling::installmentAfterFirstPayment(100.0, $onlyInstallment->fresh()->upgrades_amount, 'TRIMESTRAL'))->toBe(100.0);

    DB::table('affiliations')->where('id', 1)->update(['activated_at' => '29/10/2026']);
    $later = PaidMembership::query()->create(['affiliation_id' => 1, 'total_amount' => 135, 'status' => 'PENDIENTE']);

    expect($later->fresh()->upgrades_amount)->toBeNull()
        ->and(UpgradeFirstPaymentBilling::suggestedProofAmount(Affiliation::query()->findOrFail(1)))->toBe(100.0)
        ->and(UpgradeFirstPaymentBilling::proofAmountHint(Affiliation::query()->findOrFail(1)))->toBeNull();
});

it('al cambiar de frecuencia, un upgrade ya sumado a un aviso no se reparte: va completo en la primera cuota nueva', function (): void {
    $pending = [
        ['date' => Carbon::parse('2026-12-01'), 'months' => 3, 'amount' => 135.0],
        ['date' => Carbon::parse('2027-03-01'), 'months' => 3, 'amount' => 100.0],
    ];

    $schedule = CorporatePaymentFrequencyChanger::reschedule($pending, 'MENSUAL', 35.0);

    expect($schedule)->toHaveCount(6)
        ->and($schedule[0]['amount'])->toBe(68.33)
        ->and($schedule[1]['amount'])->toBe(33.33)
        ->and(round(array_sum(array_column($schedule, 'amount')), 2))->toBe(235.0)
        ->and(CorporatePaymentFrequencyChanger::reschedule($pending, 'MENSUAL')[0]['amount'])->toBe(39.17);
});

it('la aprobación, la renovación, el cambio de plan y la unificación aplican la regla', function (): void {
    $root = dirname(__DIR__, 2).'/';
    $individual = (string) file_get_contents($root.'app/Http/Controllers/PaidMembershipController.php');
    $corporate = (string) file_get_contents($root.'app/Http/Controllers/PaidMembershipCorporateController.php');
    $renewal = (string) file_get_contents($root.'app/Services/AffiliationRenewalCollectionGenerator.php');

    expect($individual)->toContain('UpgradeFirstPaymentBilling::installmentAfterFirstPayment($saleAmount, $record->upgrades_amount')
        ->and(substr_count($individual, '$installmentAmount'))->toBe(7)
        ->and($corporate)->toContain('UpgradeFirstPaymentBilling::installmentAfterFirstPayment((float) $record->total_amount, $record->upgrades_amount')
        ->and(substr_count($corporate, '$installmentAmount'))->toBe(7)
        ->and(substr_count($renewal, '$paymentDate === $paymentDates[0] ? round($periodAmount + $oneTimeUpgrades, 2) : $periodAmount'))->toBe(2)
        ->and((string) file_get_contents($root.'app/Support/Affiliations/PlanChange/IndividualAffiliationPlanChanger.php'))->toContain("UpgradeFirstPaymentBilling::moveCharges('affiliate_upgrades'")
        ->and((string) file_get_contents($root.'app/Support/Affiliations/FamilyMergeBilling.php'))->toContain('$installments[0][\'amount\'] = round($installments[0][\'amount\'] + $oneTimeUpgrades, 2);')
        ->and((string) file_get_contents($root.'app/Support/Upgrades/UpgradeRenewalRepricer.php'))->toContain("'billing_mode' => UpgradeFirstPaymentBilling::MODE");
});
