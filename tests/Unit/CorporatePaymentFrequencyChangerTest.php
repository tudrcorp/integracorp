<?php

declare(strict_types=1);

use App\Exceptions\CorporatePaymentFrequencyChangeBlockedException;
use App\Jobs\CreateAvisoDeCobro;
use App\Jobs\NotifyAdministrationOfCorporatePaymentFrequencyChangeJob;
use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\CorporatePaymentFrequencyChangeMail;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use App\Models\User;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeNotificationMessage;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeRecipients;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChangeReverser;
use App\Support\AffiliationCorporates\CorporatePaymentUploadAvailability;
use App\Support\CorporateDocumentPlanAmounts;
use Carbon\Carbon;
use Filament\Notifications\DatabaseNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

/**
 * Esquema mínimo en una conexión sqlite `:memory:` propia: este test escribe
 * y la config cacheada apunta a MySQL, así que nunca toca la base real.
 */
function seedFrequencyScenario(): void
{
    Schema::create('affiliation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->string('code')->nullable();
        $table->string('name_corporate')->nullable();
        $table->string('rif')->nullable();
        $table->string('address')->nullable();
        $table->string('phone')->nullable();
        $table->string('email')->nullable();
        $table->string('status')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->integer('poblation')->default(0);
        $table->string('fee_anual')->nullable();
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->timestamps();
    });

    Schema::create('affiliate_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->decimal('fee', 8, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->decimal('subtotal_payment_frequency', 8, 2)->default(0);
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
        $table->integer('total_persons')->default(0);
        $table->decimal('subtotal_anual', 8, 2)->default(0);
        $table->decimal('subtotal_quarterly', 8, 2)->default(0);
        $table->decimal('subtotal_biannual', 8, 2)->default(0);
        $table->decimal('subtotal_monthly', 8, 2)->default(0);
        $table->string('payment_frequency')->nullable();
        $table->timestamps();
    });

    Schema::create('renovation_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('payment_frequency')->nullable();
        $table->timestamps();
    });

    Schema::create('collections', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('sale_id')->nullable();
        $table->string('include_date')->nullable();
        $table->string('owner_code')->nullable();
        $table->string('collection_invoice_number')->nullable();
        $table->string('affiliation_code')->nullable();
        $table->string('affiliate_full_name')->nullable();
        $table->string('affiliate_status')->nullable();
        $table->string('type')->nullable();
        $table->string('payment_frequency')->nullable();
        $table->string('next_payment_date')->nullable();
        $table->date('filter_next_payment_date')->nullable();
        $table->string('expiration_date')->nullable();
        $table->decimal('total_amount', 8, 2)->default(0);
        $table->decimal('pay_amount_usd', 8, 2)->nullable();
        $table->decimal('pay_amount_ves', 8, 2)->nullable();
        $table->string('status')->nullable();
        $table->integer('days')->nullable();
        $table->string('created_by')->nullable();
        $table->timestamps();
    });

    Schema::create('paid_membership_corporates', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('status')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliate_corporate_upgrades', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('affiliate_corporate_id');
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('name');
        $table->decimal('amount', 10, 2);
        $table->string('status')->default('ACTIVO');
        $table->timestamps();
    });

    Schema::create('plans', function (Blueprint $table): void {
        $table->id();
        $table->string('description')->nullable();
        $table->timestamps();
    });

    Schema::create('age_ranges', function (Blueprint $table): void {
        $table->id();
        $table->string('range')->nullable();
        $table->timestamps();
    });

    Schema::create('affiliation_corporate_payment_frequency_changes', function (Blueprint $table): void {
        $table->id();
        $table->uuid('batch_uuid');
        $table->unsignedBigInteger('affiliation_corporate_id');
        $table->string('affiliation_code')->nullable();
        $table->string('affiliation_name')->nullable();
        $table->string('previous_frequency')->nullable();
        $table->string('new_frequency');
        $table->decimal('fee_anual', 12, 2)->default(0);
        $table->decimal('previous_total_amount', 12, 2)->default(0);
        $table->decimal('new_total_amount', 12, 2)->default(0);
        $table->decimal('pending_balance', 12, 2)->default(0);
        $table->json('cancelled_collections')->nullable();
        $table->json('created_collections')->nullable();
        $table->json('snapshot')->nullable();
        $table->string('status')->default('APLICADO');
        $table->unsignedBigInteger('performed_by_id')->nullable();
        $table->string('performed_by_name')->nullable();
        $table->string('performed_from')->nullable();
        $table->string('ip')->nullable();
        $table->text('user_agent')->nullable();
        $table->json('notification_log')->nullable();
        $table->timestamp('validated_at')->nullable();
        $table->unsignedBigInteger('validated_by_id')->nullable();
        $table->string('validated_by_name')->nullable();
        $table->timestamp('reversed_at')->nullable();
        $table->unsignedBigInteger('reversed_by_id')->nullable();
        $table->string('reversed_by_name')->nullable();
        $table->text('reversal_reason')->nullable();
        $table->timestamps();
    });

    Schema::create('users', function (Blueprint $table): void {
        $table->id();
        $table->string('name')->nullable();
        $table->string('email')->nullable();
        $table->string('phone')->nullable();
        $table->string('status')->nullable();
        $table->text('departament')->nullable();
        $table->string('password')->nullable();
        $table->timestamps();
    });

    Schema::create('system_notification_recipient_settings', function (Blueprint $table): void {
        $table->id();
        $table->string('notification_key');
        $table->json('notification_emails')->nullable();
        $table->json('notification_phones')->nullable();
        $table->boolean('is_active')->default(true);
        $table->string('updated_by')->nullable();
        $table->timestamps();
    });

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
        ['id' => 1, 'code' => 'TDEC-COR-00001', 'name_corporate' => 'EMPRESA TRIMESTRAL', 'status' => 'ACTIVA', 'payment_frequency' => 'TRIMESTRAL', 'fee_anual' => '1200', 'total_amount' => 300],
        ['id' => 2, 'code' => 'TDEC-COR-00002', 'name_corporate' => 'EMPRESA NUEVA', 'status' => 'PRE-APROBADA', 'payment_frequency' => 'ANUAL', 'fee_anual' => '600', 'total_amount' => 600],
        ['id' => 3, 'code' => 'TDEC-COR-00003', 'name_corporate' => 'EMPRESA EXCLUIDA', 'status' => 'EXCLUIDO', 'payment_frequency' => 'ANUAL', 'fee_anual' => '600', 'total_amount' => 600],
    ]);

    DB::table('affiliate_corporates')->insert([
        ['id' => 1, 'affiliation_corporate_id' => 1, 'fee' => 700, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 175, 'status' => 'ACTIVO'],
        ['id' => 2, 'affiliation_corporate_id' => 1, 'fee' => 500, 'payment_frequency' => 'TRIMESTRAL', 'subtotal_payment_frequency' => 125, 'status' => 'ACTIVO'],
        ['id' => 3, 'affiliation_corporate_id' => 2, 'fee' => 600, 'payment_frequency' => 'ANUAL', 'subtotal_payment_frequency' => 600, 'status' => 'ACTIVO'],
    ]);

    DB::table('plans')->insert(['id' => 5, 'description' => 'PLAN ESPECIAL']);
    DB::table('age_ranges')->insert(['id' => 7, 'range' => '18 A 64']);

    DB::table('afilliation_corporate_plans')->insert([
        'affiliation_corporate_id' => 1, 'plan_id' => 5, 'age_range_id' => 7, 'fee' => 600, 'total_persons' => 2,
        'subtotal_anual' => 1200, 'subtotal_quarterly' => 300, 'subtotal_biannual' => 600, 'subtotal_monthly' => 100, 'payment_frequency' => 'TRIMESTRAL',
    ]);

    DB::table('renovation_corporates')->insert(['affiliation_corporate_id' => 1, 'payment_frequency' => 'TRIMESTRAL']);

    DB::table('collections')->insert([
        ['id' => 10, 'sale_id' => 77, 'collection_invoice_number' => '09-00100', 'affiliation_code' => 'TDEC-COR-00001', 'affiliate_full_name' => 'EMPRESA TRIMESTRAL', 'type' => 'AFILIACIÓN CORPORATIVA', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '10/07/2026', 'filter_next_payment_date' => '2026-07-10', 'total_amount' => 300, 'status' => 'PAGADO'],
        ['id' => 11, 'sale_id' => 77, 'collection_invoice_number' => '09-00101', 'affiliation_code' => 'TDEC-COR-00001', 'affiliate_full_name' => 'EMPRESA TRIMESTRAL', 'type' => 'AFILIACIÓN CORPORATIVA', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '10/10/2026', 'filter_next_payment_date' => '2026-10-10', 'total_amount' => 300, 'status' => 'POR PAGAR'],
        ['id' => 12, 'sale_id' => 77, 'collection_invoice_number' => '09-00102', 'affiliation_code' => 'TDEC-COR-00001', 'affiliate_full_name' => 'EMPRESA TRIMESTRAL', 'type' => 'AFILIACIÓN CORPORATIVA', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '10-01-2027', 'filter_next_payment_date' => '2027-01-10', 'total_amount' => 300, 'status' => 'POR PAGAR'],
        ['id' => 13, 'sale_id' => 77, 'collection_invoice_number' => '09-00103', 'affiliation_code' => 'TDEC-COR-00001', 'affiliate_full_name' => 'EMPRESA TRIMESTRAL', 'type' => 'AFILIACIÓN CORPORATIVA', 'payment_frequency' => 'TRIMESTRAL', 'next_payment_date' => '10/04/2027', 'filter_next_payment_date' => '2027-04-10', 'total_amount' => 300, 'status' => 'POR PAGAR'],
    ]);
}

