<?php

declare(strict_types=1);

use App\Models\AffiliateCorporate;
use App\Models\AffiliateMovement;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\AgeRange;
use App\Models\Collection;
use App\Models\CollectionMovementLine;
use App\Models\User;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator as Base;
use App\Support\Affiliations\Movements\AffiliateMovements;
use App\Support\Affiliations\Movements\MovementBillingCalculator as Calc;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Mail::fake();
    Queue::fake();
    Illuminate\Support\Facades\Bus::fake([App\Jobs\SendNotificacionWhatsApp::class]);
});

afterEach(function (): void {
    CarbonImmutable::setTestNow();
    Illuminate\Support\Carbon::setTestNow();
    DB::rollBack();
});

/**
 * Trimestres del ejemplo: 01/01 → 01/04 (90 días), 01/04 → 01/07, 01/07 → 01/10, 01/10 → 01/01.
 *
 * @return array{0: list<array<string, mixed>>, 1: array<int, array<string, mixed>>}
 */
function amvQuarterExample(string $firstState = Base::STATE_PAID): array
{
    $periods = Base::periods(CarbonImmutable::parse('2027-01-01'), 3, 4);
    $installments = [];
    foreach ($periods as $period) {
        $installments[$period['index']] = [
            'period' => $period['index'], 'id' => 100 + $period['index'], 'invoice' => 'AV-'.$period['index'], 'due' => $period['start'],
            'amount_cents' => 50000, 'persons' => 5, 'state' => $period['index'] === 0 ? $firstState : Base::STATE_PENDING,
        ];
    }

    return [$periods, $installments];
}

it('ejemplo de Gustavo: 5 personas, trimestral, baja el 15/02 → el próximo aviso trae −cuota y −prorrateo de 45 días', function (): void {
    [$periods, $installments] = amvQuarterExample();
    $r = Calc::calculate(Calc::REMOVE, CarbonImmutable::parse('2027-02-15'), 40000, 4, $periods, $installments);

    expect($r['period_cents'])->toBe(10000)
        ->and($r['prorated_days'])->toBe(45)
        ->and($r['prorated_cents'])->toBe(5000)
        ->and($r['changes'][0]['invoice'])->toBe('AV-1')
        ->and($r['changes'][0]['after_cents'])->toBe(35000)
        ->and($r['changes'][0]['persons_after'])->toBe(4)
        ->and($r['changes'][1]['after_cents'])->toBe(40000)
        ->and($r['changes'][2]['after_cents'])->toBe(40000)
        ->and($r['charge_cents'])->toBe(0)
        ->and($r['pending_credit_cents'])->toBe(0);
});

it('alta el 15/02 → el próximo aviso trae +cuota y +prorrateo; si el aviso en curso no está pagado, el prorrateo va a ese', function (): void {
    [$periods, $installments] = amvQuarterExample();
    $paid = Calc::calculate(Calc::ADD, CarbonImmutable::parse('2027-02-15'), 40000, 4, $periods, $installments);
    expect($paid['changes'][0]['invoice'])->toBe('AV-1')
        ->and($paid['changes'][0]['after_cents'])->toBe(65000)
        ->and($paid['changes'][1]['after_cents'])->toBe(60000);

    [$periods, $pendingInstallments] = amvQuarterExample(Base::STATE_PENDING);
    $pending = Calc::calculate(Calc::ADD, CarbonImmutable::parse('2027-02-15'), 40000, 4, $periods, $pendingInstallments);
    expect($pending['changes'][0]['invoice'])->toBe('AV-0')
        ->and($pending['changes'][0]['after_cents'])->toBe(55000)
        ->and($pending['changes'][0]['persons_after'])->toBe(5)
        ->and($pending['changes'][1]['after_cents'])->toBe(60000);
});

it('sin aviso pendiente: el alta genera aviso aparte y la baja deja crédito; nunca un aviso negativo', function (): void {
    $periods = Base::periods(CarbonImmutable::parse('2027-01-01'), 12, 1);
    $annual = [0 => ['period' => 0, 'id' => 1, 'invoice' => 'AV-0', 'due' => $periods[0]['start'], 'amount_cents' => 100000, 'persons' => 2, 'state' => Base::STATE_PAID]];

    $add = Calc::calculate(Calc::ADD, CarbonImmutable::parse('2027-07-02'), 36500, 1, $periods, $annual);
    $remove = Calc::calculate(Calc::REMOVE, CarbonImmutable::parse('2027-07-02'), 36500, 1, $periods, $annual);

    expect($add['changes'])->toBe([])
        ->and($add['charge_cents'])->toBe($add['prorated_cents'])
        ->and($add['prorated_days'])->toBe(183)
        ->and($remove['pending_credit_cents'])->toBe($remove['prorated_cents']);

    [$periods, $installments] = amvQuarterExample();
    foreach ([1, 2, 3] as $index) {
        $installments[$index]['amount_cents'] = 1000;
    }
    $big = Calc::calculate(Calc::REMOVE, CarbonImmutable::parse('2027-01-01'), 400000, 4, $periods, $installments);
    expect(collect($big['changes'])->min('after_cents'))->toBeGreaterThanOrEqual(0)
        ->and($big['pending_credit_cents'])->toBeGreaterThan(0);
});

