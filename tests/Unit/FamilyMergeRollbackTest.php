<?php

declare(strict_types=1);

use App\Jobs\PrepareAffiliationRenovations;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationFamilyMerge;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\PaidMembership;
use App\Models\Renovation;
use App\Models\User;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\Affiliations\FamilyMergeRollback;
use App\Support\Affiliations\FamilyMergeSnapshot;
use App\Support\Affiliations\MergeIndividualAffiliationsRequest;
use App\Support\Affiliations\MergeIndividualAffiliationsResult;
use App\Support\Affiliations\MergeIndividualAffiliationsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Flujo real de unificación y reversión. Escribe, así que corre dentro de una
 * transacción que siempre se revierte: no queda nada en la base. Las pólizas son
 * copias de una afiliación activa que tarifica bien, con cédulas de prueba.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Queue::fake();

    if (! Schema::hasTable('affiliation_family_merges')) {
        $this->markTestSkipped('Falta la tabla affiliation_family_merges.');
    }

    $template = familyMergeTestTemplate();

    if (! $template instanceof Affiliation) {
        $this->markTestSkipped('No hay una afiliación ACTIVA que tarifique completa para clonar.');
    }

    $this->target = familyMergeTestClone($template, 'T', '01/03/2026');
    $this->source = familyMergeTestClone($template, 'S', '01/06/2026');
    $this->user = User::factory()->make(['name' => 'Analista de prueba']);
});

afterEach(function (): void {
    DB::rollBack();
});

function familyMergeTestTemplate(): ?Affiliation
{
    $calculator = app(AffiliationAffiliateFeeCalculator::class);

    return Affiliation::query()
        ->where('status', 'ACTIVA')
        ->whereNull('white_company_fee_id')
        ->whereNull('white_company_neta')
        ->whereNull('white_company_sale_price')
        ->whereHas('affiliates', fn ($query) => $query->where('status', 'ACTIVO'))
        ->with(['affiliates' => fn ($query) => $query->where('status', 'ACTIVO')])
        ->orderByDesc('id')
        ->limit(100)
        ->get()
        ->first(fn (Affiliation $affiliation): bool => $affiliation->affiliates->every(
            fn (Affiliate $affiliate): bool => $calculator->calculateAffiliateAmounts($affiliation, $affiliate) !== null
        ));
}

function familyMergeTestClone(Affiliation $template, string $tag, string $effectiveDate): Affiliation
{
    $clone = $template->replicate();
    $clone->code = 'TEST-FM-'.$tag.'-'.Str::upper(Str::random(8));
    $clone->status = 'ACTIVA';
    $clone->effective_date = $effectiveDate;
    $clone->save();

    foreach ($template->affiliates as $index => $affiliate) {
        $copy = $affiliate->replicate();
        $copy->affiliation_id = $clone->id;
        $copy->nro_identificacion = 'TFM'.$tag.$index.random_int(100000, 999999);
        $copy->relationship = $index === 0 ? 'TITULAR' : 'HIJO';
        $copy->save();
    }

    app(AffiliationAffiliateFeeCalculator::class)->recalculateAffiliationTotalsFromAffiliates($clone);

    return $clone->fresh('affiliates');
}

function familyMergeTestRequest(
    Affiliation $target,
    Affiliation $source,
    string $reason = 'Familia cargada en dos pólizas por error',
    ?string $effectiveDate = null,
    ?string $frequency = null,
): MergeIndividualAffiliationsRequest {
    $titular = $target->affiliates->firstWhere('relationship', 'TITULAR');
    $relationships = [];

    foreach ([...$target->affiliates, ...$source->affiliates] as $affiliate) {
        $relationships[(int) $affiliate->id] = (int) $affiliate->id === (int) $titular->id ? 'TITULAR' : 'OTRO';
    }

    return new MergeIndividualAffiliationsRequest(
        targetAffiliationId: (int) $target->id,
        sourceAffiliationIds: [(int) $source->id],
        titularAffiliateId: (int) $titular->id,
        relationships: $relationships,
        reason: $reason,
        actorName: 'Analista de prueba',
        effectiveDate: $effectiveDate,
        paymentFrequency: $frequency,
    );
}

/**
 * Aviso de cobro de prueba para una póliza, clonado de uno real para respetar las columnas obligatorias.
 */
function familyMergeTestCollection(Affiliation $affiliation, string $status, string $date, string $frequency = 'TRIMESTRAL'): Collection
{
    $template = Collection::query()->first();

    if (! $template instanceof Collection) {
        test()->markTestSkipped('No hay avisos de cobro para clonar.');
    }

    $collection = $template->replicate();
    $collection->affiliation_code = $affiliation->code;
    $collection->status = $status;
    $collection->payment_frequency = $frequency;
    $collection->next_payment_date = $date;
    $collection->filter_next_payment_date = Carbon\Carbon::createFromFormat('!d/m/Y', $date)->format('Y-m-d');
    $collection->collection_invoice_number = 'TEST-'.Str::upper(Str::random(8));
    $collection->created_at = now()->subDay();
    $collection->save();

    return $collection;
}