function frequencyOwner(int $id = 1): AffiliationCorporate
{
    return AffiliationCorporate::query()->findOrFail($id);
}

/**
 * @return list<array{date: Carbon, months: int, amount: float}>
 */
function pendingInstallmentsOf(int $count, int $months, float $amount, string $firstDate = '2026-10-10'): array
{
    return array_map(fn (int $index): array => [
        'date' => Carbon::parse($firstDate)->addMonthsNoOverflow($index * $months),
        'months' => $months,
        'amount' => $amount,
    ], range(0, $count - 1));
}

beforeEach(function (): void {
    config()->set('database.connections.corporate_frequency_testing', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $this->previousConnection = config('database.default');
    config()->set('database.default', 'corporate_frequency_testing');
    DB::purge('corporate_frequency_testing');
    DB::setDefaultConnection('corporate_frequency_testing');

    expect(DB::connection()->getDriverName())->toBe('sqlite')
        ->and(DB::connection()->getDatabaseName())->toBe(':memory:');

    seedFrequencyScenario();
    Bus::fake([CreateAvisoDeCobro::class, NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, SendNotificacionWhatsApp::class]);
    Auth::setUser(User::factory()->make(['id' => 99, 'name' => 'Analista Negocios']));
});

afterEach(function (): void {
    DB::purge('corporate_frequency_testing');
    config()->set('database.default', $this->previousConnection);
    DB::setDefaultConnection($this->previousConnection);
});

it('conoce las cuatro frecuencias y su equivalencia', function (): void {
    expect(array_keys(CorporatePaymentFrequency::options()))->toBe(['ANUAL', 'SEMESTRAL', 'TRIMESTRAL', 'MENSUAL'])
        ->and(CorporatePaymentFrequency::normalize(' mensual '))->toBe('MENSUAL')
        ->and(CorporatePaymentFrequency::normalize('QUINCENAL'))->toBeNull()
        ->and(CorporatePaymentFrequency::monthsPerInstallment('SEMESTRAL'))->toBe(6)
        ->and(CorporatePaymentFrequency::periodAmount(1200, 'MENSUAL'))->toBe(100.0)
        ->and(CorporatePaymentFrequency::periodAmount(1000, 'TRIMESTRAL'))->toBe(250.0);
});

it('el calculador de tarifas ya divide entre 12 la frecuencia mensual', function (): void {
    $calculator = new AffiliationAffiliateFeeCalculator;

    expect($calculator->totalAmountForPaymentFrequency(1200.0, 'MENSUAL'))->toBe(100.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, ' trimestral '))->toBe(300.0)
        ->and($calculator->totalAmountForPaymentFrequency(1200.0, 'ANUAL'))->toBe(1200.0);
});