/**
 * Afiliación corporativa trimestral iniciada el 01/09/2026 (el primer trimestre lo cubrió el
 * pago inicial) con avisos POR PAGAR del 01/12, 01/03 y 01/06; 3 afiliados de US$ 400/año.
 *
 * @return array{owner: AffiliationCorporate, row: AfilliationCorporatePlan, affiliates: list<AffiliateCorporate>, collections: list<Collection>, actor: User}
 */
function amvCorporate(): array
{
    $sourceRow = AfilliationCorporatePlan::query()->firstOrFail();
    $owner = AffiliationCorporate::query()->findOrFail($sourceRow->affiliation_corporate_id)->replicate();
    $owner->forceFill(['code' => 'PEST-COR-'.Str::upper(Str::random(6)), 'name_corporate' => 'EMPRESA MOVIMIENTOS PEST', 'status' => 'ACTIVA', 'payment_frequency' => 'TRIMESTRAL',
        'poblation' => 3, 'fee_anual' => 1200, 'total_amount' => 300, 'effective_date' => '01/09/2026', 'activated_at' => '2026-09-01 10:00:00'])->save();

    $range = new AgeRange;
    $range->forceFill(['plan_id' => 0, 'range' => '0 a 99', 'age_init' => 0, 'age_end' => 99, 'status' => 'ACTIVO', 'created_by' => 'pest'])->save();
    $row = $sourceRow->replicate();
    $row->forceFill(['affiliation_corporate_id' => $owner->id, 'age_range_id' => $range->id, 'fee' => 400, 'total_persons' => 3])->save();
    CorporateAffiliateRemovalServiceRow($row);

    $affiliates = [];
    foreach (['ANA', 'BETO', 'CARLA'] as $first) {
        $affiliate = new AffiliateCorporate;
        $affiliate->forceFill([
            'affiliation_corporate_id' => $owner->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => $first, 'last_name' => 'MOVIPEST ZARAGOZA',
            'nro_identificacion' => (string) random_int(80000000, 89999999), 'age' => 40, 'condition_medical' => 'SANO', 'plan_id' => $row->plan_id, 'coverage_id' => $row->coverage_id,
            'fee' => 400, 'subtotal_anual' => 400, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO', 'created_by' => 1,
        ])->save();
        $affiliates[] = $affiliate;
    }

    $template = Collection::query()->whereNull('billing_adjustment_id')->firstOrFail();
    $collections = [];
    foreach (['01/12/2026', '01/03/2027', '01/06/2027'] as $index => $due) {
        $collection = $template->replicate(['sale_id', 'pay_amount_usd', 'pay_amount_ves', 'bank_usd', 'bank_ves', 'reference']);
        $collection->forceFill(['affiliation_code' => $owner->code, 'collection_invoice_number' => 'PEST-AV-'.Str::upper(Str::random(5)).$index, 'include_date' => '01/09/2026', 'next_payment_date' => $due,
            'filter_next_payment_date' => null, 'total_amount' => '300.00', 'persons' => '3', 'status' => 'POR PAGAR', 'payment_frequency' => 'TRIMESTRAL', 'service' => 'PLAN', 'type' => 'AFILIACION CORPORATIVA', 'billing_adjustment_id' => null])->save();
        $collections[] = $collection;
    }

    return ['owner' => $owner, 'row' => $row, 'affiliates' => $affiliates, 'collections' => $collections, 'actor' => User::query()->firstOrFail()];
}

function CorporateAffiliateRemovalServiceRow(AfilliationCorporatePlan $row): void
{
    App\Services\CorporateAffiliateRemovalService::recalculateCorporatePlanRowTotals($row);
    $row->save();
}

