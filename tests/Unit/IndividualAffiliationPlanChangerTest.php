<?php

declare(strict_types=1);

use App\Filament\Shared\Affiliations\ChangeAffiliationPlanBulkAction;
use App\Models\Affiliation;
use App\Models\AffiliationPlanChange;
use App\Models\AnnualCollection;
use App\Models\Collection as BillingCollection;
use App\Models\CollectionObservation;
use App\Models\PaidMembership;
use App\Models\Renovation;
use App\Models\User;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Affiliations\PlanChange\IndividualAffiliationPlanChanger;
use App\Support\Affiliations\PlanChange\IndividualPlanChangeCatalog;
use App\Support\Affiliations\PlanChange\IndividualPlanChangeRequest;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

/**
 * Escribe en afiliaciones, afiliados y cobranza: todo en una transacción que siempre se revierte.
 * Los documentos se regeneran tras el commit, que aquí nunca ocurre; la cola va simulada igual.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();
});

afterEach(fn () => DB::rollBack());

function planChangeActor(): User
{
    return User::factory()->make(['id' => null, 'name' => 'Analista Negocios', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);
}

/**
 * Afiliación ACTIVA sin bloqueos, con cuotas POR PAGAR de la frecuencia dada y sin empresa aliada.
 */
function changeableAffiliation(string $pendingFrequency = 'TRIMESTRAL'): Affiliation
{
    $affiliation = Affiliation::query()
        ->where('status', 'ACTIVA')
        ->whereNull('white_company_neta')
        ->where('payment_frequency', $pendingFrequency)
        ->whereIn('code', BillingCollection::query()->where('status', 'POR PAGAR')->where('payment_frequency', $pendingFrequency)->where(fn ($q) => $q->whereNull('pay_amount_usd')->orWhere('pay_amount_usd', 0))->select('affiliation_code'))
        ->whereNotIn('id', PaidMembership::query()->where('status', 'PENDIENTE')->select('affiliation_id'))
        ->whereNotIn('id', Renovation::query()->where('status', 'PERIODO DE RENOVACION')->select('affiliation_id'))
        ->latest('id')
        ->first();

    if ($affiliation === null) {
        test()->markTestSkipped('No hay una afiliación local que cumpla las condiciones.');
    }

    return $affiliation;
}

function specialPlanCoverage(): int
{
    return (int) array_key_first(IndividualPlanChangeCatalog::coverageOptions(AffiliationAffiliateFeeCalculator::SPECIAL_PLAN_ID));
}