it('reparte los meses y el saldo pendientes en la nueva frecuencia', function (int $count, int $months, float $amount, string $target, array $expectedMonths, array $expectedAmounts): void {
    $schedule = CorporatePaymentFrequencyChanger::reschedule(pendingInstallmentsOf($count, $months, $amount), $target);

    expect(array_column($schedule, 'months'))->toBe($expectedMonths)
        ->and(array_column($schedule, 'amount'))->toBe($expectedAmounts)
        ->and(round(array_sum(array_column($schedule, 'amount')), 2))->toBe(round($count * $amount, 2))
        ->and($schedule[0]['date']->format('Y-m-d'))->toBe('2026-10-10');
})->with([
    'trimestral a mensual' => [3, 3, 300.0, 'MENSUAL', [1, 1, 1, 1, 1, 1, 1, 1, 1], [100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0, 100.0]],
    'trimestral a semestral prorratea la última' => [3, 3, 300.0, 'SEMESTRAL', [6, 3], [600.0, 300.0]],
    'trimestral a anual' => [3, 3, 300.0, 'ANUAL', [9], [900.0]],
    'anual a trimestral' => [1, 12, 1000.0, 'TRIMESTRAL', [3, 3, 3, 3], [250.0, 250.0, 250.0, 250.0]],
    'redondeo cuadra el saldo' => [1, 12, 1000.0, 'MENSUAL', [1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1], [83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.33, 83.37]],
]);

it('lee las fechas de cobro en los formatos que hay en la base', function (?string $value, ?string $filter, ?string $expected): void {
    expect(CorporatePaymentFrequencyChanger::parseDueDate($value, $filter)?->format('Y-m-d'))->toBe($expected);
})->with([
    'd/m/Y' => ['10/07/2026', null, '2026-07-10'],
    'd-m-Y' => ['28-04-2027', null, '2027-04-28'],
    'sin ceros' => ['1/3/2026', null, '2026-03-01'],
    'imposible cae al filtro' => ['31/02/2026', '2026-03-05', '2026-03-05'],
    'vacía con filtro con hora' => ['', '2026-12-05 00:00:00', '2026-12-05'],
    'ilegible' => ['pronto', null, null],
]);