it('baja real: afiliado inactivo, fila y totales recalculados, avisos ajustados con desglose, historial y reverso exacto', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    $f = amvCorporate();
    [$dec, $mar, $jun] = $f['collections'];

    $preview = AffiliateMovements::preview($f['owner'], $f['affiliates'][0], AffiliateMovement::KIND_REMOVE, '2026-11-11');
    expect($preview['blockers'])->toBe([]);

    $movement = AffiliateMovements::exclude($f['owner'], $f['affiliates'][0], '2026-11-11', 'La empresa notificó el retiro del colaborador.', $f['actor'], 'test');

    // Período 01/09 → 01/12 = 91 días; quedan 20 (11/11 → 01/12). Cuota trimestral US$ 100.
    $prorated = Base::prorateCents(10000, 20, 91);
    expect($f['affiliates'][0]->fresh()->status)->toBe('INACTIVO')
        ->and((int) $f['owner']->fresh()->poblation)->toBe(2)
        ->and((float) $f['owner']->fresh()->fee_anual)->toBe(800.0)
        ->and((int) $f['row']->fresh()->total_persons)->toBe(2)
        ->and((float) $f['row']->fresh()->subtotal_anual)->toBe(800.0)
        ->and(AffiliateMovements::cents($dec->fresh()->total_amount))->toBe(30000 - 10000 - $prorated)
        ->and((string) $dec->fresh()->persons)->toBe('2')
        ->and((string) $mar->fresh()->total_amount)->toBe('200.00')
        ->and((string) $jun->fresh()->total_amount)->toBe('200.00')
        ->and($movement->prorated_days)->toBe(20)
        ->and(CollectionMovementLine::query()->where('collection_id', $dec->id)->pluck('kind')->sort()->values()->all())->toBe(['CUOTA', 'PRORRATEO'])
        ->and(DB::table('collection_adjustments')->where('batch_uuid', $movement->public_id)->count())->toBe(3);

    AffiliateMovements::revert($movement, 'Se registró la baja en la empresa equivocada.', $f['actor']);

    expect($f['affiliates'][0]->fresh()->status)->toBe('ACTIVO')
        ->and((int) $f['owner']->fresh()->poblation)->toBe(3)
        ->and((int) $f['row']->fresh()->total_persons)->toBe(3)
        ->and((string) $dec->fresh()->total_amount)->toBe('300.00')
        ->and((string) $mar->fresh()->total_amount)->toBe('300.00')
        ->and($movement->fresh()->status)->toBe(AffiliateMovement::STATUS_REVERTED)
        ->and(CollectionMovementLine::query()->where('affiliate_movement_id', $movement->id)->where('reverted', false)->count())->toBe(0);
});

it('alta real: suma la cuota y el prorrateo; si el aviso ya se pagó, el reverso lo acredita en el siguiente', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    $f = amvCorporate();
    [$dec, $mar] = $f['collections'];

    $new = new AffiliateCorporate;
    $new->forceFill(['affiliation_corporate_id' => $f['owner']->id, 'affiliation_type' => 'ESTANDARD', 'first_name' => 'DIEGO', 'last_name' => 'MOVIPEST ZARAGOZA', 'nro_identificacion' => (string) random_int(80000000, 89999999),
        'age' => 30, 'condition_medical' => 'SANO', 'plan_id' => $f['row']->plan_id, 'coverage_id' => $f['row']->coverage_id, 'fee' => 400, 'subtotal_anual' => 400, 'payment_frequency' => 'TRIMESTRAL', 'status' => 'ACTIVO', 'created_by' => 1])->save();

    $movement = AffiliateMovements::include($f['owner'], $new, '2026-11-11', 'Nuevo colaborador contratado por la empresa.', $f['actor'], 'test');
    $prorated = Base::prorateCents(10000, 20, 91);

    expect((int) $f['owner']->fresh()->poblation)->toBe(4)
        ->and(AffiliateMovements::cents($dec->fresh()->total_amount))->toBe(30000 + 10000 + $prorated)
        ->and((string) $mar->fresh()->total_amount)->toBe('400.00');

    $dec->forceFill(['status' => 'PAGADO'])->save();
    AffiliateMovements::revert($movement, 'El colaborador nunca firmó el contrato.', $f['actor']);

    expect($new->fresh()->trashed())->toBeTrue()
        ->and((int) $f['owner']->fresh()->poblation)->toBe(3)
        ->and((string) $dec->fresh()->total_amount)->toBe(AffiliateMovements::decimal(30000 + 10000 + $prorated))
        ->and(AffiliateMovements::cents($mar->fresh()->total_amount))->toBe(30000 - 10000 - $prorated);
});

