<?php

declare(strict_types=1);

use App\Enums\AdministrationReportType;
use App\Enums\OperationReportFormat;
use App\Filament\Administration\Resources\DroppedAffiliationCollections\Pages\ListDroppedAffiliationCollections;
use App\Models\Affiliation;
use App\Models\Collection;
use App\Models\CollectionAdjustment;
use App\Models\CollectionDropoutReview;
use App\Models\User;
use App\Support\Administration\Reports\CollectionReportBuilder;
use App\Support\Administration\Reports\CollectionReportData;
use App\Support\Administration\Reports\CollectionReportFilters;
use App\Support\Administration\Reports\CollectionReportWriter;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Collections\CollectionReceivableReport;
use App\Support\Collections\DroppedAffiliationCollections;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(Tests\TestCase::class);

/**
 * Cuotas de afiliaciones dadas de baja: anulación automática al dar de baja (solo las que
 * vencen después), restitución al reactivar, decisiones del analista con reversión, el
 * reporte del Centro de reportes y la página. Todo dentro de una transacción revertida.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Filament::setCurrentPanel('administration');
    Auth::setUser(droppedTestUser(['SUPERADMIN', 'ADMINISTRACION']));
});

afterEach(function (): void {
    DB::rollBack();
});

function droppedTestUser(array $departments): User
{
    return User::factory()->make([
        'id' => 1,
        'name' => 'Analista de prueba',
        'email' => 'prueba@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => $departments,
        'is_superAdmin' => in_array('SUPERADMIN', $departments, true),
    ]);
}

/**
 * Una afiliación individual activa con cuotas por pagar vencidas y por vencer.
 */
function droppedTestAffiliation(): Affiliation
{
    $code = DB::table('collections as c')
        ->join('affiliations as a', 'a.code', '=', 'c.affiliation_code')
        ->where('c.status', CollectionAdjuster::STATUS_PENDING)
        ->whereRaw("UPPER(TRIM(a.status)) = 'ACTIVA'")
        ->groupBy('c.affiliation_code')
        ->havingRaw('SUM(c.filter_next_payment_date >= CURDATE()) > 0 AND SUM(c.filter_next_payment_date < CURDATE()) > 0')
        ->value('c.affiliation_code');

    if ($code === null) {
        test()->markTestSkipped('No hay afiliaciones activas con cuotas vencidas y por vencer.');
    }

    return Affiliation::query()->where('code', $code)->firstOrFail();
}

/**
 * @return array{future: list<int>, past: list<int>}
 */
function droppedTestPending(string $code): array
{
    $today = CarbonImmutable::today()->toDateString();
    $pending = Collection::query()->where('affiliation_code', $code)->where('status', CollectionAdjuster::STATUS_PENDING);

    return [
        'future' => (clone $pending)->whereDate('filter_next_payment_date', '>=', $today)->pluck('id')->map(intval(...))->all(),
        'past' => (clone $pending)->whereDate('filter_next_payment_date', '<', $today)->pluck('id')->map(intval(...))->all(),
    ];
}

it('toda cuota está en Gestión de Cobranza o en dadas de baja, nunca en las dos', function (): void {
    $pending = Collection::query()->where('status', CollectionAdjuster::STATUS_PENDING);
    $collectable = CollectionReceivableReport::excludeNonCollectableAffiliations(clone $pending)->count();
    $dropped = DroppedAffiliationCollections::pendingQuery()->count();

    expect($collectable + $dropped)->toBe((clone $pending)->count())
        ->and(CollectionReceivableReport::excludeNonCollectableAffiliations(DroppedAffiliationCollections::pendingQuery())->count())->toBe(0);
});