it('cambia la frecuencia y reemplaza los avisos pendientes por el mismo saldo', function (): void {
    $result = CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'mensual');

    $owner = frequencyOwner();
    $pending = DB::table('collections')->where('status', 'POR PAGAR')->orderBy('id')->get();

    expect($result)->toMatchArray(['changed' => true, 'previous' => 'TRIMESTRAL', 'frequency' => 'MENSUAL', 'period_amount' => 100.0, 'cancelled' => 3, 'created' => 9, 'notices' => 9])
        ->and($owner->payment_frequency)->toBe('MENSUAL')
        ->and((float) $owner->total_amount)->toBe(100.0)
        ->and((float) $owner->fee_anual)->toBe(1200.0)
        ->and(DB::table('collections')->whereIn('id', [11, 12, 13])->pluck('status')->unique()->all())->toBe(['CANCELADO'])
        ->and(DB::table('collections')->where('id', 10)->value('status'))->toBe('PAGADO')
        ->and($pending)->toHaveCount(9)
        ->and(round((float) $pending->sum('total_amount'), 2))->toBe(900.0)
        ->and($pending->pluck('next_payment_date')->all())->toBe([
            '10/10/2026', '10/11/2026', '10/12/2026', '10/01/2027', '10/02/2027', '10/03/2027', '10/04/2027', '10/05/2027', '10/06/2027',
        ])
        ->and($pending->first()->expiration_date)->toBe('09/11/2026')
        ->and($pending->first()->collection_invoice_number)->toEndWith('-00104')
        ->and($pending->last()->collection_invoice_number)->toEndWith('-00112')
        ->and($pending->pluck('payment_frequency')->unique()->all())->toBe(['MENSUAL'])
        ->and($pending->pluck('sale_id')->unique()->all())->toBe([77])
        ->and($pending->pluck('type')->unique()->all())->toBe(['AFILIACIÓN CORPORATIVA'])
        ->and(DB::table('affiliate_corporates')->where('affiliation_corporate_id', 1)->pluck('subtotal_payment_frequency', 'id')->map(fn ($v) => (float) $v)->all())->toBe([1 => 58.33, 2 => 41.67])
        ->and(DB::table('affiliate_corporates')->where('id', 3)->value('payment_frequency'))->toBe('ANUAL')
        ->and(DB::table('afilliation_corporate_plans')->value('payment_frequency'))->toBe('MENSUAL')
        ->and(DB::table('renovation_corporates')->value('payment_frequency'))->toBe('MENSUAL');

    Bus::assertDispatchedTimes(CreateAvisoDeCobro::class, 9);
});

it('la vista previa anticipa lo mismo que se aplica, sin escribir', function (): void {
    $preview = CorporatePaymentFrequencyChanger::preview(frequencyOwner(), 'SEMESTRAL');

    expect($preview['blocked'])->toBeNull()
        ->and($preview['period_amount'])->toBe(600.0)
        ->and($preview['pending_count'])->toBe(3)
        ->and($preview['pending_balance'])->toBe(900.0)
        ->and($preview['schedule'])->toBe([
            ['date' => '10/10/2026', 'months' => 6, 'amount' => 600.0],
            ['date' => '10/04/2027', 'months' => 3, 'amount' => 300.0],
        ])
        ->and(frequencyOwner()->payment_frequency)->toBe('TRIMESTRAL')
        ->and(DB::table('collections')->where('status', 'POR PAGAR')->count())->toBe(3);
});

it('sin avisos pendientes solo recalcula el monto por período', function (): void {
    $result = CorporatePaymentFrequencyChanger::change(frequencyOwner(2), 'TRIMESTRAL');

    expect($result['created'])->toBe(0)
        ->and($result['cancelled'])->toBe(0)
        ->and((float) frequencyOwner(2)->total_amount)->toBe(150.0)
        ->and((float) DB::table('affiliate_corporates')->where('id', 3)->value('subtotal_payment_frequency'))->toBe(150.0);

    Bus::assertNotDispatched(CreateAvisoDeCobro::class);
});

it('no hace nada si ya tiene esa frecuencia', function (): void {
    $result = CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'TRIMESTRAL');

    expect($result['changed'])->toBeFalse()
        ->and(DB::table('collections')->where('status', 'POR PAGAR')->count())->toBe(3);
});

it('bloquea sin tocar nada cuando no es seguro cambiar', function (Closure $arrange, string $message): void {
    $arrange();

    expect(fn () => CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL'))
        ->toThrow(CorporatePaymentFrequencyChangeBlockedException::class, $message);

    expect(frequencyOwner()->payment_frequency)->toBe('TRIMESTRAL')
        ->and((float) frequencyOwner()->total_amount)->toBe(300.0)
        ->and(DB::table('collections')->where('status', 'POR PAGAR')->count())->toBe(3)
        ->and(DB::table('collections')->count())->toBe(4)
        ->and(DB::table('affiliate_corporates')->where('id', 1)->value('payment_frequency'))->toBe('TRIMESTRAL');
})->with([
    'afiliación excluida' => [fn () => DB::table('affiliation_corporates')->where('id', 1)->update(['status' => 'EXCLUIDO']), 'excluido'],
    'comprobante por aprobar' => [fn () => DB::table('paid_membership_corporates')->insert(['affiliation_corporate_id' => 1, 'status' => 'PENDIENTE']), 'pendiente de aprobación'],
    'aviso con abono' => [fn () => DB::table('collections')->where('id', 12)->update(['pay_amount_usd' => 50]), 'abono registrado'],
    'aviso con fecha ilegible' => [fn () => DB::table('collections')->where('id', 13)->update(['next_payment_date' => 'N/A', 'filter_next_payment_date' => null]), 'No se pudo leer la fecha'],
    'avisos en cero' => [fn () => DB::table('collections')->where('status', 'POR PAGAR')->update(['total_amount' => 0]), 'suman 0,00'],
]);

it('rechaza una frecuencia que no existe', function (): void {
    expect(fn () => CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'QUINCENAL'))
        ->toThrow(InvalidArgumentException::class);
});