it('bloquea fechas fuera de la ventana, motivos sin palabras y bajas de quien ya está inactivo, y solo revierte el último movimiento', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    $f = amvCorporate();

    expect(AffiliateMovements::preview($f['owner'], $f['affiliates'][0], AffiliateMovement::KIND_REMOVE, '2026-08-01')['blockers'][0])->toContain('La fecha efectiva debe estar entre')
        ->and(fn () => AffiliateMovements::exclude($f['owner'], $f['affiliates'][0], '2026-11-11', 'dvdsvdsvdsfvdfsv', $f['actor']))->toThrow(RuntimeException::class, 'tres palabras');

    $first = AffiliateMovements::exclude($f['owner'], $f['affiliates'][0], '2026-11-11', 'Retiro voluntario del colaborador.', $f['actor']);
    expect(fn () => AffiliateMovements::exclude($f['owner'], $f['affiliates'][0]->fresh(), '2026-11-11', 'Retiro voluntario del colaborador.', $f['actor']))->toThrow(RuntimeException::class, 'ya no está activo');

    AffiliateMovements::revert($first, 'Baja registrada por error en el sistema.', $f['actor']);
    $second = AffiliateMovements::exclude($f['owner'], $f['affiliates'][0]->fresh(), '2026-11-11', 'Retiro voluntario confirmado por la empresa.', $f['actor']);

    expect(AffiliateMovements::revertBlocker($first->fresh()))->toContain('ya fue revertido')
        ->and(AffiliateMovements::revertBlocker($second))->toBeNull();
});

it('el crédito pendiente de una baja sin avisos se descuenta en el próximo aviso que se emita', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    $f = amvCorporate();
    foreach ($f['collections'] as $collection) {
        $collection->forceFill(['status' => 'PAGADO'])->save();
    }

    $movement = AffiliateMovements::exclude($f['owner'], $f['affiliates'][0], '2026-11-11', 'La empresa notificó el retiro del colaborador.', $f['actor']);
    // Prorrateo de 20/91 días más los tres trimestres ya pagados por adelantado.
    $credit = AffiliateMovements::cents($movement->pending_credit);
    expect($credit)->toBe(Base::prorateCents(10000, 20, 91) + 30000);

    $renewal = $f['collections'][0]->replicate();
    $renewal->forceFill(['collection_invoice_number' => 'PEST-REN-'.Str::random(4), 'next_payment_date' => '01/09/2027', 'total_amount' => '200.00', 'status' => 'POR PAGAR'])->save();

    // El aviso queda en cero (nunca negativo) y lo que sobra sigue pendiente para el siguiente.
    expect((string) $renewal->fresh()->total_amount)->toBe('0.00')
        ->and(AffiliateMovements::cents($movement->fresh()->pending_credit))->toBe($credit - 20000)
        ->and(CollectionMovementLine::query()->where('collection_id', $renewal->id)->value('kind'))->toBe(CollectionMovementLine::KIND_CREDIT);
});

it('la tabla de afiliados corporativos de Negocios da de baja con fecha, motivo y vista previa, y el historial lo revierte', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = amvCorporate();
    $user = User::factory()->make(['id' => 987980, 'name' => 'Analista Movimientos Pest', 'email' => 'movimientos-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS'], 'is_business_admin' => 1]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesQuickRelationManager::class, ['ownerRecord' => $f['owner'], 'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class])
        ->mountTableAction('changet_status', $f['affiliates'][1]);

    expect((string) $component->get('mountedActions.0.data.movement_effective_date'))->toStartWith('2026-11-11');

    $component->set('mountedActions.0.data.movement_reason', 'corto')
        ->call('callMountedAction')
        ->assertHasErrors(['mountedActions.0.data.movement_reason'])
        ->set('mountedActions.0.data.movement_reason', 'La empresa notificó el retiro del colaborador.')
        ->call('callMountedAction')
        ->assertHasNoErrors()
        ->assertNotified('Exclusión registrada');

    $movement = AffiliateMovement::query()->where('affiliate_id', $f['affiliates'][1]->id)->firstOrFail();
    expect($f['affiliates'][1]->fresh()->status)->toBe('INACTIVO')
        ->and($movement->source)->toBe('business.corporate-affiliates');

    $history = Livewire\Livewire::actingAs($user)
        ->test(App\Livewire\Affiliations\AffiliateMovementsHistory::class, ['affiliationType' => AffiliateMovement::TYPE_CORPORATE, 'affiliationId' => $f['owner']->id, 'canRevert' => true])
        ->assertSee('BETO MOVIPEST ZARAGOZA')
        ->mountTableAction('revert', $movement)
        ->set('mountedActions.0.data.reason', 'Se dio de baja a la persona equivocada.')
        ->call('callMountedAction')
        ->assertNotified('Movimiento revertido');

    expect($f['affiliates'][1]->fresh()->status)->toBe('ACTIVO')
        ->and((string) $f['collections'][0]->fresh()->total_amount)->toBe('300.00');

    $detail = view('livewire.affiliations.affiliate-movement-detail', ['movement' => $movement->fresh()->load('collectionLines.collection')])->render();
    expect($detail)->toContain('Avisos de cobro (lo que verá el cliente)', 'Prorrateo por exclusión de BETO MOVIPEST ZARAGOZA', 'Revertido');
});