describe('vista previa', function (): void {
    it('muestra el resultado real y no deja nada escrito', function (): void {
        $affiliation = changeableAffiliation();
        $fingerprint = fn (): string => md5(json_encode([
            $affiliation->fresh()->only(['plan_id', 'coverage_id', 'payment_frequency', 'fee_anual', 'total_amount']),
            $affiliation->affiliates()->orderBy('id')->get(['id', 'plan_id', 'coverage_id', 'age_range_id', 'fee', 'total_amount', 'payment_frequency'])->toArray(),
            BillingCollection::query()->where('affiliation_code', $affiliation->code)->orderBy('id')->get(['id', 'status', 'total_amount', 'plan_id', 'payment_frequency'])->toArray(),
            BillingCollection::query()->count(),
            CollectionObservation::query()->count(),
            AffiliationPlanChange::query()->count(),
        ]));
        $before = $fingerprint();

        $preview = app(IndividualAffiliationPlanChanger::class)->preview($affiliation, new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'));

        expect($preview['blocked'])->toBeNull()
            ->and($preview['new']['plan_id'])->toBe(3)
            ->and($preview['new']['frequency'])->toBe('MENSUAL')
            ->and($preview['billing']['mode'])->toBe('regenerated')
            ->and($preview['billing']['created'])->not->toBeEmpty()
            ->and(collect($preview['billing']['created'])->pluck('invoice')->unique()->all())->toBe(['nuevo'])
            ->and($fingerprint())->toBe($before);
    });
});

describe('cambio de plan', function (): void {
    it('cambia plan, cobertura y frecuencia, y reparte los mismos meses pendientes en cuotas nuevas', function (): void {
        $affiliation = changeableAffiliation('TRIMESTRAL');
        $coverage = specialPlanCoverage();
        $pending = BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'POR PAGAR')->get();
        $paidBefore = BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'PAGADO')->orderBy('id')->get()->toArray();
        $months = $pending->count() * 3;

        $result = app(IndividualAffiliationPlanChanger::class)->change($affiliation, new IndividualPlanChangeRequest(3, $coverage, 'MENSUAL', [], 'Prueba de cambio de plan'), planChangeActor());
        $affiliation->refresh();
        $created = BillingCollection::query()->whereKey(collect($result['billing']['created'])->pluck('id'))->get();

        expect($affiliation->plan_id)->toBe(3)
            ->and((int) $affiliation->coverage_id)->toBe($coverage)
            ->and($affiliation->payment_frequency)->toBe('MENSUAL')
            ->and((float) $affiliation->total_amount)->toBe(round((float) $affiliation->fee_anual / 12, 2))
            ->and($affiliation->affiliates()->where('status', 'ACTIVO')->pluck('plan_id')->unique()->all())->toBe([3])
            ->and($affiliation->affiliates()->where('status', 'ACTIVO')->pluck('payment_frequency')->unique()->all())->toBe(['MENSUAL'])
            ->and(BillingCollection::query()->whereKey($pending->modelKeys())->pluck('status')->unique()->all())->toBe(['CANCELADO'])
            ->and($created)->toHaveCount($months)
            ->and($created->pluck('status')->unique()->all())->toBe(['POR PAGAR'])
            ->and($created->pluck('plan_id')->unique()->all())->toBe([3])
            ->and(round($created->sum('total_amount'), 2))->toBe(round((float) $affiliation->fee_anual * $months / 12, 2))
            ->and($created->pluck('collection_invoice_number')->unique())->toHaveCount($months)
            ->and(BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'PAGADO')->orderBy('id')->get()->toArray())->toBe($paidBefore)
            ->and(CollectionObservation::query()->whereIn('collection_id', [...$pending->modelKeys(), ...$created->modelKeys()])->count())->toBe($pending->count() + $months);

        $change = AffiliationPlanChange::query()->findOrFail($result['change_id']);

        expect($change->new_plan_id)->toBe(3)
            ->and($change->previous_frequency)->toBe('TRIMESTRAL')
            ->and($change->snapshot['affiliation']['plan_id'])->toBe($result['previous']['plan_id'])
            ->and($change->cancelled_collections)->toHaveCount($pending->count())
            ->and($change->reason)->toBe('Prueba de cambio de plan')
            ->and($affiliation->status_log_affiliations()->where('action', 'CAMBIO DE PLAN')->exists())->toBeTrue();

        $annual = AnnualCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'POR PAGAR')->first();

        if ($annual !== null) {
            $flags = collect(range(1, 12))->filter(fn (int $m): bool => (bool) $annual->{'month_'.$m})->values()->all();

            expect($annual->plan_id)->toBe(3)
                ->and($flags)->toBe($created->map(fn ($c) => (int) explode('/', $c->next_payment_date)[1])->unique()->sort()->values()->all());
        }
    });

    it('con la misma frecuencia recalcula las cuotas en el lugar, con su mismo número de aviso', function (): void {
        $affiliation = changeableAffiliation('TRIMESTRAL');
        $pending = BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'POR PAGAR')->orderBy('id')->get();
        $total = BillingCollection::query()->count();

        $result = app(IndividualAffiliationPlanChanger::class)->change($affiliation, new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'TRIMESTRAL'), planChangeActor());
        $affiliation->refresh();

        expect($result['billing']['mode'])->toBe('updated')
            ->and(BillingCollection::query()->count())->toBe($total)
            ->and(BillingCollection::query()->whereKey($pending->modelKeys())->pluck('collection_invoice_number')->all())->toBe($pending->pluck('collection_invoice_number')->all())
            ->and(BillingCollection::query()->whereKey($pending->modelKeys())->pluck('total_amount')->map(fn ($v) => (float) $v)->unique()->all())->toBe([(float) $affiliation->total_amount]);
    });

    it('solo cambiar la frecuencia conserva la tarifa de cada afiliado', function (): void {
        $affiliation = changeableAffiliation('TRIMESTRAL');
        $fees = $affiliation->affiliates()->where('status', 'ACTIVO')->orderBy('id')->pluck('fee', 'id')->map(fn ($v) => (float) $v)->all();

        $summary = app(IndividualAffiliationPlanChanger::class)->changeFrequencyMany([$affiliation], 'SEMESTRAL', planChangeActor());

        expect($summary['blocked'])->toBe([])
            ->and($affiliation->affiliates()->where('status', 'ACTIVO')->orderBy('id')->pluck('fee', 'id')->map(fn ($v) => (float) $v)->all())->toBe($fees)
            ->and($affiliation->fresh()->payment_frequency)->toBe('SEMESTRAL')
            ->and($summary['changed'][0]['action'])->toBe('CAMBIO DE FRECUENCIA');
    });

    it('el rango de edad elegido a mano define la tarifa', function (): void {
        $affiliation = changeableAffiliation();
        $coverage = specialPlanCoverage();
        $affiliate = $affiliation->affiliates()->where('status', 'ACTIVO')->orderBy('id')->firstOrFail();
        $ranges = IndividualPlanChangeCatalog::ageRangeOptions(3, $coverage);
        $chosen = (int) array_key_last($ranges);
        $fee = IndividualPlanChangeCatalog::feeForAgeRange(3, $coverage, $chosen);

        $result = app(IndividualAffiliationPlanChanger::class)->change($affiliation, new IndividualPlanChangeRequest(3, $coverage, 'TRIMESTRAL', [(int) $affiliate->id => $chosen]), planChangeActor());
        $line = collect($result['affiliates'])->firstWhere('id', (int) $affiliate->id);

        expect($line['overridden'])->toBeTrue()
            ->and($line['new_age_range_id'])->toBe($chosen)
            ->and($line['new_fee'])->toBe(round((float) $fee->price, 2))
            ->and((float) $affiliate->fresh()->fee)->toBe(round((float) $fee->price, 2));
    });
});