it('en lote procesa cada afiliación por separado e informa las bloqueadas', function (): void {
    $summary = CorporatePaymentFrequencyChanger::changeMany(AffiliationCorporate::query()->orderBy('id')->get(), 'ANUAL');

    expect($summary['changed'])->toBe(['TDEC-COR-00001 · EMPRESA TRIMESTRAL'])
        ->and($summary['unchanged'])->toBe(['TDEC-COR-00002 · EMPRESA NUEVA'])
        ->and($summary['blocked'])->toHaveCount(1)
        ->and($summary['blocked'][0]['name'])->toBe('TDEC-COR-00003 · EMPRESA EXCLUIDA')
        ->and($summary['created'])->toBe(1)
        ->and((float) DB::table('collections')->where('status', 'POR PAGAR')->value('total_amount'))->toBe(900.0)
        ->and(frequencyOwner(3)->payment_frequency)->toBe('ANUAL');
});

it('el botón de cargar pago sigue la cobranza real y no un conteo fijo', function (): void {
    expect(CorporatePaymentUploadAvailability::isFullyPaid(frequencyOwner(2)))->toBeFalse();

    DB::table('paid_membership_corporates')->insert(['affiliation_corporate_id' => 1, 'status' => 'APROBADO']);
    expect(CorporatePaymentUploadAvailability::isFullyPaid(frequencyOwner()))->toBeFalse();

    DB::table('collections')->where('status', 'POR PAGAR')->update(['status' => 'PAGADO']);
    expect(CorporatePaymentUploadAvailability::isFullyPaid(frequencyOwner()))->toBeTrue();

    DB::table('paid_membership_corporates')->insert(['affiliation_corporate_id' => 2, 'status' => 'RECHAZADO']);
    expect(CorporatePaymentUploadAvailability::isFullyPaid(frequencyOwner(2)))->toBeFalse();

    DB::table('paid_membership_corporates')->insert(['affiliation_corporate_id' => 2, 'status' => 'PENDIENTE']);
    expect(CorporatePaymentUploadAvailability::isFullyPaid(frequencyOwner(2)))->toBeTrue();
});

it('los PDF corporativos toman el subtotal semestral real y toleran filas incompletas', function (array $row, ?string $fallback, float $expected): void {
    expect(CorporateDocumentPlanAmounts::periodAmount($row, $fallback))->toBe($expected);
})->with([
    'semestral usa subtotal_biannual' => [['payment_frequency' => 'SEMESTRAL', 'subtotal_anual' => 1200, 'subtotal_biannual' => 600], null, 600.0],
    'semestral sin columna calcula' => [['payment_frequency' => 'SEMESTRAL', 'subtotal_anual' => 1200], null, 600.0],
    'subtotal en cero es dato viejo' => [['payment_frequency' => 'TRIMESTRAL', 'subtotal_anual' => 1200, 'subtotal_quarterly' => 0], null, 300.0],
    'mensual' => [['payment_frequency' => 'MENSUAL', 'subtotal_anual' => 1200, 'subtotal_monthly' => 100], null, 100.0],
    'sin frecuencia usa la del documento' => [['subtotal_anual' => 1200], 'TRIMESTRAL', 300.0],
    'sin nada es anual' => [['subtotal_anual' => 1200], null, 1200.0],
]);

it('el aviso de cobro corporativo renderiza en semestral con upgrades', function (): void {
    $html = view('documents.aviso-de-cobro-corporativo', ['data' => [
        'invoice_number' => '09-00200',
        'emission_date' => '10/10/2026',
        'full_name_ti' => 'EMPRESA TRIMESTRAL',
        'ci_rif_ti' => '123',
        'address_ti' => 'CARACAS',
        'phone_ti' => '0212',
        'email_ti' => 'a@b.c',
        'total_amount' => 650.0,
        'plan' => [[
            'plan_id' => 5, 'coverage_id' => null, 'age_range_id' => 7, 'payment_frequency' => 'SEMESTRAL',
            'subtotal_anual' => 1200, 'subtotal_quarterly' => 300, 'subtotal_biannual' => 600, 'subtotal_monthly' => 100,
        ]],
        'upgrades' => [['name' => 'ÓPTICA', 'affiliates' => 2, 'annual_amount' => 100.0, 'amount' => 50.0]],
        'frequency' => 'SEMESTRAL',
        'affiliates_count' => 2,
    ]])->render();

    expect($html)
        ->toContain('PLAN ESPECIAL')
        ->toContain('RANGO DE EDAD: 18 A 64')
        ->toContain('FRECUENCIA DE PAGO: SEMESTRAL')
        ->toContain('US$600.00')
        ->toContain('UPGRADE: ÓPTICA')
        ->toContain('US$50.00')
        ->toContain('Monto Total: US$650.00');
});

/**
 * Registro, avisos a Administración y reverso.
 */