it('al dar de baja anula solo las cuotas que vencen después, con historial y decisión', function (): void {
    $affiliation = droppedTestAffiliation();
    ['future' => $future, 'past' => $past] = droppedTestPending($affiliation->code);

    $affiliation->update(['status' => 'EXCLUIDO']);

    expect(Collection::query()->whereKey($future)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_CANCELLED])
        ->and(Collection::query()->whereKey($past)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_PENDING])
        ->and(CollectionDropoutReview::query()->active()->whereIn('collection_id', $future)->where('decision', CollectionDropoutReview::DECISION_AUTO_CANCELLED)->count())->toBe(count($future))
        ->and(CollectionAdjustment::query()->whereIn('collection_id', $future)->where('mode', CollectionAdjustment::MODE_DROPOUT)->count())->toBe(count($future))
        ->and(DroppedAffiliationCollections::applyReviewFilter(DroppedAffiliationCollections::reviewQuery(), DroppedAffiliationCollections::REVIEW_UNDECIDED)->whereKey($past)->count())->toBe(count($past));
});

it('al reactivar devuelve a POR PAGAR solo lo que anuló la baja y no lo tocado después', function (): void {
    $affiliation = droppedTestAffiliation();
    ['future' => $future] = droppedTestPending($affiliation->code);
    $affiliation->update(['status' => 'INACTIVO']);

    $touched = $future[0];
    CollectionAdjustment::query()->create([
        'collection_id' => $touched,
        'mode' => CollectionAdjustment::MODE_SINGLE,
        'changes' => [],
        'reason' => 'Ajuste manual posterior de prueba',
    ]);

    $affiliation->update(['status' => 'ACTIVA']);

    $restored = array_values(array_diff($future, [$touched]));

    expect(Collection::query()->whereKey($restored)->pluck('status')->unique()->all())->toBe($restored === [] ? [] : [CollectionAdjuster::STATUS_PENDING])
        ->and(Collection::query()->whereKey($touched)->value('status'))->toBe(CollectionAdjuster::STATUS_CANCELLED);
});

it('no anula sola una cuota que el analista decidió mantener como deuda', function (): void {
    $affiliation = droppedTestAffiliation();
    ['future' => $future] = droppedTestPending($affiliation->code);
    $affiliation->update(['status' => 'EXCLUIDO']);
    $affiliation->update(['status' => 'ACTIVA']);

    $affiliation->update(['status' => 'ANULADA']);
    DroppedAffiliationCollections::revert([$future[0]], 'Se anuló de más, el cliente debe esta cuota', Auth::user());
    DroppedAffiliationCollections::keep([$future[0]], 'Deuda reconocida por el cliente en reunión', Auth::user());

    $affiliation->update(['status' => 'ACTIVA']);
    $affiliation->update(['status' => 'EXCLUIDO']);

    expect(Collection::query()->whereKey($future[0])->value('status'))->toBe(CollectionAdjuster::STATUS_PENDING)
        ->and(Collection::query()->find($future[0])->activeDropoutReview?->decision)->toBe(CollectionDropoutReview::DECISION_KEPT);
});

it('la unificación de grupo familiar no dispara la anulación automática', function (): void {
    $affiliation = droppedTestAffiliation();
    ['future' => $future] = droppedTestPending($affiliation->code);

    DroppedAffiliationCollections::withoutAutomaticCancellation(fn (): bool => $affiliation->update(['status' => 'EXCLUIDO']));

    expect(Collection::query()->whereKey($future)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_PENDING])
        ->and((string) file_get_contents(dirname(__DIR__, 2).'/app/Support/Affiliations/MergeIndividualAffiliationsService.php'))
        ->toContain('DroppedAffiliationCollections::withoutAutomaticCancellation(fn (): bool => $source->save());');
});

it('el analista anula, mantiene y revierte; todo o nada y con nota', function (): void {
    $affiliation = droppedTestAffiliation();
    ['past' => $past] = droppedTestPending($affiliation->code);
    $affiliation->update(['status' => 'EXCLUIDO']);
    $user = Auth::user();

    expect(fn () => DroppedAffiliationCollections::cancel($past, 'corta', $user))->toThrow(InvalidArgumentException::class, 'mínimo');

    $result = DroppedAffiliationCollections::cancel($past, 'Deuda anterior a la baja que no se cobrará', $user);

    expect($result['count'])->toBe(count($past))
        ->and(Collection::query()->whereKey($past)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_CANCELLED])
        ->and(fn () => DroppedAffiliationCollections::keep($past, 'Ya están anuladas, no debería poder', $user))->toThrow(InvalidArgumentException::class, 'ya no está por pagar');

    expect(DroppedAffiliationCollections::revert($past, 'Se anularon por error al revisar el reporte', $user))->toBe(count($past))
        ->and(Collection::query()->whereKey($past)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_PENDING])
        ->and(CollectionDropoutReview::query()->active()->whereIn('collection_id', $past)->count())->toBe(0);
});