function familyMergeTestExecute(Affiliation $target, Affiliation $source, ?string $effectiveDate = null, ?string $frequency = null): MergeIndividualAffiliationsResult
{
    $service = app(MergeIndividualAffiliationsService::class);
    $request = familyMergeTestRequest($target, $source, effectiveDate: $effectiveDate, frequency: $frequency);
    $preview = $service->preview($request);

    expect($preview->blockers)->toBe([]);

    return $service->execute($request);
}

it('arma la vista previa con el calendario de cobro de la vigencia y frecuencia elegidas', function (): void {
    $preview = app(MergeIndividualAffiliationsService::class)->preview(
        familyMergeTestRequest($this->target, $this->source, effectiveDate: '01/03/2026', frequency: 'TRIMESTRAL'),
    );

    expect($preview->canExecute())->toBeTrue()
        ->and($preview->newFamilyMembers)->toBe($this->target->affiliates->count() + $this->source->affiliates->count())
        ->and($preview->billing['renewal_date'])->toBe('01/03/2027')
        ->and(array_column($preview->billing['installments'], 'date'))->toBe(['01/03/2026', '01/06/2026', '01/09/2026', '01/12/2026'])
        ->and($preview->billing['installments'][0]['amount'])->toBe(round($preview->newFeeAnual / 4, 2))
        ->and($preview->billing['installments'][0]['overdue'])->toBeTrue()
        ->and((string) $preview->billingHtml())->toContain('Próximas fechas de pago')
        ->and(implode(' ', $preview->warnings))->toContain('Todo el grupo renovará el 01/03/2027');
});

it('ya no bloquea por frecuencias distintas: manda la elegida', function (): void {
    $this->source->forceFill(['payment_frequency' => 'ANUAL'])->save();

    $preview = app(MergeIndividualAffiliationsService::class)->preview(
        familyMergeTestRequest($this->target, $this->source->fresh('affiliates'), frequency: 'MENSUAL'),
    );

    expect($preview->blockers)->toBe([])
        ->and($preview->billing['installments'])->toHaveCount(12);
});

it('bloquea una vigencia anterior a lo ya pagado (cobro doble)', function (): void {
    familyMergeTestCollection($this->source, 'PAGADO', '01/09/2026');

    $preview = app(MergeIndividualAffiliationsService::class)->preview(
        familyMergeTestRequest($this->target, $this->source, effectiveDate: '01/03/2026', frequency: 'TRIMESTRAL'),
    );

    expect(implode(' ', $preview->blockers))->toContain('pagado hasta el 01/12/2026')->toContain('dos veces');
    expect(fn () => app(MergeIndividualAffiliationsService::class)->execute(
        familyMergeTestRequest($this->target, $this->source, effectiveDate: '01/03/2026', frequency: 'TRIMESTRAL'),
    ))->toThrow(App\Support\Affiliations\MergeIndividualAffiliationsException::class);
    expect($this->source->fresh()->status)->toBe('ACTIVA');
});

it('advierte el hueco sin cobrar cuando la vigencia es posterior a lo pagado', function (): void {
    familyMergeTestCollection($this->source, 'PAGADO', '01/12/2025');

    $preview = app(MergeIndividualAffiliationsService::class)->preview(
        familyMergeTestRequest($this->target, $this->source, effectiveDate: '01/04/2026', frequency: 'TRIMESTRAL'),
    );

    expect(implode(' ', $preview->warnings))->toContain('sin cobrar hasta la vigencia del 01/04/2026');
});

it('bloquea si hay avisos POR PAGAR anteriores a la vigencia, para no perdonar deuda', function (): void {
    familyMergeTestCollection($this->source, 'POR PAGAR', '01/01/2026');

    $preview = app(MergeIndividualAffiliationsService::class)->preview(
        familyMergeTestRequest($this->target, $this->source, effectiveDate: '01/03/2026'),
    );

    expect(implode(' ', $preview->blockers))->toContain('perdonaría esa deuda');
});

it('bloquea si hay comprobantes de pago por aprobar', function (): void {
    $template = PaidMembership::query()->first();

    if (! $template instanceof PaidMembership) {
        $this->markTestSkipped('No hay pagos para clonar.');
    }

    $proof = $template->replicate();
    $proof->affiliation_id = $this->source->id;
    $proof->status = 'PENDIENTE';
    $proof->save();

    $preview = app(MergeIndividualAffiliationsService::class)->preview(familyMergeTestRequest($this->target, $this->source));

    expect(implode(' ', $preview->blockers))->toContain('comprobante(s) de pago por aprobar');
});