function frequencyChangeRecord(): AffiliationCorporatePaymentFrequencyChange
{
    return AffiliationCorporatePaymentFrequencyChange::query()->latest('id')->firstOrFail();
}

function reverseReason(): string
{
    return 'El cliente pidió seguir pagando trimestral; el cambio fue un error.';
}

function seedAdministrationRecipients(): void
{
    DB::table('users')->insert([
        ['id' => 10, 'name' => 'Ana Administración', 'email' => 'ana@tudrencasa.com', 'phone' => '0414-111.2233', 'status' => 'ACTIVO', 'departament' => json_encode(['ADMINISTRACION', 'NEGOCIOS'])],
        ['id' => 11, 'name' => 'Buzón Administración', 'email' => 'administracion@tudrencasa.com', 'phone' => null, 'status' => 'ACTIVO', 'departament' => 'ADMINISTRACION'],
        ['id' => 12, 'name' => 'Ex colaborador', 'email' => 'ex@tudrencasa.com', 'phone' => '04141112234', 'status' => 'INACTIVO', 'departament' => json_encode(['ADMINISTRACION'])],
        ['id' => 13, 'name' => 'Analista Negocios', 'email' => 'negocios@tudrencasa.com', 'phone' => '04121234567', 'status' => 'ACTIVO', 'departament' => json_encode(['NEGOCIOS'])],
    ]);

    DB::table('system_notification_recipient_settings')->insert([
        'notification_key' => 'corporate_payment_frequency_change',
        'notification_emails' => json_encode(['ANA@tudrencasa.com', 'cobranza@tudrencasa.com']),
        'notification_phones' => json_encode(['04241234567']),
        'is_active' => true,
    ]);
}

it('registra cada cambio con la foto anterior, los avisos y quién lo hizo', function (): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');

    $change = frequencyChangeRecord();

    expect($change->status)->toBe('APLICADO')
        ->and($change->affiliation_code)->toBe('TDEC-COR-00001')
        ->and($change->previous_frequency)->toBe('TRIMESTRAL')
        ->and($change->new_frequency)->toBe('MENSUAL')
        ->and((float) $change->previous_total_amount)->toBe(300.0)
        ->and((float) $change->new_total_amount)->toBe(100.0)
        ->and((float) $change->pending_balance)->toBe(900.0)
        ->and($change->performed_by_name)->toBe('Analista Negocios')
        ->and($change->performed_by_id)->toBe(99)
        ->and(array_column($change->cancelled_collections, 'id'))->toBe([11, 12, 13])
        ->and($change->created_collections)->toHaveCount(9)
        ->and($change->snapshot['affiliation'])->toBe(['payment_frequency' => 'TRIMESTRAL', 'total_amount' => 300])
        ->and(array_column($change->snapshot['affiliates'], 'subtotal_payment_frequency'))->toEqual([175, 125])
        ->and($change->snapshot['renovations'][0]['payment_frequency'])->toBe('TRIMESTRAL');

    Bus::assertDispatchedTimes(NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, 1);
    Bus::assertDispatched(NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, fn ($job): bool => $job->changeIds === [$change->id] && $job->event === 'applied');
});

it('el cambio masivo notifica una sola vez con todas las afiliaciones del lote', function (): void {
    CorporatePaymentFrequencyChanger::changeMany(AffiliationCorporate::query()->orderBy('id')->get(), 'MENSUAL');

    $changes = AffiliationCorporatePaymentFrequencyChange::query()->orderBy('id')->get();

    expect($changes)->toHaveCount(2)
        ->and($changes->pluck('batch_uuid')->unique())->toHaveCount(1);

    Bus::assertDispatchedTimes(NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, 1);
    Bus::assertDispatched(NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, fn ($job): bool => $job->changeIds === $changes->modelKeys());
});

it('revertir deja afiliación, afiliados, plan, renovación y cobranza como estaban', function (): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $change = frequencyChangeRecord();
    $createdIds = array_column($change->created_collections, 'id');

    $result = CorporatePaymentFrequencyChangeReverser::reverse($change, reverseReason(), ' tdec-cor-00001 ');

    $owner = frequencyOwner();
    $change->refresh();

    expect($result)->toBe(['restored' => 3, 'cancelled' => 9, 'notices' => 3])
        ->and($owner->payment_frequency)->toBe('TRIMESTRAL')
        ->and((float) $owner->total_amount)->toBe(300.0)
        ->and(DB::table('collections')->whereIn('id', [11, 12, 13])->pluck('status')->unique()->all())->toBe(['POR PAGAR'])
        ->and(DB::table('collections')->whereIn('id', $createdIds)->pluck('status')->unique()->all())->toBe(['CANCELADO'])
        ->and(DB::table('collections')->count())->toBe(13)
        ->and(DB::table('affiliate_corporates')->where('affiliation_corporate_id', 1)->pluck('subtotal_payment_frequency')->map(fn ($v) => (float) $v)->all())->toBe([175.0, 125.0])
        ->and(DB::table('affiliate_corporates')->where('affiliation_corporate_id', 1)->pluck('payment_frequency')->unique()->all())->toBe(['TRIMESTRAL'])
        ->and(DB::table('afilliation_corporate_plans')->value('payment_frequency'))->toBe('TRIMESTRAL')
        ->and(DB::table('renovation_corporates')->value('payment_frequency'))->toBe('TRIMESTRAL')
        ->and($change->status)->toBe('REVERSADO')
        ->and($change->reversed_by_name)->toBe('Analista Negocios')
        ->and($change->reversal_reason)->toBe(reverseReason());

    Bus::assertDispatched(NotifyAdministrationOfCorporatePaymentFrequencyChangeJob::class, fn ($job): bool => $job->event === 'reversed' && $job->changeIds === [$change->id]);
});

