<?php

declare(strict_types=1);

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AfilliationCorporatePlan;
use App\Models\Collection;
use App\Models\CorporateQuote;
use App\Models\DetailCorporateQuote;
use App\Models\DetailIndividualQuote;
use App\Models\IndividualQuote;
use App\Models\QuoteValueAdjustment;
use App\Models\User;
use App\Support\AffiliationCorporates\ActivePopulationTotals;
use App\Support\Quotes\Adjustments\QuoteValueAdjustments as Adjust;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();
});

afterEach(function (): void {
    DB::rollBack();
});

function qvaActor(): User
{
    return User::factory()->make(['id' => 987990, 'name' => 'Analista Ajustes Pest', 'email' => 'ajustes-pest@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'NEGOCIOS']]);
}

/**
 * @return array<int, int> centavos de los avisos POR PAGAR, por id
 */
function qvaPending(string $code): array
{
    return Collection::query()->where('affiliation_code', $code)->where('status', 'POR PAGAR')->orderBy('id')->pluck('total_amount', 'id')->map(fn ($amount): int => Adjust::cents($amount))->all();
}

/**
 * @return array<string, mixed>
 */
function qvaChange(int $detailId, string $fee, int $persons): array
{
    $model = DetailCorporateQuote::query()->find($detailId) ?? DetailIndividualQuote::query()->findOrFail($detailId);

    return ['detail_id' => $detailId, 'fee' => $fee, 'persons' => $persons, 'fee_before' => (string) $model->fee, 'persons_before' => (int) $model->total_persons];
}

/**
 * Cuota viva esperada de cada aviso POR PAGAR del año: cuota regular + lo que lleva aparte.
 *
 * @return array<int, int>
 */
function qvaExpectedPending(Illuminate\Database\Eloquent\Model $owner): array
{
    $plan = App\Support\Affiliations\ActiveBilling\ActivePopulationBilling::plan($owner->fresh());

    return collect($plan['collections'])->mapWithKeys(fn (array $c): array => [$c['id'] => $c['after_cents']])->all();
}

it('corporativa: la tarifa pasa a la fila y a sus afiliados (también a quien no tenía), la cantidad cambia solo la cotización, y todo cuadra con la población activa; el reverso deja cada tarifa como estaba', function (): void {
    $owner = AffiliationCorporate::query()->findOrFail(56);
    $quote = CorporateQuote::query()->findOrFail(259);
    $row = AfilliationCorporatePlan::query()->findOrFail(93);

    // Con un comprobante pendiente de aprobación no se toca la cobranza.
    expect(Adjust::preview($quote, [qvaChange(3742, '400', 28)])['blockers'][0])->toContain('comprobante de pago pendiente');
    App\Models\PaidMembershipCorporate::query()->where('affiliation_corporate_id', 56)->where('status', 'PENDIENTE')->update(['status' => 'APROBADO']);

    // Un afiliado de la fila sin tarifa: debe recibir la nueva, no una resta.
    $withoutFee = AffiliateCorporate::query()->where('affiliation_corporate_id', 56)->where('plan_id', 3)->where('coverage_id', 12)->where('status', 'ACTIVO')->orderBy('id')->firstOrFail();
    AffiliateCorporate::query()->whereKey($withoutFee->id)->update(['fee' => null]);
    $affiliateFees = AffiliateCorporate::query()->where('affiliation_corporate_id', 56)->orderBy('id')->pluck('fee', 'id')->all();

    $preview = Adjust::preview($quote, [qvaChange(3742, '400', 28)]);
    expect($preview['blockers'])->toBe([])
        ->and(DetailCorporateQuote::query()->find(3742)->fee)->toBe('377.00');

    $adjustment = Adjust::apply($quote, [qvaChange(3742, '400', 28)], 'El cliente negoció una tarifa nueva para su cobertura.', qvaActor());
    $effects = $adjustment->effects['affiliation'];
    $ids = $effects['affiliate_groups'][0]['ids'];
    $live = App\Support\Affiliations\ActiveBilling\ActivePopulationBilling::live($owner->fresh());

    expect($preview['affiliation']['totals']['after'])->toBe($effects['totals']['after'])
        ->and($row->fresh()->fee)->toBe('400.00')
        ->and((int) $row->fresh()->total_persons)->toBe(25)
        ->and($row->fresh()->fee_source)->toBe('NEGOCIADA')
        ->and(AffiliateCorporate::query()->find($withoutFee->id)->fee)->toBe('400.00')
        ->and(AffiliateCorporate::query()->whereIn('id', $ids)->where('fee', '<', 0)->count())->toBe(0)
        ->and(implode(' ', $effects['notes']))->toContain('cambia solo en la cotización')
        ->and((int) $owner->fresh()->poblation)->toBe($live['persons'])
        ->and(Adjust::cents($owner->fresh()->fee_anual))->toBe(Adjust::cents($live['fee_anual']))
        ->and(Adjust::cents($owner->fresh()->total_amount))->toBe(Adjust::cents($live['total_amount']))
        ->and(qvaExpectedPending($owner))->toBe([]);

    $other = collect($ids)->first(fn (int $id): bool => $id !== $withoutFee->id && $affiliateFees[$id] !== null);
    expect(Adjust::cents(AffiliateCorporate::query()->find($other)->fee))->toBe(Adjust::cents($affiliateFees[$other]) + 2300);

    // La tabla muestra solo la población activa.
    $listed = ActivePopulationTotals::applyToQuery(AffiliationCorporate::query()->whereKey(56))->firstOrFail();
    expect(ActivePopulationTotals::forRecord($listed)['persons'])->toBe($live['persons'])
        ->and(ActivePopulationTotals::forRecord($listed)['differs'])->toBeFalse();

    Adjust::revert($adjustment, 'La gerencia no aprobó la tarifa negociada.', qvaActor());

    expect(DetailCorporateQuote::query()->find(3742)->only(['fee', 'total_persons']))->toBe(['fee' => '377.00', 'total_persons' => 26])
        ->and($row->fresh()->only(['fee', 'total_persons']))->toBe(['fee' => '377.00', 'total_persons' => 25])
        ->and(AffiliateCorporate::query()->where('affiliation_corporate_id', 56)->orderBy('id')->pluck('fee', 'id')->all())->toBe($affiliateFees)
        ->and(qvaExpectedPending($owner))->toBe([])
        ->and($adjustment->fresh()->status)->toBe(QuoteValueAdjustment::STATUS_REVERTED);
});

it('corporativa: una línea que no está contratada en la afiliación solo cambia la cotización', function (): void {
    $owner = AffiliationCorporate::query()->findOrFail(56);
    $before = $owner->only(['fee_anual', 'total_amount', 'poblation']);
    $pending = qvaPending($owner->code);

    $adjustment = Adjust::apply(CorporateQuote::query()->findOrFail(259), [qvaChange(3735, '300', 5)], 'Corrección de la opción no elegida por el cliente.', qvaActor());

    expect(DetailCorporateQuote::query()->find(3735)->only(['fee', 'subtotal_anual']))->toBe(['fee' => '300.00', 'subtotal_anual' => '1500.00'])
        ->and($owner->fresh()->only(['fee_anual', 'total_amount', 'poblation']))->toEqual($before)
        ->and(qvaPending($owner->code))->toBe($pending)
        ->and(implode(' ', $adjustment->effects['affiliation']['notes']))->toContain('no está contratada');
});

it('individual: la tarifa llega a los afiliados de esa línea y los avisos quedan contra la población activa; la cantidad cambia solo la cotización', function (): void {
    $owner = Affiliation::query()->findOrFail(444);
    $quote = IndividualQuote::query()->findOrFail(4187);

    $adjustment = Adjust::apply($quote, [qvaChange(24450, '150', 4)], 'Tarifa especial aprobada para el grupo familiar.', qvaActor());
    $effects = $adjustment->effects['affiliation'];

    expect(Affiliate::query()->whereIn('id', [497, 498, 499])->pluck('fee')->all())->toBe(['150.00', '150.00', '150.00'])
        ->and((float) $owner->fresh()->fee_anual)->toBe(450.0)
        ->and((float) $owner->fresh()->total_amount)->toBe(112.5)
        ->and((int) $owner->fresh()->family_members)->toBe(3)
        ->and((int) DetailIndividualQuote::query()->find(24450)->total_persons)->toBe(4)
        ->and(implode(' ', $effects['notes']))->toContain('cambia solo en la cotización')
        ->and(count($effects['collections']))->toBeGreaterThan(0)
        ->and(qvaExpectedPending($owner))->toBe([]);

    foreach ($effects['collections'] as $change) {
        expect(Collection::query()->find($change['id'])->total_amount)->toBe('112.50')
            ->and(Collection::query()->find($change['id'])->persons)->toBe('3');
    }

    Adjust::revert($adjustment, 'Se aplicó por error en otra afiliación.', qvaActor());
    expect(Affiliate::query()->whereIn('id', [497, 498, 499])->pluck('fee')->all())->toBe(['160.00', '160.00', '160.00'])
        ->and((float) $owner->fresh()->fee_anual)->toBe(480.0)
        ->and(qvaExpectedPending($owner))->toBe([]);
});

it('al revertir, un aviso que ya se pagó con la tarifa ajustada se respeta y los pendientes vuelven a la población activa', function (): void {
    $owner = Affiliation::query()->findOrFail(444);
    $adjustment = Adjust::apply(IndividualQuote::query()->findOrFail(4187), [qvaChange(24450, '150', 3)], 'Tarifa especial aprobada para el grupo familiar.', qvaActor());
    $changed = $adjustment->effects['affiliation']['collections'];
    expect(count($changed))->toBeGreaterThanOrEqual(2);
    Collection::query()->whereKey($changed[0]['id'])->update(['status' => 'PAGADO']);

    Adjust::revert($adjustment, 'La tarifa especial no fue aprobada finalmente.', qvaActor());

    expect(Collection::query()->find($changed[0]['id'])->total_amount)->toBe('112.50')
        ->and(Collection::query()->find($changed[1]['id'])->total_amount)->toBe('120.00')
        ->and(qvaExpectedPending($owner))->toBe([]);
});

it('no ajusta una individual donde cada afiliado tiene la tarifa de todo el grupo', function (): void {
    $preview = Adjust::preview(IndividualQuote::query()->findOrFail(3863), [qvaChange(22114, '300', 5)]);

    expect($preview['blockers'][0])->toContain('Cada afiliado tiene la tarifa de todo el grupo');
});

it('bloquea: sin cambios, valores inválidos, cotización cambiada, cotización de migración, motivo corto y reverso fuera de orden', function (): void {
    $quote = IndividualQuote::query()->findOrFail(4187);

    expect(Adjust::preview($quote, [qvaChange(24450, '160', 3)])['blockers'][0])->toContain('No cambió ningún valor')
        ->and(Adjust::preview($quote, [qvaChange(24450, '-5', 3)])['blockers'][0])->toContain('la tarifa debe ser')
        ->and(Adjust::preview($quote, [['persons' => 'dos'] + qvaChange(24450, '100', 3)])['blockers'][0])->toContain('personas debe ser')
        ->and(Adjust::preview($quote, [['detail_id' => 24450, 'fee' => '100', 'persons' => 3, 'fee_before' => '999', 'persons_before' => 3]])['blockers'][0])->toContain('cambió mientras la editaba')
        ->and(Adjust::preview($quote, [['detail_id' => 1, 'fee' => '100', 'persons' => 2]])['blockers'][0])->toContain('no pertenece');

    $migration = CorporateQuote::query()->findOrFail(1);
    $detail = DetailCorporateQuote::query()->where('corporate_quote_id', 1)->firstOrFail();
    expect(Adjust::preview($migration, [qvaChange((int) $detail->id, '1', 1)])['blockers'][0])->toContain('afiliaciones');

    expect(fn () => Adjust::apply($quote, [qvaChange(24450, '150', 3)], 'corto', qvaActor()))->toThrow(RuntimeException::class, 'tres palabras');

    $first = Adjust::apply($quote, [qvaChange(24450, '150', 3)], 'Primer ajuste de la tarifa del grupo.', qvaActor());
    Adjust::apply($quote, [qvaChange(24450, '155', 3)], 'Segundo ajuste de la tarifa del grupo.', qvaActor());

    expect(Adjust::revertPreview($first)['blocker'])->toContain('más reciente');
});

it('la ficha de la cotización ofrece «Ajustar valores» con permiso y el historial revierte desde la tabla', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $quote = IndividualQuote::query()->findOrFail(4187);
    $user = qvaActor();
    $user->email = 'ajustes-pest-'.uniqid().'@tudrencasa.com';

    $page = Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Business\Resources\IndividualQuotes\Pages\ViewIndividualQuote::class, ['record' => $quote->id])
        ->assertActionVisible('adjustQuoteValues')
        ->mountAction('adjustQuoteValues');

    $lines = $page->get('mountedActions.0.data.lines');
    $key = collect($lines)->search(fn (array $line): bool => (int) $line['detail_id'] === 24450);
    expect($key)->not->toBeFalse()
        ->and($lines[$key]['contracted'])->toBeTrue();

    $page->set('mountedActions.0.data.lines.'.$key.'.fee', '150')
        ->set('mountedActions.0.data.reason', 'Tarifa especial aprobada para el grupo familiar.')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $adjustment = QuoteValueAdjustment::query()->where('quote_type', 'INDIVIDUAL')->where('quote_id', 4187)->latest('id')->firstOrFail();
    $html = view('filament.shared.quotes.value-adjustment-detail', ['adjustment' => $adjustment])->render();
    expect($html)->toContain('US$ 160.00 × 3 → <span class="font-semibold">US$ 150.00 × 3</span>', 'Aviso POR PAGAR', 'Tarifa especial aprobada');
    expect(view('filament.shared.quotes.value-adjustment-revert-preview', ['preview' => Adjust::revertPreview($adjustment)])->render())->toContain('vuelven a su tarifa anterior');
    expect(Affiliate::query()->find(497)->fee)->toBe('150.00');

    Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Shared\Quotes\QuoteValueAdjustmentsRelationManager::class, ['ownerRecord' => $quote, 'pageClass' => App\Filament\Business\Resources\IndividualQuotes\Pages\ViewIndividualQuote::class])
        ->assertCanSeeTableRecords([$adjustment])
        ->mountTableAction('revert', $adjustment)
        ->set('mountedActions.0.data.reason', 'Se revierte porque la tarifa no fue aprobada.')
        ->callMountedAction()
        ->assertHasNoActionErrors();

    expect($adjustment->fresh()->status)->toBe(QuoteValueAdjustment::STATUS_REVERTED)
        ->and(Affiliate::query()->find(497)->fee)->toBe('160.00');
});