it('crea en Administración los avisos del calendario y anula los pendientes reemplazados', function (): void {
    $replaced = familyMergeTestCollection($this->source, 'POR PAGAR', '01/06/2026');
    $result = familyMergeTestExecute($this->target, $this->source, '01/03/2026', 'MENSUAL');
    $target = $this->target->fresh();

    $created = Collection::query()
        ->where('affiliation_code', $target->code)
        ->where('status', 'POR PAGAR')
        ->orderBy('filter_next_payment_date')
        ->get();

    expect($target->effective_date)->toBe('01/03/2026')
        ->and($target->payment_frequency)->toBe('MENSUAL')
        ->and($created)->toHaveCount(12)
        ->and($created->first()->next_payment_date)->toBe('01/03/2026')
        ->and($created->last()->next_payment_date)->toBe('01/02/2027')
        ->and((float) $created->first()->total_amount)->toBe(round((float) $target->fee_anual / 12, 2))
        ->and((string) $created->first()->persons)->toBe((string) $target->family_members)
        ->and($created->pluck('collection_invoice_number')->unique())->toHaveCount(12)
        ->and($replaced->fresh()->status)->toBe(MergeIndividualAffiliationsService::COLLECTION_CANCELLED_STATUS)
        ->and($result->recalculatedCollections)->toBe(12)
        ->and(Renovation::query()->where('affiliation_id', $target->id)->where('date_renewal', '2027-03-01')->exists())->toBeTrue();
});

it('la reversión anula los avisos creados y devuelve vigencia y frecuencia', function (): void {
    $replaced = familyMergeTestCollection($this->source, 'POR PAGAR', '01/06/2026');
    $previousFrequency = (string) $this->target->payment_frequency;
    $result = familyMergeTestExecute($this->target, $this->source, '01/04/2026', 'MENSUAL');

    FamilyMergeRollback::execute(AffiliationFamilyMerge::query()->findOrFail($result->mergeId), $this->user, 'Prueba de reversión del calendario de cobro');

    $target = $this->target->fresh();

    expect($target->effective_date)->toBe('01/03/2026')
        ->and($target->payment_frequency)->toBe($previousFrequency)
        ->and(Collection::query()->where('affiliation_code', $target->code)->where('status', 'POR PAGAR')->count())->toBe(0)
        ->and($replaced->fresh()->status)->toBe('POR PAGAR')
        ->and(Renovation::query()->where('affiliation_id', $target->id)->where('date_renewal', '2027-04-01')->value('status'))->toBe('ANULADA');
});

it('no revierte si ya se pagó un aviso creado por la unificación', function (): void {
    $result = familyMergeTestExecute($this->target, $this->source, '01/03/2026', 'TRIMESTRAL');
    $merge = AffiliationFamilyMerge::query()->findOrFail($result->mergeId);

    Collection::query()->whereIn('id', $merge->summary['created_collection_ids'])->limit(1)->update(['status' => 'PAGADO']);

    expect(implode(' ', FamilyMergeRollback::blockers($merge)))->toContain('Cuota')->toContain('PAGADO');
});

it('unifica, deja punto de restauración y recalcula la renovación del destino en el acto', function (): void {
    $people = $this->target->affiliates->count() + $this->source->affiliates->count();
    $result = familyMergeTestExecute($this->target, $this->source);

    $merge = AffiliationFamilyMerge::query()->findOrFail($result->mergeId);
    $renovation = Renovation::query()->where('affiliation_id', $this->target->id)->first();

    expect($this->source->fresh()->status)->toBe('EXCLUIDO')
        ->and(Affiliate::query()->where('affiliation_id', $this->target->id)->count())->toBe($people)
        ->and($merge->status)->toBe(AffiliationFamilyMerge::STATUS_APPLIED)
        ->and(array_keys($merge->snapshot_before['affiliates']))->toHaveCount($people)
        ->and($merge->snapshot_before['affiliations'][$this->source->id]['status'])->toBe('ACTIVA')
        ->and($merge->snapshot_after['affiliations'][$this->source->id]['status'])->toBe('EXCLUIDO')
        ->and($renovation)->not->toBeNull()
        ->and((int) $renovation->total_persons)->toBe($people)
        ->and(FamilyMergeRollback::latestAppliedFor($this->source)?->id)->toBe($merge->id);
});