it('no revierte si algo cambió después, sin tocar nada', function (Closure $arrange, string $message): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $change = frequencyChangeRecord();
    $arrange($change);

    $before = DB::table('collections')->orderBy('id')->pluck('status', 'id')->all();

    expect(CorporatePaymentFrequencyChangeReverser::blockReason($change->fresh()))->toContain($message)
        ->and(fn () => CorporatePaymentFrequencyChangeReverser::reverse($change->fresh(), reverseReason(), 'TDEC-COR-00001'))
        ->toThrow(CorporatePaymentFrequencyChangeBlockedException::class, $message);

    expect(DB::table('collections')->orderBy('id')->pluck('status', 'id')->all())->toBe($before)
        ->and($change->fresh()->status)->toBe('APLICADO');
})->with([
    'un aviso nuevo ya se cobró' => [fn ($change) => DB::table('collections')->where('id', $change->created_collections[0]['id'])->update(['status' => 'PAGADO']), 'ya está en estado PAGADO'],
    'un aviso nuevo tiene abono' => [fn ($change) => DB::table('collections')->where('id', $change->created_collections[1]['id'])->update(['pay_amount_ves' => 10]), 'abono registrado'],
    'la tarifa anual cambió' => [fn () => DB::table('affiliation_corporates')->where('id', 1)->update(['fee_anual' => '1300']), 'tarifa anual'],
    'hay un comprobante por aprobar' => [fn () => DB::table('paid_membership_corporates')->insert(['affiliation_corporate_id' => 1, 'status' => 'PENDIENTE']), 'pendiente de aprobación'],
    'un aviso original ya no está cancelado' => [fn () => DB::table('collections')->where('id', 12)->update(['status' => 'PAGADO']), 'ya no está cancelado'],
    'la afiliación fue excluida' => [fn () => DB::table('affiliation_corporates')->where('id', 1)->update(['status' => 'EXCLUIDO']), 'excluido'],
]);

it('solo revierte el cambio más reciente y cada cambio una sola vez', function (): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $first = frequencyChangeRecord();
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'SEMESTRAL');
    $second = frequencyChangeRecord();

    expect(CorporatePaymentFrequencyChangeReverser::blockReason($first))->toContain('Revierta primero el más reciente');

    CorporatePaymentFrequencyChangeReverser::reverse($second, reverseReason(), 'TDEC-COR-00001');
    expect(frequencyOwner()->payment_frequency)->toBe('MENSUAL')
        ->and(CorporatePaymentFrequencyChangeReverser::blockReason($second->fresh()))->toContain('ya fue revertido');

    CorporatePaymentFrequencyChangeReverser::reverse($first->fresh(), reverseReason(), 'TDEC-COR-00001');

    expect(frequencyOwner()->payment_frequency)->toBe('TRIMESTRAL')
        ->and((float) frequencyOwner()->total_amount)->toBe(300.0)
        ->and(DB::table('collections')->where('status', 'POR PAGAR')->orderBy('id')->pluck('id')->all())->toBe([11, 12, 13]);
});

it('exige motivo suficiente y el código exacto de la afiliación', function (string $reason, string $code): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');

    expect(fn () => CorporatePaymentFrequencyChangeReverser::reverse(frequencyChangeRecord(), $reason, $code))
        ->toThrow(InvalidArgumentException::class);

    expect(frequencyChangeRecord()->status)->toBe('APLICADO');
})->with([
    'motivo corto' => ['error', 'TDEC-COR-00001'],
    'código equivocado' => [reverseReason(), 'TDEC-COR-00002'],
    'sin código' => [reverseReason(), ''],
]);

it('validar deja traza y no aplica a un cambio revertido', function (): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $change = frequencyChangeRecord();

    CorporatePaymentFrequencyChangeReverser::validate($change);

    expect($change->fresh()->validated_by_name)->toBe('Analista Negocios')
        ->and($change->fresh()->displayStatus())->toBe('Validado');

    CorporatePaymentFrequencyChangeReverser::reverse($change->fresh(), reverseReason(), 'TDEC-COR-00001');

    expect($change->fresh()->displayStatus())->toBe('Revertido')
        ->and(fn () => CorporatePaymentFrequencyChangeReverser::validate($change->fresh()))
        ->toThrow(CorporatePaymentFrequencyChangeBlockedException::class);
});

