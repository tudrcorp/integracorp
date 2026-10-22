<?php

declare(strict_types=1);

use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateDateCorrection;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\PaidMembershipCorporate;
use App\Models\RenovationCorporate;
use App\Models\User;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrectionPreview;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrectionRollback;
use App\Support\AffiliationCorporates\DateCorrection\CorporateDateCorrector;
use App\Support\Audit\TableRowSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

/**
 * Escribe: corre dentro de una transacción que siempre se revierte. La afiliación es una copia
 * de prueba (código TEST-ACDC-…) con avisos, renovación y pago clonados de filas reales.
 */
beforeEach(function (): void {
    DB::beginTransaction();

    $template = AffiliationCorporate::query()->first();
    $collectionTemplate = Collection::query()->first();
    $renovationTemplate = RenovationCorporate::query()->first();
    $paymentTemplate = PaidMembershipCorporate::query()->first();

    if (! $template || ! $collectionTemplate || ! $renovationTemplate || ! $paymentTemplate) {
        $this->markTestSkipped('Faltan filas reales para clonar.');
    }

    $this->affiliation = $template->replicate();
    $this->affiliation->code = 'TEST-ACDC-'.Str::upper(Str::random(8));
    $this->affiliation->status = 'ACTIVA';
    $this->affiliation->payment_frequency = 'TRIMESTRAL';
    $this->affiliation->activated_at = '20/07/2026';
    $this->affiliation->effective_date = '20/07/2026';
    $this->affiliation->save();

    $this->collections = collect(['20/10/2026', '20/01/2027', '20/04/2027'])->map(function (string $date) use ($collectionTemplate): Collection {
        $collection = $collectionTemplate->replicate();
        $collection->affiliation_code = $this->affiliation->code;
        $collection->include_date = '20/07/2026';
        $collection->next_payment_date = $date;
        $collection->status = 'POR PAGAR';
        $collection->total_amount = 108639.50;
        $collection->payment_frequency = 'TRIMESTRAL';
        $collection->collection_invoice_number = 'T-'.Str::upper(Str::random(6));
        $collection->created_at = now()->subDays(10);
        $collection->save();

        return $collection;
    });

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

it('reprograma cada aviso POR PAGAR conservando su número de cuota', function (): void {
    $plan = CorporateDateCorrector::plan($this->affiliation, '26/08/2026', CarbonImmutable::create(2026, 10, 22));

    expect($plan['blockers'])->toBe([])
        ->and(array_column($plan['collections'], 'installment'))->toBe(['2 de 4', '3 de 4', '4 de 4'])
        ->and(array_column($plan['collections'], 'new'))->toBe(['26/11/2026', '26/02/2027', '26/05/2027'])
        ->and($plan['collections'][0]['was_overdue'])->toBeTrue()
        ->and($plan['collections'][0]['is_overdue'])->toBeFalse()
        ->and($plan['after']['renewal'])->toBe('26/08/2027')
        ->and($plan['renovations'][0]['new'])->toBe('26/08/2027')
        ->and($plan['payments'][0]['prox_new'])->toBe('26-11-2026')
        ->and($plan['payments'][0]['renewal_new'])->toBe('26-08-2027');
});

it('aplica la corrección en todo lo que depende de la fecha, sin tocar montos', function (): void {
    $correction = CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);
    $affiliation = $this->affiliation->fresh();
    $first = $this->collections->first()->fresh();

    expect($affiliation->activated_at)->toBe('26/08/2026')
        ->and($affiliation->effective_date)->toBe('26/08/2026')
        ->and($first->next_payment_date)->toBe('26/11/2026')
        ->and($first->filter_next_payment_date)->toBe('2026-11-26')
        ->and($first->expiration_date)->toBe('26/11/2026')
        ->and($first->include_date)->toBe('26/08/2026')
        ->and((float) $first->total_amount)->toBe(108639.50)
        ->and($first->status)->toBe('POR PAGAR')
        ->and((string) $this->renovation->fresh()->getRawOriginal('date_renewal'))->toStartWith('2027-08-26')
        ->and($this->payment->fresh()->prox_payment_date)->toBe('26-11-2026')
        ->and($this->payment->fresh()->status)->toBe('APROBADO')
        ->and($correction->status)->toBe(AffiliationCorporateDateCorrection::STATUS_APPLIED)
        ->and(CollectionAdjustment::query()->where('affiliation_code', $affiliation->code)->where('mode', CollectionAdjustment::MODE_EFFECTIVE_DATE)->count())->toBe(3);
});

it('revertir devuelve cada fila exactamente a su foto previa', function (): void {
    $correction = CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);
    $before = $correction->snapshot_before;

    expect(CorporateDateCorrectionRollback::blockers($correction))->toBe([]);

    CorporateDateCorrectionRollback::execute($correction, $this->actor, 'Se corrigió con la fecha equivocada, se deshace');

    $current = TableRowSnapshot::capture(CorporateDateCorrector::COLUMNS, TableRowSnapshot::idsOf(CorporateDateCorrector::COLUMNS, $before));

    expect(TableRowSnapshot::differences(CorporateDateCorrector::COLUMNS, $before, $current))->toBe([])
        ->and($this->affiliation->fresh()->activated_at)->toBe('20/07/2026')
        ->and($this->collections->first()->fresh()->next_payment_date)->toBe('20/10/2026')
        ->and($correction->fresh()->status)->toBe(AffiliationCorporateDateCorrection::STATUS_ROLLED_BACK)
        ->and(CorporateDateCorrectionRollback::latestAppliedFor($this->affiliation))->toBeNull();
});