it('revertir devuelve cada fila exactamente a su foto previa', function (): void {
    $result = familyMergeTestExecute($this->target, $this->source);
    $merge = AffiliationFamilyMerge::query()->findOrFail($result->mergeId);
    $before = $merge->snapshot_before;

    expect(FamilyMergeRollback::blockers($merge))->toBe([]);

    $summary = FamilyMergeRollback::execute($merge, $this->user, 'Se unificó la póliza equivocada, se separa de nuevo');

    expect(FamilyMergeSnapshot::differences($before, FamilyMergeSnapshot::capture(FamilyMergeSnapshot::idsOf($before))))->toBe([])
        ->and($merge->fresh()->status)->toBe(AffiliationFamilyMerge::STATUS_ROLLED_BACK)
        ->and($this->source->fresh()->status)->toBe('ACTIVA')
        ->and(Affiliate::query()->where('affiliation_id', $this->source->id)->count())->toBe($this->source->affiliates->count())
        ->and((int) Renovation::query()->where('affiliation_id', $this->target->id)->value('total_persons'))->toBe($this->target->affiliates->count())
        ->and($summary['affiliations'])->toContain($this->target->code, $this->source->code)
        ->and(FamilyMergeRollback::latestAppliedFor($this->target))->toBeNull();
});

it('no revierte nada si alguien cambió una fila después de unificar', function (): void {
    $result = familyMergeTestExecute($this->target, $this->source);
    $merge = AffiliationFamilyMerge::query()->findOrFail($result->mergeId);

    DB::table('affiliations')->where('id', $this->target->id)->update(['fee_anual' => 1]);

    expect(implode(' ', FamilyMergeRollback::blockers($merge)))->toContain('tarifa anual');

    expect(fn () => FamilyMergeRollback::execute($merge, $this->user, 'Intento de reversión con cambios posteriores'))
        ->toThrow(RuntimeException::class);

    expect($this->source->fresh()->status)->toBe('EXCLUIDO')
        ->and($merge->fresh()->status)->toBe(AffiliationFamilyMerge::STATUS_APPLIED);
});

it('exige un motivo suficiente para revertir', function (): void {
    $result = familyMergeTestExecute($this->target, $this->source);
    $merge = AffiliationFamilyMerge::query()->findOrFail($result->mergeId);

    expect(fn () => FamilyMergeRollback::execute($merge, $this->user, 'corto'))
        ->toThrow(RuntimeException::class, 'mínimo');

    expect($merge->fresh()->status)->toBe(AffiliationFamilyMerge::STATUS_APPLIED);
});

it('recalcula la cuota anual pendiente del destino sin escribir columnas inexistentes y la reversión la restituye', function (): void {
    $template = AnnualCollection::query()->first();

    if (! $template instanceof AnnualCollection) {
        $this->markTestSkipped('No hay cuotas anuales para clonar.');
    }

    $annual = $template->replicate();
    $annual->affiliation_code = $this->target->code;
    $annual->status = MergeIndividualAffiliationsService::COLLECTION_PENDING_STATUS;
    $annual->persons = '1';
    $annual->created_at = now()->subMinute();
    $annual->save();

    $result = familyMergeTestExecute($this->target, $this->source);

    expect((string) $annual->fresh()->persons)->toBe((string) $result->newFamilyMembers);

    FamilyMergeRollback::execute(
        AffiliationFamilyMerge::query()->findOrFail($result->mergeId),
        $this->user,
        'Prueba de reversión con cuota anual pendiente',
    );

    expect((string) $annual->fresh()->persons)->toBe('1');
});

it('una segunda unificación posterior bloquea revertir la primera', function (): void {
    $first = familyMergeTestExecute($this->target, $this->source);
    $third = familyMergeTestClone(familyMergeTestTemplate(), 'X', '01/03/2026');
    familyMergeTestExecute($this->target->fresh('affiliates'), $third);

    expect(implode(' ', FamilyMergeRollback::blockers(AffiliationFamilyMerge::query()->findOrFail($first->mergeId))))
        ->toContain('Revierta primero esa');
});

it('la renovación diaria y el refresco puntual comparten el mismo cálculo', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Jobs/PrepareAffiliationRenovations.php');

    expect($source)
        ->toContain('$outcome = $this->upsertRenovation($affiliation, $calculator, $feeProjection, $today);')
        ->toContain('public static function refreshForAffiliation(Affiliation $affiliation')
        ->and(PrepareAffiliationRenovations::refreshForAffiliation($this->source->replicate()->forceFill(['status' => 'EXCLUIDO'])))
        ->toBeFalse();
});

it('compara la foto como número cuando ambos lados son numéricos', function (): void {
    expect(FamilyMergeSnapshot::sameValue('120', '120.00'))->toBeTrue()
        ->and(FamilyMergeSnapshot::sameValue('120', '121'))->toBeFalse()
        ->and(FamilyMergeSnapshot::sameValue(null, ''))->toBeFalse()
        ->and(FamilyMergeSnapshot::differences(
            ['affiliations' => [5 => ['status' => 'ACTIVA']]],
            ['affiliations' => []],
        ))->toHaveCount(1);
});