it('no deja decidir sobre cuotas de afiliaciones que no están dadas de baja', function (): void {
    $affiliation = droppedTestAffiliation();
    ['past' => $past] = droppedTestPending($affiliation->code);

    expect(fn () => DroppedAffiliationCollections::cancel($past, 'Intento sobre una afiliación activa', Auth::user()))
        ->toThrow(InvalidArgumentException::class, 'ya no está dada de baja');
});

it('sin el permiso de acción no se puede anular', function (): void {
    $affiliation = droppedTestAffiliation();
    ['past' => $past] = droppedTestPending($affiliation->code);
    $affiliation->update(['status' => 'EXCLUIDO']);

    expect(fn () => DroppedAffiliationCollections::cancel($past, 'Analista sin permiso de acción', droppedTestUser(['ADMINISTRACION'])))
        ->toThrow(InvalidArgumentException::class, 'No tiene permiso');
});

it('el reporte lista las cuotas por pagar de afiliaciones dadas de baja y cuadra con la página', function (): void {
    $filters = CollectionReportFilters::fromArray([
        'type' => AdministrationReportType::DroppedAffiliationCollections->value,
        'period' => CollectionReportFilters::PERIOD_ALL,
        'collection_status' => CollectionReportFilters::STATUS_ALL,
    ]);

    $data = CollectionReportBuilder::build($filters);
    $rows = 0;
    $amount = 0.0;
    $totalLine = null;

    foreach ($data->lines as $line) {
        if ($line['kind'] === CollectionReportData::LINE_ROW) {
            $rows++;
            $amount += (float) end($line['values']);
        } elseif ($line['kind'] === CollectionReportData::LINE_TOTAL) {
            $totalLine = $line;
        }
    }

    expect($rows)->toBe(CollectionReportBuilder::count($filters))
        ->and($rows)->toBe(DroppedAffiliationCollections::pendingQuery()->count())
        ->and(round($amount, 2))->toBe(round((float) DroppedAffiliationCollections::pendingQuery()->sum('total_amount'), 2))
        ->and($totalLine['label'] ?? '')->toStartWith('TOTAL GENERAL')
        ->and($data->title)->toBe('Cuotas de afiliaciones dadas de baja');

    $path = tempnam(sys_get_temp_dir(), 'bajas').'.csv';
    CollectionReportWriter::write(CollectionReportBuilder::build($filters), OperationReportFormat::Csv, $path);
    $lines = file($path);
    @unlink($path);

    expect(count($lines))->toBe($rows + 1)
        ->and($lines[0])->toContain('VENCE ANTES O DESPUES DE LA BAJA');
});

it('la página abre, filtra por decidir y muestra las acciones a quien tiene permiso', function (): void {
    Livewire::test(ListDroppedAffiliationCollections::class)
        ->assertSuccessful()
        ->assertSee('Por decidir')
        ->assertTableBulkActionExists('cancel_dropped')
        ->assertTableBulkActionExists('keep_dropped')
        ->assertTableBulkActionExists('revert_dropped');
});

it('anula desde la pantalla con la acción en lote y su nota', function (): void {
    $affiliation = droppedTestAffiliation();
    ['past' => $past] = droppedTestPending($affiliation->code);
    $affiliation->update(['status' => 'EXCLUIDO']);

    Livewire::test(ListDroppedAffiliationCollections::class)
        ->mountTableBulkAction('cancel_dropped', $past)
        ->set('mountedActions.0.data.note', 'Revisado en el reporte: deuda que no se cobrará')
        ->callMountedAction()
        ->assertHasNoErrors();

    expect(Collection::query()->whereKey($past)->pluck('status')->unique()->all())->toBe([CollectionAdjuster::STATUS_CANCELLED])
        ->and(CollectionDropoutReview::query()->active()->whereIn('collection_id', $past)->value('note'))->toBe('Revisado en el reporte: deuda que no se cobrará');
});