it('no revierte si un aviso reprogramado ya se pagó', function (): void {
    $correction = CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'La póliza se emitió el 26/08/2026 según contrato', $this->actor);

    DB::table('collections')->where('id', $this->collections->first()->id)->update(['status' => 'PAGADO']);

    expect(implode(' ', CorporateDateCorrectionRollback::blockers($correction)))->toContain('PAGADO');
    expect(fn () => CorporateDateCorrectionRollback::execute($correction, $this->actor, 'Intento con un aviso ya pagado'))
        ->toThrow(RuntimeException::class);
    expect($this->affiliation->fresh()->activated_at)->toBe('26/08/2026');
});

it('bloquea con comprobantes por aprobar, período de renovación o un aviso fuera de calendario', function (): void {
    $proof = $this->payment->replicate();
    $proof->status = 'PENDIENTE';
    $proof->save();

    $this->renovation->forceFill(['status' => 'PERIODO DE RENOVACION'])->save();
    $this->collections->last()->forceFill(['next_payment_date' => '03/05/2027'])->save();

    $blockers = implode(' ', CorporateDateCorrector::plan($this->affiliation, '26/08/2026')['blockers']);

    expect($blockers)
        ->toContain('comprobante(s) de pago por aprobar')
        ->toContain('PERÍODO DE RENOVACIÓN')
        ->toContain('no sigue el calendario');

    expect(fn () => CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'Intento con bloqueos pendientes', $this->actor))
        ->toThrow(RuntimeException::class);
    expect($this->affiliation->fresh()->activated_at)->toBe('20/07/2026');
});

it('bloquea fechas absurdas, la misma fecha y afiliaciones no activas', function (): void {
    expect(implode(' ', CorporateDateCorrector::plan($this->affiliation, '20/07/2026')['blockers']))->toContain('no hay nada que corregir')
        ->and(implode(' ', CorporateDateCorrector::plan($this->affiliation, '20/07/2028')['blockers']))->toContain('más de 12 meses');

    $this->affiliation->forceFill(['status' => 'EXCLUIDA'])->save();

    expect(implode(' ', CorporateDateCorrector::plan($this->affiliation, '26/08/2026')['blockers']))->toContain('ACTIVAS');
});

it('exige motivo y permiso', function (): void {
    expect(fn () => CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'corto', $this->actor))
        ->toThrow(RuntimeException::class, 'mínimo');

    $withoutPermission = User::factory()->make(['departament' => ['ADMINISTRACION']]);

    expect(fn () => CorporateDateCorrector::apply($this->affiliation, '26/08/2026', 'Motivo suficientemente largo', $withoutPermission))
        ->toThrow(RuntimeException::class, 'permiso');
});

it('ubica la cuota en el calendario aun con fin de mes', function (): void {
    $anchor = CarbonImmutable::create(2026, 1, 31);

    expect(CorporateDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 4, 30), 3))->toBe(1)
        ->and(CorporateDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 7, 31), 3))->toBe(2)
        ->and(CorporateDateCorrector::installmentIndex($anchor, CarbonImmutable::create(2026, 5, 3), 3))->toBeNull();
});

it('la vista previa escapa los datos y la acción vive en Administración con permiso propio', function (): void {
    $html = (string) CorporateDateCorrectionPreview::html([
        'blockers' => ['Bloqueo <script>'],
        'warnings' => [],
        'before' => ['activated_at' => '20/07/2026', 'effective_date' => '20/07/2026', 'renewal' => '20/07/2027'],
        'after' => ['date' => '26/08/2026', 'renewal' => '26/08/2027'],
        'frequency' => 'TRIMESTRAL',
        'collections' => [['id' => 1, 'invoice' => '09-00913', 'installment' => '2 de 4', 'old' => '20/10/2026', 'new' => '26/11/2026', 'amount' => 108639.5, 'was_overdue' => true, 'is_overdue' => false]],
        'annual' => [], 'renovations' => [], 'payments' => [],
    ]);

    expect($html)->toContain('deja de estar vencido')->toContain('108.639,50')->not->toContain('<script>');

    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AffiliationCorporates/Pages/ViewAffiliationCorporate.php');
    $registry = (string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/BusinessFilamentActionPermissionRegistry.php');

    expect($page)->toContain('CorporateEffectiveDateActions::correct()')->toContain('CorporateEffectiveDateActions::revert()')
        ->and($registry)->toContain("'corregir-vigencia-afiliacion-corporativa'");
});