describe('bloqueos', function (): void {
    it('bloquea con el motivo y no cambia nada', function (callable $pick, string $reason): void {
        $affiliation = $pick();

        if ($affiliation === null) {
            $this->markTestSkipped('No hay datos locales para este bloqueo.');
        }

        $before = $affiliation->fresh()->only(['plan_id', 'coverage_id', 'payment_frequency', 'fee_anual', 'total_amount']);
        $preview = app(IndividualAffiliationPlanChanger::class)->preview($affiliation, new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'));

        expect($preview['blocked'])->toContain($reason)
            ->and(fn () => app(IndividualAffiliationPlanChanger::class)->change($affiliation, new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'), planChangeActor()))
            ->toThrow(App\Exceptions\IndividualPlanChangeBlockedException::class)
            ->and($affiliation->fresh()->only(array_keys($before)))->toBe($before);
    })->with([
        'comprobante por aprobar' => [fn () => Affiliation::query()->where('status', 'ACTIVA')->whereIn('id', PaidMembership::query()->where('status', 'PENDIENTE')->select('affiliation_id'))->first(), 'por aprobar en Administración'],
        'período de renovación' => [fn () => Affiliation::query()->where('status', 'ACTIVA')->whereIn('id', Renovation::query()->where('status', 'PERIODO DE RENOVACION')->select('affiliation_id'))->whereNotIn('id', PaidMembership::query()->where('status', 'PENDIENTE')->select('affiliation_id'))->first(), 'período de renovación'],
        'afiliación excluida' => [fn () => Affiliation::query()->where('status', 'EXCLUIDO')->first(), 'solo se cambia el plan de afiliaciones activas'],
    ]);

    it('bloquea si una cuota pendiente tiene un abono a medias', function (): void {
        $affiliation = changeableAffiliation();
        BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'POR PAGAR')->limit(1)->update(['pay_amount_usd' => 10]);

        $preview = app(IndividualAffiliationPlanChanger::class)->preview($affiliation, new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'));

        expect($preview['blocked'])->toContain('tiene un abono registrado');
    });

    it('no acepta un plan solo corporativo, una cobertura ajena ni nada que cambiar', function (): void {
        $affiliation = changeableAffiliation();
        $changer = app(IndividualAffiliationPlanChanger::class);

        expect($changer->preview($affiliation, new IndividualPlanChangeRequest(11, null, 'MENSUAL'))['blocked'])->toContain('no está disponible para afiliaciones individuales')
            ->and($changer->preview($affiliation, new IndividualPlanChangeRequest(3, 999999, 'MENSUAL'))['blocked'])->toContain('Elija una cobertura')
            ->and($changer->preview($affiliation, new IndividualPlanChangeRequest((int) $affiliation->plan_id, (int) $affiliation->coverage_id > 0 ? (int) $affiliation->coverage_id : null, (string) $affiliation->payment_frequency))['blocked'])->toContain('no hay nada que cambiar');
    });

    it('las aliadas recalculan su tarifa negociada con el plan nuevo, o se bloquean si la matriz no lo tiene', function (): void {
        $affiliation = Affiliation::query()->where('status', 'ACTIVA')->whereNotNull('white_company_neta')
            ->whereIn('id', App\Models\Affiliate::query()->where('status', 'ACTIVO')->select('affiliation_id'))
            ->first();

        if ($affiliation === null) {
            $this->markTestSkipped('No hay afiliaciones de empresas aliadas con afiliados activos.');
        }

        // Dentro de la transacción del test (se revierte): se quitan sus otros bloqueos para llegar a la aliada.
        PaidMembership::query()->where('affiliation_id', $affiliation->id)->where('status', 'PENDIENTE')->update(['status' => 'APROBADO']);
        Renovation::query()->where('affiliation_id', $affiliation->id)->where('status', 'PERIODO DE RENOVACION')->update(['status' => 'VIGENTE']);
        BillingCollection::query()->where('affiliation_code', $affiliation->code)->where('status', 'POR PAGAR')->update(['pay_amount_usd' => null, 'pay_amount_ves' => null]);
        $before = $affiliation->fresh()->only(['white_company_sale_price', 'white_company_neta', 'white_company_fee_id']);

        foreach ([[(int) $affiliation->plan_id, (int) $affiliation->coverage_id > 0 ? (int) $affiliation->coverage_id : null, 'MENSUAL'], [3, specialPlanCoverage(), 'MENSUAL']] as [$plan, $coverage, $frequency]) {
            $preview = app(IndividualAffiliationPlanChanger::class)->preview($affiliation, new IndividualPlanChangeRequest($plan, $coverage, $frequency));

            if ($preview['blocked'] !== null) {
                expect($preview['blocked'])->toContain('empresa aliada');
            } else {
                expect($preview['white_company'])->toHaveKeys(['company', 'sale_price', 'neta'])
                    ->and($preview['white_company']['neta'])->toBeGreaterThan(0);
            }
        }

        // La vista previa no deja la tarifa negociada vacía ni cambiada.
        expect($affiliation->fresh()->only(array_keys($before)))->toBe($before);
    });

    it('en un lote, una afiliación bloqueada no impide cambiar las demás', function (): void {
        $good = changeableAffiliation();
        $excluded = Affiliation::query()->where('status', 'EXCLUIDO')->firstOrFail();

        $summary = app(IndividualAffiliationPlanChanger::class)->changeMany(
            [$good, $excluded],
            fn () => new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'),
            planChangeActor(),
        );

        expect($summary['changed'])->toHaveCount(1)
            ->and($summary['blocked'])->toHaveCount(1)
            ->and($summary['blocked'][0]['label'])->toContain((string) $excluded->code)
            ->and($good->fresh()->plan_id)->toBe(3);
    });
});

describe('catálogo, permisos y tabla', function (): void {
    it('ofrece solo planes activos con tarifas y no los exclusivos de corporativos', function (): void {
        $plans = IndividualPlanChangeCatalog::planOptions();

        expect($plans)->toHaveKeys([1, 2, 3])
            ->and($plans)->not->toHaveKey(11)
            ->and(IndividualPlanChangeCatalog::coverageOptions(1))->toBe([])
            ->and(IndividualPlanChangeCatalog::ageRangeOptions(1, null))->not->toBeEmpty();
    });

    it('la calculadora deja sin cobertura cualquier plan de paquete, no solo el 1', function (): void {
        $amounts = (new AffiliationAffiliateFeeCalculator)->calculateAmountsForPlanCoverageAndAge(26, 99, 40, 'ANUAL');

        expect($amounts)->not->toBeNull()->and($amounts['coverage_id'])->toBeNull();
    });

    it('el permiso es asignable y solo lo tienen SUPERADMIN o quien lo reciba', function (): void {
        expect(BusinessFilamentActionPermissionRegistry::all())->toHaveKey('cambiar-plan-afiliacion-individual');

        $this->actingAs(User::factory()->make(['id' => 999999951, 'departament' => ['SUPERADMIN']]));
        expect(ChangeAffiliationPlanBulkAction::userCan())->toBeTrue();

        $this->actingAs(User::factory()->make(['id' => 999999952, 'departament' => ['NEGOCIOS']]));
        expect(ChangeAffiliationPlanBulkAction::userCan())->toBeFalse();
    });

    it('la tabla de Negocios ofrece «Cambiar plan» y las acciones de frecuencia usan el mismo servicio', function (): void {
        $table = file_get_contents(app_path('Filament/Business/Resources/Affiliations/Tables/AffiliationsTable.php'));

        expect($table)->toContain('ChangeAffiliationPlanBulkAction::make()')
            ->and(substr_count($table, '->changeFrequencyMany('))->toBe(2)
            ->and($table)->not->toContain('$record->total_amount = $record->fee_anual / 12');
    });

    it('solo envía los rangos que el analista cambió', function (): void {
        $overrides = ChangeAffiliationPlanBulkAction::overridesFrom([
            ['affiliation_id' => 1, 'affiliate_id' => 10, 'age_range_id' => 5, 'suggested_age_range_id' => 5],
            ['affiliation_id' => 1, 'affiliate_id' => 11, 'age_range_id' => 7, 'suggested_age_range_id' => 6],
        ]);

        expect($overrides)->toBe([1 => [11 => 7]]);
    });

    it('la vista previa se dibuja con bloqueadas y cambiadas', function (): void {
        $affiliation = changeableAffiliation();
        $previews = ChangeAffiliationPlanBulkAction::previews([(int) $affiliation->id, (int) Affiliation::query()->where('status', 'EXCLUIDO')->value('id')], fn () => new IndividualPlanChangeRequest(3, specialPlanCoverage(), 'MENSUAL'));
        $html = view('filament.business.affiliations.plan-change-preview', ['previews' => $previews])->render();

        expect($html)->toContain('Bloqueada')
            ->toContain('Cambio de plan')
            ->toContain('PLAN ESPECIAL')
            ->toContain('se anulan');
    });
});

it('la acción masiva de la tabla de Negocios cambia el plan de punta a punta', function (): void {
    $affiliation = changeableAffiliation();
    Filament\Facades\Filament::setCurrentPanel('business');
    $user = User::factory()->make(['id' => null, 'name' => 'Analista Negocios', 'email' => 'negocios.prueba@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS'], 'is_business_admin' => 1]);
    $this->actingAs($user);

    Livewire\Livewire::test(App\Filament\Business\Resources\Affiliations\Pages\ListAffiliations::class)
        ->call('loadTable')
        ->selectTableRecords([$affiliation->id])
        ->mountAction(Filament\Actions\Testing\TestAction::make('change_plan')->table()->bulk())
        ->assertSet('mountedActions.0.data.affiliation_ids', [(int) $affiliation->id])
        ->set('mountedActions.0.data.plan_id', 3)
        ->set('mountedActions.0.data.coverage_id', specialPlanCoverage())
        ->set('mountedActions.0.data.frequency', 'MENSUAL')
        ->set('mountedActions.0.data.reason', 'Cambio solicitado por el cliente')
        ->set('mountedActions.0.data.confirmed', true)
        ->callMountedAction()
        ->assertHasNoErrors()
        ->assertNotified('Plan cambiado en 1 afiliación');

    expect($affiliation->fresh()->plan_id)->toBe(3)
        ->and($affiliation->fresh()->payment_frequency)->toBe('MENSUAL')
        ->and(AffiliationPlanChange::query()->where('affiliation_id', $affiliation->id)->value('reason'))->toBe('Cambio solicitado por el cliente');
});