it('avisa por correo, WhatsApp y panel a Administración sin duplicar', function (): void {
    seedAdministrationRecipients();
    Mail::fake();
    NotificationFacade::fake();

    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $change = frequencyChangeRecord();
    $job = new NotifyAdministrationOfCorporatePaymentFrequencyChangeJob([$change->id], 'applied');

    $job->handle();
    $job->handle();

    Mail::assertSentCount(3);
    foreach (['ana@tudrencasa.com', 'cobranza@tudrencasa.com', 'administracion@tudrencasa.com'] as $email) {
        Mail::assertSent(CorporatePaymentFrequencyChangeMail::class, fn (CorporatePaymentFrequencyChangeMail $mail): bool => $mail->recipientEmail === $email);
    }
    Mail::assertNotSent(CorporatePaymentFrequencyChangeMail::class, fn ($mail): bool => in_array($mail->recipientEmail, ['ex@tudrencasa.com', 'negocios@tudrencasa.com'], true));

    Bus::assertDispatchedTimes(SendNotificacionWhatsApp::class, 2);
    Bus::assertDispatched(SendNotificacionWhatsApp::class, fn (SendNotificacionWhatsApp $wa): bool => $wa->phone === '+584141112233'
        && str_contains($wa->body, 'Analista Negocios')
        && str_contains($wa->body, '*Mensual*')
        && str_contains($wa->body, 'Revise su correo'));

    NotificationFacade::assertSentTo(User::query()->find(10), DatabaseNotification::class);
    NotificationFacade::assertSentTo(User::query()->find(11), DatabaseNotification::class);
    NotificationFacade::assertNotSentTo(User::query()->find(12), DatabaseNotification::class);
    NotificationFacade::assertNotSentTo(User::query()->find(13), DatabaseNotification::class);
    NotificationFacade::assertSentTimes(DatabaseNotification::class, 2);

    $log = $change->fresh()->notification_log['applied'];

    expect($log['emails_sent'])->toBe(3)
        ->and($log['whatsapps_queued'])->toBe(2)
        ->and($log['database_notified'])->toBe(2);
});

it('con el aviso pausado no envía correo ni WhatsApp pero sí la notificación del panel', function (): void {
    seedAdministrationRecipients();
    DB::table('system_notification_recipient_settings')->update(['is_active' => false]);
    Mail::fake();
    NotificationFacade::fake();

    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    (new NotifyAdministrationOfCorporatePaymentFrequencyChangeJob([frequencyChangeRecord()->id], 'applied'))->handle();

    Mail::assertNothingSent();
    Bus::assertNotDispatched(SendNotificacionWhatsApp::class);
    NotificationFacade::assertSentTimes(DatabaseNotification::class, 2);
    expect(frequencyChangeRecord()->notification_log['applied']['channels_active'])->toBeFalse();
});

it('el reverso también avisa al analista que hizo el cambio', function (): void {
    seedAdministrationRecipients();
    Mail::fake();
    NotificationFacade::fake();
    Auth::setUser(User::query()->find(13));

    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $change = frequencyChangeRecord();
    Auth::setUser(User::query()->find(10));
    CorporatePaymentFrequencyChangeReverser::reverse($change, reverseReason(), 'TDEC-COR-00001');

    (new NotifyAdministrationOfCorporatePaymentFrequencyChangeJob([$change->id], 'reversed'))->handle();

    Mail::assertSent(CorporatePaymentFrequencyChangeMail::class, fn ($mail): bool => $mail->recipientEmail === 'negocios@tudrencasa.com'
        && str_contains($mail->subjectLine, 'REVERTIDO'));
    NotificationFacade::assertSentTo(User::query()->find(13), DatabaseNotification::class);
    Bus::assertDispatched(SendNotificacionWhatsApp::class, fn ($wa): bool => str_contains($wa->body, 'REVERTIDO') && str_contains($wa->body, 'Motivo: '.reverseReason()));
});

it('el correo lista los avisos cancelados y los nuevos con sus montos', function (): void {
    CorporatePaymentFrequencyChanger::change(frequencyOwner(), 'MENSUAL');
    $changes = AffiliationCorporatePaymentFrequencyChange::query()->get();

    $html = (new CorporatePaymentFrequencyChangeMail(
        CorporatePaymentFrequencyChangeNotificationMessage::emailPayload($changes, 'applied'),
        'ana@tudrencasa.com',
        'Asunto',
    ))->render();

    expect($html)
        ->toContain('TDEC-COR-00001 · EMPRESA TRIMESTRAL')
        ->toContain('Trimestral → <strong>Mensual</strong>')
        ->toContain('Avisos de cobro cancelados (3)')
        ->toContain('09-00101')
        ->toContain('10-01-2027')
        ->toContain('Avisos de cobro nuevos (9)')
        ->toContain('100,00 US$')
        ->toContain('Analista Negocios');
});

it('lee el departamento aunque esté guardado como texto plano', function (): void {
    seedAdministrationRecipients();

    expect(CorporatePaymentFrequencyChangeRecipients::departmentsOf(User::query()->find(11)))->toBe(['ADMINISTRACION'])
        ->and(CorporatePaymentFrequencyChangeRecipients::departmentsOf(User::query()->find(10)))->toBe(['ADMINISTRACION', 'NEGOCIOS'])
        ->and(CorporatePaymentFrequencyChangeRecipients::administrationUsers()->modelKeys())->toBe([10, 11]);
});
