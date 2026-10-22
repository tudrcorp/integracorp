<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\AffiliationDateCorrection;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\PaidMembershipCorporate;
use App\Models\RenovationCorporate;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionPreview;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrectionRollback;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use App\Support\Audit\TableRowSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Corrección de vigencia de afiliaciones corporativas. Escribe: corre dentro de una transacción
 * que siempre se revierte. La afiliación es una copia de prueba (TEST-ACDC-…) con avisos,
 * renovación y pago clonados de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $template = AffiliationCorporate::query()->first();
    $this->collectionTemplate = Collection::query()->first();
    $renovationTemplate = RenovationCorporate::query()->first();
    $paymentTemplate = PaidMembershipCorporate::query()->first();

    if (! $template || ! $this->collectionTemplate || ! $renovationTemplate || ! $paymentTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->affiliation = $template->replicate();
    $this->affiliation->code = 'TEST-ACDC-'.Str::upper(Str::random(8));
    $this->affiliation->status = 'ACTIVA';
    $this->affiliation->payment_frequency = 'TRIMESTRAL';
    $this->affiliation->activated_at = '20/07/2026';
    $this->affiliation->effective_date = '20/07/2026';
    $this->affiliation->save();

    $this->collections = collect(['20/10/2026', '20/01/2027', '20/04/2027'])
        ->map(fn (string $date): Collection => corporateDateTestCollection($this->collectionTemplate, $this->affiliation, '20/07/2026', $date));

    $this->renovation = $renovationTemplate->replicate();
    $this->renovation->affiliation_corporate_id = $this->affiliation->id;
    $this->renovation->date_renewal = '2027-07-20';
    $this->renovation->status = 'VIGENTE';
    $this->renovation->save();

    $this->payment = $paymentTemplate->replicate();
    $this->payment->affiliation_corporate_id = $this->affiliation->id;
    $this->payment->status = 'APROBADO';
    $this->payment->prox_payment_date = '07-09-2027';
    $this->payment->renewal_date = '07-09-2027';
    $this->payment->created_at = '2026-09-07 14:44:21';
    $this->payment->save();

    $this->actor = User::factory()->make(['name' => 'Analista de prueba', 'departament' => ['SUPERADMIN']]);
});

afterEach(function (): void {
    DB::rollBack();
});

function corporateDateTestCollection(Collection $template, AffiliationCorporate $affiliation, string $include, string $date, string $status = 'POR PAGAR'): Collection
{
    $collection = $template->replicate();
    $collection->affiliation_code = $affiliation->code;
    $collection->include_date = $include;
    $collection->next_payment_date = $date;
    $collection->status = $status;
    $collection->total_amount = 108639.50;
    $collection->payment_frequency = 'TRIMESTRAL';
    $collection->collection_invoice_number = 'T-'.Str::upper(Str::random(6));
    $collection->created_at = now()->subDays(10);
    $collection->save();

    return $collection;
}

it('reprograma cada aviso POR PAGAR conservando su número de cuota', function (): void {
    $plan = AffiliationDateCorrector::plan($this->affiliation, '26/08/2026', CarbonImmutable::create(2026, 10, 22));

    expect($plan['blockers'])->toBe([])
        ->and($plan['scope'])->toBe(AffiliationDateCorrector::SCOPE_FIRST_YEAR)
        ->and(array_column($plan['collections'], 'installment'))->toBe(['2 de 4', '3 de 4', '4 de 4'])
        ->and(array_column($plan['collections'], 'new'))->toBe(['26/11/2026', '26/02/2027', '26/05/2027'])
        ->and($plan['collections'][0]['was_overdue'])->toBeTrue()
        ->and($plan['collections'][0]['is_overdue'])->toBeFalse()
        ->and($plan['after']['activated_at'])->toBe('26/08/2026')
        ->and($plan['after']['renewal'])->toBe('26/08/2027')
        ->and($plan['renovations'][0])->toMatchArray(['action' => 'move', 'new' => '26/08/2027'])
        ->and($plan['payments'][0]['prox_new'])->toBe('26-11-2026');
});

it('aplica la corrección en todo lo que depende de la fecha, sin tocar montos', function (): void {
    $correction = AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);
    $affiliation = $this->affiliation->fresh();
    $first = $this->collections->first()->fresh();

    expect($affiliation->activated_at)->toBe('26/08/2026')
        ->and($affiliation->effective_date)->toBe('26/08/2026')
        ->and($first->next_payment_date)->toBe('26/11/2026')
        ->and($first->filter_next_payment_date)->toBe('2026-11-26')
        ->and($first->include_date)->toBe('26/08/2026')
        ->and((float) $first->total_amount)->toBe(108639.50)
        ->and((string) $this->renovation->fresh()->getRawOriginal('date_renewal'))->toStartWith('2027-08-26')
        ->and($this->payment->fresh()->prox_payment_date)->toBe('26-11-2026')
        ->and($this->payment->fresh()->status)->toBe('APROBADO')
        ->and($correction->affiliation_type)->toBe(DateCorrectionSubject::TYPE_CORPORATE)
        ->and(CollectionAdjustment::query()->where('affiliation_code', $affiliation->code)->where('mode', CollectionAdjustment::MODE_EFFECTIVE_DATE)->count())->toBe(3);
});

