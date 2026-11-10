<?php

declare(strict_types=1);

use App\Models\IndividualQuote;
use App\Models\QuoteDeletionLog;
use App\Support\Metrics\Corporate\CorporateQuoteMetrics;
use App\Support\Operations\Dashboard\OperationsDashboardPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Datos aislados en 2031 (nadie cotiza ahí), dentro de una transacción revertida.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config(['cache.default' => 'array']);
});

afterEach(function (): void {
    DB::rollBack();
});

/**
 * @param  list<int>  $detailPlans
 */
function seedQuote(string $code, ?string $plan, string $status, string $createdAt, array $detailPlans = []): int
{
    $id = DB::table('individual_quotes')->insertGetId([
        'code' => $code,
        'plan' => $plan,
        'status' => $status,
        'created_by' => 'test',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);

    foreach ($detailPlans as $planId) {
        DB::table('detail_individual_quotes')->insert([
            'individual_quote_id' => $id,
            'plan_id' => $planId,
            'age_range_id' => 1,
            'total_persons' => 1,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }

    return $id;
}

function seedAffiliation(int $quoteId, int $planId, bool $deleted = false): void
{
    DB::table('affiliations')->insert([
        'code' => 'TEST-AFF-'.uniqid(),
        'code_agency' => 'TDG-100',
        'status' => 'ACTIVA',
        'individual_quote_id' => $quoteId,
        'plan_id' => $planId,
        'deleted_at' => $deleted ? '2031-03-20 10:00:00' : null,
        'created_at' => '2031-03-20 10:00:00',
        'updated_at' => '2031-03-20 10:00:00',
    ]);
}

function seedMarch2031(): void
{
    $march = '2031-03-10 09:00:00';

    seedQuote('T31-ANULADA', '1', 'ANULADA', $march, [1]);
    $issued = seedQuote('T31-EMITIDA', '1', 'EJECUTADA', $march, [1]);
    seedAffiliation($issued, 1);
    $otherPlan = seedQuote('T31-AFIL-OTRO-PLAN', '1', 'EJECUTADA', $march, [1]);
    seedAffiliation($otherPlan, 2);
    $multiple = seedQuote('T31-CM-1-3', 'CM', 'APROBADA', $march, [1, 3]);
    seedAffiliation($multiple, 3);
    seedQuote('T31-PLAN2-DETALLE3', '2', 'PRE-APROBADA', $march, [3]);
    seedQuote('T31-CM-SIN-DETALLE', 'CM', 'PRE-APROBADA', $march);
    $deletedAffiliation = seedQuote('T31-AFIL-ELIMINADA', '1', 'EJECUTADA', $march, [1]);
    seedAffiliation($deletedAffiliation, 1, deleted: true);
    seedQuote('T31-FEB-ESPECIAL', '3', 'PRE-APROBADA', '2031-02-14 09:00:00', [3]);

    $toDelete = seedQuote('T31-BORRADA', '1', 'PRE-APROBADA', $march, [1]);
    IndividualQuote::query()->findOrFail($toDelete)->delete();

    DB::table('corporate_quotes')->insert([
        'code' => 'T31-CORP',
        'plan' => '1',
        'status' => 'PRE-APROBADA',
        'created_by' => 'test',
        'created_at' => $march,
        'updated_at' => $march,
    ]);
}

function march2031(): array
{
    return [CarbonImmutable::parse('2031-03-01'), CarbonImmutable::parse('2031-03-31')];
}

it('cuenta creadas, anuladas o eliminadas y emitidas del Plan Inicial con las reglas acordadas', function (): void {
    seedMarch2031();
    [$from, $to] = march2031();

    $plan = CorporateQuoteMetrics::planBetween(1, $from, $to);

    expect($plan)->toMatchArray([
        'created' => 6,
        'issued' => 1,
        'anulated' => 1,
        'deleted' => 1,
        'voided' => 2,
        'open' => 3,
        'approved' => 3,
        'pre_approved' => 0,
    ])
        ->and($plan['issued'] + $plan['anulated'] + $plan['deleted'] + $plan['open'])->toBe($plan['created']);
});

it('la cotización de plan único manda sobre su detalle y la múltiple cuenta en cada plan que cotiza', function (): void {
    seedMarch2031();
    [$from, $to] = march2031();

    expect(CorporateQuoteMetrics::planBetween(2, $from, $to))
        ->toMatchArray(['created' => 1, 'issued' => 0])
        ->and(CorporateQuoteMetrics::planBetween(3, $from, $to))
        ->toMatchArray(['created' => 1, 'issued' => 1]);
});

it('una afiliación de otro plan o eliminada no cuenta como emitida', function (): void {
    seedMarch2031();
    [$from, $to] = march2031();

    $plan1 = CorporateQuoteMetrics::planBetween(1, $from, $to);
    $plan2 = CorporateQuoteMetrics::planBetween(2, $from, $to);

    expect($plan1['issued'])->toBe(1)
        ->and($plan2['issued'])->toBe(0);
});

it('compara contra el período anterior e informa las cotizaciones sin plan atribuible', function (): void {
    seedMarch2031();
    $period = OperationsDashboardPeriod::fromFilters(['period' => OperationsDashboardPeriod::LAST_MONTH], CarbonImmutable::parse('2031-04-15'));

    $special = CorporateQuoteMetrics::plan($period, 3);

    expect($period->from->toDateString())->toBe('2031-03-01')
        ->and($special['current']['created'])->toBe(1)
        ->and($special['previous']['created'])->toBe(1)
        ->and(CorporateQuoteMetrics::unattributed($period))->toBe(['individual' => 1, 'corporate' => 0]);
});

it('suma al año las borradas registradas y compara contra el mismo tramo del año anterior', function (): void {
    seedMarch2031();

    $data = CorporateQuoteMetrics::yearToDate(CarbonImmutable::parse('2031-03-31'));

    expect($data['from'])->toBe('2031-01-01')
        ->and($data['previous_from'])->toBe('2030-01-01')
        ->and($data['previous_to'])->toBe('2030-03-31')
        ->and($data['current'])->toBe(['individual' => 9, 'corporate' => 1, 'total' => 10])
        ->and($data['previous']['total'])->toBe(0);
});

it('el mismo tramo del año anterior respeta el 29 de febrero', function (): void {
    $data = CorporateQuoteMetrics::yearToDate(CarbonImmutable::parse('2032-02-29'));

    expect($data['previous_to'])->toBe('2031-02-28');
});

it('al borrar una cotización queda su huella con plan, estado y fecha de creación', function (): void {
    $id = seedQuote('T31-HUELLA', 'CM', 'APROBADA', '2031-03-05 08:00:00', [2, 3]);

    IndividualQuote::query()->findOrFail($id)->delete();

    $log = QuoteDeletionLog::query()->where('quote_id', $id)->firstOrFail();

    expect($log->quote_type)->toBe(QuoteDeletionLog::INDIVIDUAL)
        ->and($log->code)->toBe('T31-HUELLA')
        ->and($log->plan)->toBe('CM')
        ->and($log->plan_ids)->toBe([2, 3])
        ->and($log->status)->toBe('APROBADA')
        ->and($log->quote_created_at->toDateTimeString())->toBe('2031-03-05 08:00:00')
        ->and(CorporateQuoteMetrics::deletedLogHasPlan($log, 3))->toBeTrue()
        ->and(CorporateQuoteMetrics::deletedLogHasPlan($log, 1))->toBeFalse();
});

it('guarda la fecha de anulación y la limpia si la cotización sale de ANULADA', function (): void {
    $id = seedQuote('T31-ANULAR', '1', 'PRE-APROBADA', '2031-03-05 08:00:00', [1]);
    $quote = IndividualQuote::query()->findOrFail($id);

    $quote->update(['status' => 'ANULADA']);
    expect($quote->refresh()->anulated_at)->not->toBeNull();

    $quote->update(['status' => 'APROBADA']);
    expect($quote->refresh()->anulated_at)->toBeNull();
});

it('las corporativas se cuentan por su plan, se emiten por los planes de la afiliación y nunca se anulan', function (): void {
    $march = '2031-03-12 10:00:00';
    $quote = DB::table('corporate_quotes')->insertGetId(['code' => 'T31-C-CM', 'plan' => 'CM', 'status' => 'APROBADA', 'created_by' => 'test', 'created_at' => $march, 'updated_at' => $march]);
    DB::table('detail_corporate_quotes')->insert([
        ['corporate_quote_id' => $quote, 'plan_id' => 1, 'total_persons' => 5, 'created_at' => $march, 'updated_at' => $march],
        ['corporate_quote_id' => $quote, 'plan_id' => 3, 'total_persons' => 5, 'created_at' => $march, 'updated_at' => $march],
    ]);
    DB::table('corporate_quotes')->insert(['code' => 'T31-C-P1', 'plan' => '1', 'status' => 'PRE-APROBADA', 'created_by' => 'test', 'created_at' => $march, 'updated_at' => $march]);
    DB::table('corporate_quotes')->insert(['code' => 'T31-C-DT', 'plan' => '27', 'status' => 'PRE-APROBADA', 'created_by' => 'test', 'created_at' => $march, 'updated_at' => $march]);

    $affiliation = DB::table('affiliation_corporates')->insertGetId([
        'code' => 'T31-AC', 'corporate_quote_id' => $quote, 'owner_code' => 'TDG-100', 'code_agency' => 'TDG-100',
        'name_corporate' => 'Empresa de prueba', 'rif' => 'J-0', 'email' => 'e@test.com', 'phone' => '0', 'address' => 'x',
        'city_id' => 1, 'country_id' => 1, 'region_id' => '1', 'full_name_contact' => 'x', 'nro_identificacion_contact' => '0',
        'email_contact' => 'c@test.com', 'phone_contact' => '0', 'fee_anual' => '0', 'payment_frequency' => 'ANUAL',
        'status' => 'ACTIVA', 'created_by' => 'test', 'created_at' => $march, 'updated_at' => $march,
    ]);
    DB::table('afilliation_corporate_plans')->insert([
        'affiliation_corporate_id' => $affiliation, 'code_affiliation' => 'T31-AC', 'plan_id' => 3, 'age_range_id' => 1,
        'fee' => 0, 'subtotal_anual' => 0, 'subtotal_biannual' => 0, 'subtotal_quarterly' => 0, 'total_persons' => 5,
        'status' => 'ACTIVO', 'created_at' => $march, 'updated_at' => $march,
    ]);

    [$from, $to] = march2031();
    $initial = CorporateQuoteMetrics::planBetween(1, $from, $to, CorporateQuoteMetrics::CORPORATE);
    $special = CorporateQuoteMetrics::planBetween(3, $from, $to, CorporateQuoteMetrics::CORPORATE);

    expect($initial)->toMatchArray(['created' => 2, 'issued' => 0, 'anulated' => 0, 'approved' => 1, 'pre_approved' => 1, 'stale' => 0])
        ->and($special)->toMatchArray(['created' => 1, 'issued' => 1, 'open' => 0]);

    $period = OperationsDashboardPeriod::fromFilters(['period' => OperationsDashboardPeriod::LAST_MONTH], CarbonImmutable::parse('2031-04-15'));

    expect(CorporateQuoteMetrics::unattributed($period)['corporate'])->toBe(1);
})->skip(fn (): bool => ! Illuminate\Support\Facades\Schema::hasColumn('affiliation_corporates', 'corporate_quote_id'), 'Sin columna de vínculo corporativo.');

it('marca como vencidas las pre-aprobadas individuales con más de 15 días', function (): void {
    $old = CarbonImmutable::today()->subDays(20)->setTime(9, 0)->toDateTimeString();
    $recent = CarbonImmutable::today()->subDays(2)->setTime(9, 0)->toDateTimeString();
    seedQuote('T31-VIEJA', '2', 'PRE-APROBADA', $old, [2]);
    seedQuote('T31-NUEVA', '2', 'PRE-APROBADA', $recent, [2]);

    $plan = CorporateQuoteMetrics::planBetween(2, CarbonImmutable::today()->subDays(25), CarbonImmutable::today());

    expect($plan['pre_approved'])->toBeGreaterThanOrEqual(2)
        ->and($plan['stale'])->toBeGreaterThanOrEqual(1)
        ->and($plan['stale'])->toBeLessThan($plan['pre_approved']);
});

it('la lectura del período marca la emisión casi nula, la pérdida alta y las aprobadas sin afiliar', function (): void {
    $march = '2031-03-08 09:00:00';

    foreach (range(1, 12) as $i) {
        seedQuote('T31-IDEAL-ANUL-'.$i, '2', 'ANULADA', $march, [2]);
    }

    seedQuote('T31-IDEAL-APROB', '2', 'APROBADA', $march, [2]);

    $period = OperationsDashboardPeriod::fromFilters(['period' => OperationsDashboardPeriod::LAST_MONTH], CarbonImmutable::parse('2031-04-15'));
    $analysis = App\Support\Metrics\Corporate\CorporateQuoteAnalysis::build($period);

    $ideal = collect($analysis['plans'])->firstWhere('id', 2);
    $titles = collect($analysis['insights'])->pluck('title')->implode(' | ');

    expect($ideal['types']['individual']['current']['created'])->toBe(13)
        ->and($ideal['types']['individual']['issued_rate'])->toBe(0.0)
        ->and($ideal['types']['individual']['voided_rate'])->toBe(92.3)
        ->and($titles)->toContain('Casi no se emite')
        ->and($titles)->toContain('Más de la mitad se pierde')
        ->and($titles)->toContain('Hay cotizaciones aprobadas sin afiliación')
        ->and(collect($analysis['insights'])->pluck('text')->implode(' | '))->toContain('Plan Ideal 92,3 % (12 de 13)')
        ->and(collect($analysis['insights'])->pluck('text')->implode(' | '))->toContain('Plan Ideal ind. 1')
        ->and($analysis['insights'][0]['tone'])->toBe('critical')
        ->and(array_sum(array_column($ideal['types']['individual']['segments'], 'value')))->toBe(13);
});

it('la página Comercial muestra el año y los tres planes', function (): void {
    $source = file_get_contents(app_path('Filament/Metrics/Pages/Comercial.php'));

    expect($source)
        ->toContain('QuotesYearKpiTiles::class')
        ->toContain('PlanQuotesAnalysis::class');
});