it('el alta en las tablas de Negocios y Administración muestra la sección de inclusión solo al crear', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $f = amvCorporate();
    $user = User::factory()->make(['id' => 987981, 'name' => 'Analista Movimientos Pest', 'email' => 'movimientos-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS'], 'is_business_admin' => 1]);

    $component = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\AffiliationCorporates\RelationManagers\CorporateAffiliatesQuickRelationManager::class, ['ownerRecord' => $f['owner'], 'pageClass' => App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class])
        ->mountTableAction('create');

    expect($component->get('mountedActions.0.data'))->toHaveKey('movement_effective_date');

    foreach ([
        'app/Filament/Business/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
        'app/Filament/Business/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
        'app/Filament/Administration/Resources/AffiliationCorporates/RelationManagers/CorporateAffiliatesRelationManager.php',
        'app/Filament/Administration/Resources/Affiliations/RelationManagers/AffiliatesRelationManager.php',
    ] as $file) {
        $source = (string) file_get_contents(base_path($file));
        expect($source)->toContain('AffiliateMovementActions::exclude(', 'AffiliateMovementActions::registerInclusion(', 'AffiliateMovementActions::includeFields(')
            ->and($source)->not->toContain('addAffiliate(')
            ->and($source)->not->toContain("'status' => 'INACTIVO',");
    }
});

it('el aviso de cobro que recibe el cliente muestra el ajuste por la exclusión y solo cuenta a los afiliados vigentes', function (): void {
    CarbonImmutable::setTestNow('2026-11-11 10:00:00');
    Illuminate\Support\Carbon::setTestNow('2026-11-11 10:00:00');
    $f = amvCorporate();
    AffiliateMovements::exclude($f['owner'], $f['affiliates'][0], '2026-11-11', 'La empresa notificó el retiro del colaborador.', $f['actor']);
    $dec = $f['collections'][0]->fresh();

    $lines = App\Support\Collections\CollectionMovementBreakdown::forInvoice($dec->collection_invoice_number);
    expect($lines)->toHaveCount(1)
        ->and($lines[0]['description'])->toContain('Prorrateo por exclusión de ANA MOVIPEST ZARAGOZA', '20 de 91 días')
        ->and($lines[0]['amount'])->toBe(-(Base::prorateCents(10000, 20, 91) / 100));

    $notice = new App\Support\Collections\CollectionNotice;
    $method = new ReflectionMethod($notice, 'documentData');
    $data = $method->invoke($notice, $dec, ['full_name' => 'EMPRESA', 'document' => 'J-1', 'address' => 'X', 'phone' => '1', 'email' => 'a@b.c'], CarbonImmutable::today(), null, null);
    $html = view('documents.aviso-de-cobro-corporativo', ['data' => $data])->render();

    expect($data['affiliates_count'])->toBe(2)
        ->and($html)->toContain('AJUSTE: PRORRATEO POR EXCLUSIÓN DE ANA MOVIPEST ZARAGOZA')
        ->and($html)->toContain('Monto Total: US$'.number_format((float) $dec->total_amount, 2));
});

it('guardar la edición de la afiliación no pisa los totales que cambió un alta mientras la página estaba abierta (caso 00064)', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $owner = AffiliationCorporate::query()->findOrFail(64);
    $user = User::factory()->make(['id' => 987982, 'name' => 'Analista Edicion Pest', 'email' => 'edicion-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);

    $page = Livewire\Livewire::actingAs($user)->test(App\Filament\Business\Resources\AffiliationCorporates\Pages\EditAffiliationCorporate::class, ['record' => $owner->id]);

    // Mientras la página está abierta, un alta en la tabla de afiliados recalcula los totales.
    DB::table('affiliation_corporates')->where('id', $owner->id)->update(['fee_anual' => 99999, 'total_amount' => 24999.75]);

    $page->call('save');

    expect((float) $owner->fresh()->fee_anual)->toBe(99999.0)
        ->and((float) $owner->fresh()->total_amount)->toBe(24999.75);
});