it('corrige los avisos aunque la fecha ya se haya editado a mano en la tabla', function (): void {
    $this->affiliation->forceFill(['activated_at' => '26/08/2026', 'effective_date' => '26/08/2026'])->save();

    $plan = AffiliationDateCorrector::plan($this->affiliation, '26/08/2026');

    expect($plan['blockers'])->toBe([])
        ->and($plan['before']['cycle_start'])->toBe('20/07/2026')
        ->and(array_column($plan['collections'], 'new'))->toBe(['26/11/2026', '26/02/2027', '26/05/2027']);
});

it('dice que no hay nada que corregir solo cuando la afiliación y sus avisos ya están en la fecha', function (): void {
    AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);

    expect(implode(' ', AffiliationDateCorrector::plan($this->affiliation->fresh(), '26/08/2026')['blockers']))
        ->toContain('no hay nada que corregir');
});

it('en una póliza renovada no toca la emisión original', function (): void {
    $history = AffiliationCorporateRenovationHistory::query()->first();

    if (! $history instanceof AffiliationCorporateRenovationHistory) {
        $this->markTestSkipped('No hay historial de renovaciones para clonar.');
    }

    $copy = $history->replicate();
    $copy->affiliation_corporate_id = $this->affiliation->id;
    $copy->save();

    $this->affiliation->forceFill(['activated_at' => '10/04/2025'])->save();

    AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'Vigencia del ciclo renovado mal cargada', $this->actor);

    expect($this->affiliation->fresh()->activated_at)->toBe('10/04/2025')
        ->and($this->affiliation->fresh()->effective_date)->toBe('26/08/2026');
});

it('si los avisos ya son del segundo año, la vigencia es el inicio de ese año', function (): void {
    $this->collections->each(fn (Collection $collection) => $collection->forceFill(['status' => 'ANULADO'])->save());
    corporateDateTestCollection($this->collectionTemplate, $this->affiliation, '10/04/2025', '10/01/2027');
    $this->affiliation->forceFill(['activated_at' => '10/04/2025', 'effective_date' => '10/04/2026'])->save();

    $plan = AffiliationDateCorrector::plan($this->affiliation, '15/04/2025');

    expect($plan['blockers'])->toBe([])
        ->and($plan['collections'][0]['installment'])->toBe('4 de 4')
        ->and($plan['collections'][0]['new'])->toBe('15/01/2027')
        ->and($plan['after']['activated_at'])->toBe('15/04/2025')
        ->and($plan['after']['effective_date'])->toBe('15/04/2026')
        ->and($plan['after']['renewal'])->toBe('15/04/2027')
        ->and(implode(' ', $plan['warnings']))->toContain('año 2 del calendario');
});

it('deja una sola propuesta de renovación: conserva la de la fecha nueva y anula la duplicada', function (): void {
    $duplicate = $this->renovation->replicate();
    $duplicate->date_renewal = '2027-08-26';
    $duplicate->save();

    $plan = AffiliationDateCorrector::plan($this->affiliation, '26/08/2026');
    $actions = collect($plan['renovations'])->pluck('action', 'id');

    expect($actions[$this->renovation->id])->toBe('annul')
        ->and($actions[$duplicate->id])->toBe('keep');

    AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);

    expect($this->renovation->fresh()->status)->toBe(AffiliationDateCorrector::RENOVATION_CANCELLED)
        ->and($duplicate->fresh()->status)->toBe('VIGENTE');
});

it('revertir devuelve cada fila exactamente a su foto previa', function (): void {
    $correction = AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);
    $before = $correction->snapshot_before;
    $columns = DateCorrectionSubject::corporate()->columns();

    expect(AffiliationDateCorrectionRollback::blockers($correction))->toBe([]);

    AffiliationDateCorrectionRollback::execute($correction, $this->actor, 'Se corrigió con la fecha equivocada, se deshace');

    $current = TableRowSnapshot::capture($columns, TableRowSnapshot::idsOf($columns, $before));

    expect(TableRowSnapshot::differences($columns, $before, $current))->toBe([])
        ->and($this->affiliation->fresh()->activated_at)->toBe('20/07/2026')
        ->and($correction->fresh()->status)->toBe(AffiliationDateCorrection::STATUS_ROLLED_BACK)
        ->and(AffiliationDateCorrectionRollback::latestAppliedFor($this->affiliation))->toBeNull();
});

