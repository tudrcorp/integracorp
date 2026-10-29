<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateUpgrade;
use App\Models\Affiliation;
use App\Models\User;
use App\Support\Upgrades\IndividualAffiliateUpgradeManager;
use App\Support\Upgrades\UpgradePricing;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

/**
 * Esquema mínimo en una conexión sqlite `:memory:` propia: la config cacheada
 * apunta a MySQL y este test escribe, así que nunca debe tocar la base real.
 */
function seedIndividualUpgradeScenario(): void
{
    Schema::create('affiliations', function (Blueprint $table): void {
        $table->timestamp('deleted_at')->nullable();
        $table->id();
        $table->string('code')->nullable();
        $table->string('status')->nullable();
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
        $table->string('relationship')->nullable();
        $table->string('birth_date')->nullable();
        $table->string('age')->nullable();
        $table->decimal('fee', 8, 2)->default(0);
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    createUpgradeCatalogTestSchema();

    Schema::create('affiliate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_id');
        upgradeAssignmentTestColumns($table);
    });

    Schema::create('collections', function (Blueprint $table): void {
        $table->id();
        $table->string('collection_invoice_number')->nullable();
        $table->string('affiliation_code')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->string('next_payment_date')->nullable();
        $table->date('filter_next_payment_date')->nullable();
        $table->string('expiration_date')->nullable();
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->decimal('pay_amount_usd', 8, 2)->nullable();
        $table->decimal('pay_amount_ves', 12, 2)->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('collection_observations', function (Blueprint $table): void {
        $table->id();
        $table->string('affiliation_code')->nullable();
        $table->string('affiliation_type')->nullable();
        $table->unsignedBigInteger('collection_id')->nullable();
        $table->date('due_date')->nullable();
        $table->decimal('amount', 12, 2)->nullable();
        $table->text('observation')->nullable();
        $table->unsignedBigInteger('created_by')->nullable();
        $table->string('created_by_name')->nullable();
        $table->timestamps();
    });

    Schema::create('paid_memberships', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('renovations', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_id')->nullable();
        $table->string('status')->nullable();
        $table->string('date_renewal')->nullable();
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

    DB::table('affiliations')->insert([
        ['id' => 10, 'code' => 'TDEC-IND-00010', 'status' => 'ACTIVA', 'payment_frequency' => 'TRIMESTRAL', 'fee_anual' => 1000, 'total_amount' => 250],
        ['id' => 11, 'code' => 'TDEC-IND-00011', 'status' => 'ACTIVA', 'payment_frequency' => 'ANUAL', 'fee_anual' => 300, 'total_amount' => 300],
    ]);

    DB::table('affiliates')->insert([
        ['id' => 1, 'affiliation_id' => 10, 'full_name' => 'ANA PEREZ', 'relationship' => 'TITULAR', 'age' => '30', 'fee' => 600, 'total_amount' => 150, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO'],
        ['id' => 2, 'affiliation_id' => 10, 'full_name' => 'LUIS PEREZ', 'relationship' => 'ESPOSO', 'age' => '40', 'fee' => 400, 'total_amount' => 100, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO'],
        ['id' => 3, 'affiliation_id' => 10, 'full_name' => 'EVA PEREZ', 'relationship' => 'HIJA', 'age' => '20', 'fee' => 200, 'total_amount' => 50, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'INACTIVO'],
        ['id' => 4, 'affiliation_id' => 11, 'full_name' => 'AJENO OTRO', 'relationship' => 'TITULAR', 'age' => '33', 'fee' => 300, 'total_amount' => 300, 'payment_frequency' => 'ANUAL', 'status' => 'ACTIVO'],
    ]);

    DB::table('collections')->insert([
        ['id' => 100, 'collection_invoice_number' => 'AC-0100', 'affiliation_code' => 'TDEC-IND-00010', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '01/12/2026', 'total_amount' => 250, 'status' => 'POR PAGAR'],
        ['id' => 101, 'collection_invoice_number' => 'AC-0101', 'affiliation_code' => 'TDEC-IND-00010', 'payment_frequency' => 'MENSUAL', 'next_payment_date' => '01/03/2027', 'total_amount' => 83.33, 'status' => 'POR PAGAR'],
        ['id' => 102, 'collection_invoice_number' => 'AC-0102', 'affiliation_code' => 'TDEC-IND-00010', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '01/09/2026', 'total_amount' => 250, 'status' => 'PAGADO'],
    ]);
}

function individualOwner(int $id = 10): Affiliation
{
    return Affiliation::query()->findOrFail($id);
}

function individualFee(int $id): float
{
    return (float) Affiliate::query()->findOrFail($id)->fee;
}

function individualCollection(int $id): float
{
    return (float) DB::table('collections')->where('id', $id)->value('total_amount');
}

beforeEach(function (): void {
    config()->set('database.connections.individual_upgrades_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'individual_upgrades_testing');
    DB::purge('individual_upgrades_testing');
    DB::setDefaultConnection('individual_upgrades_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    seedIndividualUpgradeScenario();
    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
    Storage::fake('public');
});

afterEach(function (): void {
    DB::purge('individual_upgrades_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('cobra a cada familiar el rango de su edad y ajusta afiliación y cuotas por pagar en proporción', function (): void {
    $result = IndividualAffiliateUpgradeManager::add(individualOwner(), [1, 2], [10], null, upgradeVouchersFor([1, 2]));

    $owner = individualOwner();

    expect($result['upgrades_created'])->toBe(2)
        ->and($result['annual_delta'])->toBe(100.0)
        ->and($result['collections_adjusted'])->toBe(2)
        ->and(individualFee(1))->toBe(640.0)
        ->and(individualFee(2))->toBe(460.0)
        ->and((float) Affiliate::query()->findOrFail(1)->total_amount)->toBe(160.0)
        ->and((float) $owner->fee_anual)->toBe(1100.0)
        ->and((float) $owner->total_amount)->toBe(275.0)
        ->and(individualCollection(100))->toBe(275.0)
        ->and(individualCollection(101))->toBe(91.66)
        ->and(individualCollection(102))->toBe(250.0)
        ->and(DB::table('collection_observations')->count())->toBe(2)
        ->and(DB::table('collection_observations')->where('collection_id', 100)->value('observation'))->toContain('Upgrade agregado')
        ->and(DB::table('logs')->where('action', 'AUDIT_BUSINESS_INDIVIDUAL_AFFILIATE_UPGRADES_ADDED')->exists())->toBeTrue();

    $rows = AffiliateUpgrade::query()->orderBy('affiliate_id')->get();

    expect($rows->pluck('amount')->map(fn ($amount) => (float) $amount)->all())->toBe([40.0, 60.0])
        ->and($rows->pluck('upgrade_coverage_age_range_id')->all())->toBe([100, 101])
        ->and($rows->first()->age_at_assignment)->toBe(30);
});

it('omite dados de baja, ajenos, repetidos y edades sin rango', function (): void {
    IndividualAffiliateUpgradeManager::add(individualOwner(), [1], [20], null, upgradeVouchersFor([1]));
    $result = IndividualAffiliateUpgradeManager::add(individualOwner(), [1, 2, 3, 4], [20], null, upgradeVouchersFor([1, 2, 3, 4]));

    expect($result['upgrades_created'])->toBe(0)
        ->and(collect($result['skipped'])->pluck('reason')->sort()->values()->all())->toBe(collect([
            UpgradePricing::SKIP_NOT_IN_AFFILIATION,
            UpgradePricing::SKIP_ALREADY_ACTIVE,
            UpgradePricing::SKIP_NO_RANGE,
            UpgradePricing::SKIP_AFFILIATE_INACTIVE,
        ])->sort()->values()->all())
        ->and(individualFee(4))->toBe(300.0)
        ->and((float) individualOwner(11)->fee_anual)->toBe(300.0);
});

it('bloquea sin escribir nada', function (Closure $arrange, string $message): void {
    $arrange();

    expect(fn () => IndividualAffiliateUpgradeManager::add(individualOwner(), [1], [10], null, upgradeVouchersFor([1])))
        ->toThrow(RuntimeException::class, $message);

    expect(AffiliateUpgrade::query()->count())->toBe(0)
        ->and(individualFee(1))->toBe(600.0)
        ->and((float) individualOwner()->fee_anual)->toBe(1000.0)
        ->and(individualCollection(100))->toBe(250.0);
})->with([
    'comprobante por aprobar' => [fn () => DB::table('paid_memberships')->insert(['affiliation_id' => 10, 'status' => 'PENDIENTE']), 'por aprobar'],
    'período de renovación' => [fn () => DB::table('renovations')->insert(['affiliation_id' => 10, 'status' => 'PERIODO DE RENOVACION', 'date_renewal' => '2026-11-15']), 'renueva el 15/11/2026'],
    'cuota con abono' => [fn () => DB::table('collections')->where('id', 101)->update(['pay_amount_usd' => 10]), 'abono registrado'],
    'afiliación excluida' => [fn () => DB::table('affiliations')->where('id', 10)->update(['status' => 'EXCLUIDO']), 'solo se cambian en afiliaciones activas'],
]);

it('quita el upgrade, revierte montos y deja traza', function (): void {
    IndividualAffiliateUpgradeManager::add(individualOwner(), [1, 2], [10], null, upgradeVouchersFor([1, 2]));
    $id = AffiliateUpgrade::query()->where('affiliate_id', 2)->value('id');

    $result = IndividualAffiliateUpgradeManager::deactivate(individualOwner(), [$id]);

    expect($result['upgrades_removed'])->toBe(1)
        ->and($result['annual_delta'])->toBe(-60.0)
        ->and(individualFee(2))->toBe(400.0)
        ->and((float) individualOwner()->fee_anual)->toBe(1040.0)
        ->and(individualCollection(100))->toBe(260.0)
        ->and(AffiliateUpgrade::query()->find($id)->status)->toBe('INACTIVO')
        ->and(AffiliateUpgrade::query()->find($id)->deactivation_reason)->toBe(IndividualAffiliateUpgradeManager::REMOVAL_REASON)
        ->and(IndividualAffiliateUpgradeManager::activeTotalFor(Affiliate::query()->findOrFail(1)))->toBe(40.0);

    expect(fn () => IndividualAffiliateUpgradeManager::deactivate(individualOwner(), [$id]))
        ->toThrow(RuntimeException::class, 'ya no están activos');
});

it('no quita upgrades de familiares de otra afiliación', function (): void {
    IndividualAffiliateUpgradeManager::add(individualOwner(11), [4], [20], null, upgradeVouchersFor([4]));
    $id = AffiliateUpgrade::query()->sole()->id;

    expect(fn () => IndividualAffiliateUpgradeManager::deactivate(individualOwner(10), [$id]))
        ->toThrow(RuntimeException::class);

    expect(individualFee(4))->toBe(320.0);
});

it('agrupa y quita en lote por upgrade del catálogo', function (): void {
    IndividualAffiliateUpgradeManager::add(individualOwner(), [1, 2], [10], null, upgradeVouchersFor([1, 2]));

    expect(IndividualAffiliateUpgradeManager::activeUpgradesAmong(individualOwner(), [1, 2]))
        ->toBe([1 => ['name' => 'ODONTOLOGÍA', 'total' => 2]]);

    IndividualAffiliateUpgradeManager::deactivate(
        individualOwner(),
        IndividualAffiliateUpgradeManager::activeAssignmentIdsForUpgrades(individualOwner(), [1, 2], ['1']),
    );

    expect(individualFee(1))->toBe(600.0)
        ->and(individualFee(2))->toBe(400.0)
        ->and((float) individualOwner()->fee_anual)->toBe(1000.0)
        ->and(individualCollection(100))->toBe(250.0);
});

it('sin Voucher ILS no se agrega nada, y el voucher queda guardado con el upgrade', function (): void {
    expect(fn () => IndividualAffiliateUpgradeManager::add(individualOwner(), [1], [10]))
        ->toThrow(InvalidArgumentException::class, 'Falta el Voucher ILS de ANA PEREZ');

    expect(AffiliateUpgrade::query()->count())->toBe(0)
        ->and(individualFee(1))->toBe(600.0);

    $vouchers = upgradeVouchersFor([1]);
    IndividualAffiliateUpgradeManager::add(individualOwner(), [1], [10], null, $vouchers);

    $row = AffiliateUpgrade::query()->sole();

    expect($row->ils_voucher_code)->toBe($vouchers[1]['code'])
        ->and($row->ils_date_end->toDateString())->toBe($vouchers[1]['date_end'])
        ->and($row->ils_document_path)->toBe($vouchers[1]['document_path']);
});

it('rechaza vouchers inválidos sin escribir nada', function (Closure $mutate, string $message): void {
    $vouchers = upgradeVouchersFor([1, 2]);
    $vouchers = $mutate($vouchers);

    expect(fn () => IndividualAffiliateUpgradeManager::add(individualOwner(), [1, 2], [10], null, $vouchers))
        ->toThrow(InvalidArgumentException::class, $message);

    expect(AffiliateUpgrade::query()->count())->toBe(0)
        ->and((float) individualOwner()->fee_anual)->toBe(1000.0);
})->with([
    'sin número' => [fn (array $v): array => array_replace_recursive($v, [1 => ['code' => '  ']]), 'Falta el número'],
    'hasta antes de desde' => [fn (array $v): array => array_replace_recursive($v, [1 => ['date_init' => now()->addDays(10)->toDateString(), 'date_end' => now()->addDays(5)->toDateString()]]), 'no puede ser anterior'],
    'vencido' => [fn (array $v): array => array_replace_recursive($v, [1 => ['date_init' => now()->subYear()->toDateString(), 'date_end' => now()->subDay()->toDateString()]]), 'venció'],
    'sin comprobante' => [fn (array $v): array => array_replace_recursive($v, [1 => ['document_path' => 'upgrades-ils/no-existe.pdf']]), 'Adjunte el comprobante'],
    'ruta con trucos' => [fn (array $v): array => array_replace_recursive($v, [1 => ['document_path' => '../.env']]), 'Adjunte el comprobante'],
    'mismo número en el lote' => [fn (array $v): array => array_replace_recursive($v, [2 => ['code' => strtolower($v[1]['code'])]]), 'está repetido'],
    'falta el de un familiar' => [fn (array $v): array => [1 => $v[1]], 'Falta el Voucher ILS de LUIS PEREZ'],
]);

it('un número de voucher ya usado en el sistema no se acepta, sin importar mayúsculas', function (): void {
    $first = upgradeVouchersFor([1]);
    IndividualAffiliateUpgradeManager::add(individualOwner(), [1], [10], null, $first);

    $second = upgradeVouchersFor([2]);
    $second[2]['code'] = ' '.strtolower($first[1]['code']).' ';

    expect(fn () => IndividualAffiliateUpgradeManager::add(individualOwner(), [2], [10], null, $second))
        ->toThrow(InvalidArgumentException::class, 'ya está registrado');

    expect(App\Support\Upgrades\UpgradeIlsVoucher::isCodeTaken($first[1]['code']))->toBeTrue()
        ->and(App\Support\Upgrades\UpgradeIlsVoucher::isCodeTaken('ILS-NUEVO-999'))->toBeFalse();
});

it('la vista previa muestra el recálculo exacto sin escribir nada', function (): void {
    $preview = IndividualAffiliateUpgradeManager::preview(individualOwner(), [1, 2, 3], 10);
    $rows = collect($preview['rows'])->keyBy('id');

    expect($preview['error'])->toBeNull()
        ->and($preview['blockers'])->toBe([])
        ->and($preview['upgrade']['covers'])->toBe('0 a 64 años')
        ->and($rows[1]['receives'])->toBeTrue()
        ->and($rows[1]['price'])->toBe(40.0)
        ->and($rows[1]['fee_after'])->toBe(640.0)
        ->and($rows[1]['period_after'])->toBe(160.0)
        ->and($rows[2]['price'])->toBe(60.0)
        ->and($rows[3]['receives'])->toBeFalse()
        ->and($rows[3]['reason'])->toBe('está inactivo')
        ->and($preview['owner']['annual_after'])->toBe(1100.0)
        ->and($preview['owner']['period_after'])->toBe(275.0)
        ->and(array_column($preview['collections'], 'after'))->toBe([275.0, 91.66])
        ->and(App\Support\Upgrades\UpgradeAssignmentPreview::receivingIds($preview))->toBe([1, 2])
        ->and(AffiliateUpgrade::query()->count())->toBe(0)
        ->and(individualFee(1))->toBe(600.0);
});

it('la vista previa avisa los bloqueos antes de confirmar', function (): void {
    DB::table('paid_memberships')->insert(['affiliation_id' => 10, 'status' => 'PENDIENTE']);

    $preview = IndividualAffiliateUpgradeManager::preview(individualOwner(), [1], 10);

    expect($preview['blockers'][0])->toContain('por aprobar')
        ->and(App\Support\Upgrades\UpgradeAssignmentPreview::blockingMessage($preview))->toContain('por aprobar')
        ->and(App\Support\Upgrades\UpgradeAssignmentPreview::blockingMessage(IndividualAffiliateUpgradeManager::preview(individualOwner(), [1], 999)))->toContain('ya no existe');
});

it('la modal pinta el recálculo con su detalle', function (): void {
    $html = view('filament.business.upgrades.assignment-preview', [
        'preview' => IndividualAffiliateUpgradeManager::preview(individualOwner(), [1, 3], 10),
    ])->render();

    expect($html)
        ->toContain('Paso 2 · Recálculo')
        ->toContain('ODONTOLOGÍA')
        ->toContain('cubre 0 a 64 años')
        ->toContain('1 de 2')
        ->toContain('+US$ 40,00')
        ->toContain('US$ 600,00 → <span class="font-bold">US$ 640,00</span>')
        ->toContain('No lo recibe')
        ->toContain('está inactivo')
        ->toContain('Afiliación TDEC-IND-00010')
        ->toContain('US$ 1.000,00 → <span class="font-bold">US$ 1.040,00</span>')
        ->toContain('AC-0100');
});