it('sin el permiso no aparece «Ajustar valores» ni se puede revertir', function (): void {
    Filament\Facades\Filament::setCurrentPanel('business');
    $user = User::factory()->make(['id' => 987991, 'name' => 'Analista Sin Permiso', 'email' => 'sin-permiso-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['NEGOCIOS']]);

    $this->actingAs($user);
    expect(App\Filament\Shared\Quotes\AdjustQuoteValuesAction::canUse())->toBeFalse()
        ->and(App\Filament\Shared\Quotes\AdjustQuoteValuesAction::make()->isVisible())->toBeFalse();

    $this->actingAs(qvaActor());
    expect(App\Filament\Shared\Quotes\AdjustQuoteValuesAction::canUse())->toBeTrue();
});

it('Administración muestra en la tabla corporativa la población activa y «Recalcular totales» enseña los avisos que alinea', function (): void {
    Filament\Facades\Filament::setCurrentPanel('administration');
    $owner = AffiliationCorporate::query()->findOrFail(56);
    $live = App\Support\Affiliations\ActiveBilling\ActivePopulationBilling::live($owner);
    AffiliationCorporate::query()->whereKey(56)->update(['poblation' => 999, 'fee_anual' => 1, 'total_amount' => 1]);
    $user = User::factory()->make(['id' => 987992, 'name' => 'Analista Administración Pest', 'email' => 'admin-pest-'.uniqid().'@tudrencasa.com', 'status' => 'ACTIVO', 'departament' => ['SUPERADMIN', 'ADMINISTRACION'], 'is_accountManagers' => false]);

    Livewire\Livewire::actingAs($user)
        ->test(App\Filament\Administration\Resources\AffiliationCorporates\Pages\ListAffiliationCorporates::class)
        ->assertTableColumnStateSet('poblation', $live['persons'], $owner)
        ->assertTableColumnStateSet('fee_anual', $live['fee_anual'], $owner);

    $html = view('filament.business.affiliation-corporates.recalculate-totals-preview', [
        'preview' => ActivePopulationTotals::preview($owner->fresh()),
        'record' => $owner->fresh(),
        'billing' => App\Support\Affiliations\ActiveBilling\ActivePopulationBilling::plan($owner->fresh()),
    ])->render();

    expect($html)->toContain('comprobante de pago pendiente');
});