it('no revierte si un aviso reprogramado ya se pagó', function (): void {
    $correction = AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);

    DB::table('collections')->where('id', $this->collections->first()->id)->update(['status' => 'PAGADO']);

    expect(implode(' ', AffiliationDateCorrectionRollback::blockers($correction)))->toContain('PAGADO');
    expect(fn () => AffiliationDateCorrectionRollback::execute($correction, $this->actor, 'Intento con un aviso ya pagado'))
        ->toThrow(RuntimeException::class);
    expect($this->affiliation->fresh()->activated_at)->toBe('26/08/2026');
});

it('bloquea con comprobantes por aprobar, período de renovación o un aviso fuera de calendario', function (): void {
    $proof = $this->payment->replicate();
    $proof->status = 'PENDIENTE';
    $proof->save();

    $this->renovation->forceFill(['status' => 'PERIODO DE RENOVACION'])->save();
    $this->collections->last()->forceFill(['next_payment_date' => '03/05/2027'])->save();

    expect(implode(' ', AffiliationDateCorrector::plan($this->affiliation, '26/08/2026')['blockers']))
        ->toContain('comprobante(s) de pago por aprobar')
        ->toContain('PERÍODO DE RENOVACIÓN')
        ->toContain('no sigue el calendario');

    expect(fn () => AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'Intento con bloqueos pendientes', $this->actor))
        ->toThrow(RuntimeException::class);
    expect($this->affiliation->fresh()->activated_at)->toBe('20/07/2026');
});

it('bloquea fechas absurdas y afiliaciones no activas', function (): void {
    expect(implode(' ', AffiliationDateCorrector::plan($this->affiliation, '20/07/2028')['blockers']))->toContain('más de 12 meses');

    $this->affiliation->forceFill(['status' => 'EXCLUIDA'])->save();

    expect(implode(' ', AffiliationDateCorrector::plan($this->affiliation, '26/08/2026')['blockers']))->toContain('ACTIVAS');
});

it('exige motivo y el permiso de corporativas', function (): void {
    expect(fn () => AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'corto', $this->actor))
        ->toThrow(RuntimeException::class, 'mínimo');

    $withoutPermission = User::factory()->make(['departament' => ['ADMINISTRACION', 'NEGOCIOS']]);

    expect(fn () => AffiliationDateCorrector::apply($this->affiliation, '26/08/2026', 'Motivo suficientemente largo', $withoutPermission))
        ->toThrow(RuntimeException::class, 'permiso');
});

it('ubica la cuota en el calendario aun con fin de mes', function (): void {
    $anchor = CarbonImmutable::create(2026, 1, 31);

    expect(AffiliationDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 4, 30), 3))->toBe(1)
        ->and(AffiliationDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 7, 31), 3))->toBe(2)
        ->and(AffiliationDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 1, 31), 3))->toBe(0)
        ->and(AffiliationDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 5, 3), 3))->toBeNull();
});

it('la vista previa escapa los datos', function (): void {
    $html = (string) AffiliationDateCorrectionPreview::html([
        'blockers' => ['Bloqueo <script>'],
        'warnings' => [],
        'before' => ['activated_at' => '20/07/2026', 'effective_date' => '20/07/2026', 'cycle_start' => '20/07/2026', 'renewal' => '20/07/2027'],
        'after' => ['activated_at' => '26/08/2026', 'effective_date' => '26/08/2026', 'renewal' => '26/08/2027'],
        'frequency' => 'TRIMESTRAL',
        'collections' => [['id' => 1, 'invoice' => '09-00913', 'installment' => '2 de 4', 'k' => 1, 'old' => '20/10/2026', 'new' => '26/11/2026', 'include_old' => '20/07/2026', 'amount' => 108639.5, 'was_overdue' => true, 'is_overdue' => false]],
        'annual' => [], 'renovations' => [], 'payments' => [],
    ]);

    expect($html)->toContain('deja de estar vencido')->toContain('108.639,50')->not->toContain('<script>');
});

it('las fechas son de solo lectura en las tablas y la corrección guiada está en Negocios y Administración', function (): void {
    $root = dirname(__DIR__, 2).'/app/Filament/';

    foreach ([
        'Business/Resources/Affiliations/Tables/AffiliationsTable.php',
        'Business/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
        'Marketing/Resources/AffiliationCorporates/Tables/AffiliationCorporatesTable.php',
    ] as $table) {
        expect((string) file_get_contents($root.$table))
            ->not->toContain("TextInputColumn::make('activated_at')")
            ->not->toContain("TextInputColumn::make('effective_date')");
    }

    foreach ([
        'Business/Resources/Affiliations/Pages/ViewAffiliation.php',
        'Business/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php',
        'Administration/Resources/Affiliations/Pages/ViewAffiliation.php',
        'Administration/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php',
    ] as $page) {
        expect((string) file_get_contents($root.$page))
            ->toContain('AffiliationDateCorrectionActions::correct()')
            ->toContain('AffiliationDateCorrectionActions::revert()');
    }

    expect((string) file_get_contents($root.'Marketing/Resources/Affiliations/Schemas/AffiliationForm.php'))
        ->toContain("->disabled(fn (string \$operation): bool => \$operation === 'edit')");
});
