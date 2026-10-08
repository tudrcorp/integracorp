<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\AnnualCollections\AnnualCollectionResource;
use App\Models\Collection;
use App\Support\Collections\CollectionReceivableReport;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

/**
 * Cuota pendiente con vencimiento en 1990: siempre es la «próxima» de su afiliación.
 *
 * @param  array<string, mixed>  $attributes
 */
function excludedReceivable(string $affiliationCode, array $attributes = []): Collection
{
    return Collection::query()->forceCreate([
        'include_date' => '01/01/1990',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'collection_invoice_number' => 'PEST-AC-'.uniqid(),
        'quote_number' => 'PEST-Q',
        'persons' => '1',
        'type' => 'AFILIACION INDIVIDUAL',
        'affiliation_code' => $affiliationCode,
        'affiliate_status' => 'ACTIVA',
        'payment_frequency' => 'TRIMESTRAL',
        'next_payment_date' => '1990-01-01',
        'filter_next_payment_date' => '1990-01-01',
        'total_amount' => 100,
        'status' => CollectionReceivableReport::PENDING_STATUS,
        ...$attributes,
    ]);
}

/**
 * @param  list<Collection>  $rows
 * @return list<int>
 */
function receivableIdsAmong(array $rows): array
{
    return CollectionReceivableReport::scopeNextPendingPerAffiliation(Collection::query())
        ->whereIn('collections.id', array_map(fn (Collection $row): int => $row->id, $rows))
        ->orderBy('collections.id')
        ->pluck('collections.id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all();
}

it('deja fuera las afiliaciones excluidas, inactivas y anuladas según su estatus actual', function (): void {
    DB::table('affiliations')->where('code', 'TDEC-IND-000436')->update(['status' => 'ANULADA']);
    DB::table('affiliations')->where('code', 'TDEC-IND-000183')->update(['status' => ' inactiva ']);
    DB::table('affiliation_corporates')->where('code', 'TDEC-COR-00058')->update(['status' => 'EXCLUIDA']);
    DB::table('affiliation_corporates')->where('code', 'TDEC-COR-00057')->update(['status' => 'ACTIVA']);

    $anulada = excludedReceivable('TDEC-IND-000436');
    $inactiva = excludedReceivable('TDEC-IND-000183');
    $corporativaExcluida = excludedReceivable('TDEC-COR-00058', ['type' => 'AFILIACION CORPORATIVA']);
    $corporativaActiva = excludedReceivable('TDEC-COR-00057', ['type' => 'AFILIACION CORPORATIVA']);

    expect(receivableIdsAmong([$anulada, $inactiva, $corporativaExcluida, $corporativaActiva]))->toBe([$corporativaActiva->id]);
});

it('manda el estatus actual de la afiliación sobre el que quedó copiado en la cuota', function (): void {
    DB::table('affiliations')->where('code', 'TDEC-IND-000436')->update(['status' => 'EXCLUIDO']);
    DB::table('affiliations')->where('code', 'TDEC-IND-000183')->update(['status' => 'ACTIVA']);

    $copiadaActivaPeroExcluida = excludedReceivable('TDEC-IND-000436', ['affiliate_status' => 'ACTIVA']);
    $copiadaExcluidaPeroActiva = excludedReceivable('TDEC-IND-000183', ['affiliate_status' => 'EXCLUIDO']);

    expect(receivableIdsAmong([$copiadaActivaPeroExcluida, $copiadaExcluidaPeroActiva]))->toBe([$copiadaExcluidaPeroActiva->id]);
});

it('si la afiliación ya no existe usa el estatus copiado en la cuota', function (): void {
    $huerfanaActiva = excludedReceivable('PEST-HUERFANA-ACT-'.uniqid(), ['affiliate_status' => 'ACTIVA']);
    $huerfanaExcluida = excludedReceivable('PEST-HUERFANA-EXC-'.uniqid(), ['affiliate_status' => ' Excluida ']);
    $huerfanaSinEstatus = excludedReceivable('PEST-HUERFANA-NUL-'.uniqid(), ['affiliate_status' => null]);

    expect(receivableIdsAmong([$huerfanaActiva, $huerfanaExcluida, $huerfanaSinEstatus]))
        ->toBe([$huerfanaActiva->id, $huerfanaSinEstatus->id]);
});

it('las pre-aprobadas siguen en el reporte', function (): void {
    DB::table('affiliations')->where('code', 'TDEC-IND-000436')->update(['status' => 'PRE-APROBADA']);

    $preAprobada = excludedReceivable('TDEC-IND-000436');

    expect(receivableIdsAmong([$preAprobada]))->toBe([$preAprobada->id]);
});

it('la tabla, el CSV y el resumen comparten la misma exclusión', function (): void {
    DB::table('affiliations')->where('code', 'TDEC-IND-000436')->update(['status' => 'EXCLUIDO']);
    DB::table('affiliations')->where('code', 'TDEC-IND-000183')->update(['status' => 'ACTIVA']);

    $excluida = excludedReceivable('TDEC-IND-000436', ['total_amount' => 5000]);
    $activa = excludedReceivable('TDEC-IND-000183', ['total_amount' => 70]);
    $ids = [$excluida->id, $activa->id];

    $tabla = AnnualCollectionResource::getEloquentQuery()->whereIn('collections.id', $ids)->pluck('collections.id')->all();
    $resumen = CollectionReceivableReport::summary(AnnualCollectionResource::getEloquentQuery()->whereIn('collections.id', $ids));

    expect(array_map('intval', $tabla))->toBe([$activa->id])
        ->and($resumen['rows_count'])->toBe(1);
});

it('el filtro de estatus ya no ofrece Excluido', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/AnnualCollections/Tables/AnnualCollectionsTable.php');

    expect($source)->not->toContain("'EXCLUIDO' => 'Excluido',")
        ->and(CollectionReceivableReport::EXCLUDED_AFFILIATION_STATUSES)
        ->toBe(['EXCLUIDO', 'EXCLUIDA', 'INACTIVO', 'INACTIVA', 'ANULADO', 'ANULADA']);
});

it('collections tiene los índices del reporte de cuentas por cobrar', function (): void {
    expect(Illuminate\Support\Facades\Schema::hasIndex('collections', ['affiliation_code', 'status', 'filter_next_payment_date']))->toBeTrue()
        ->and(Illuminate\Support\Facades\Schema::hasIndex('collections', ['status', 'filter_next_payment_date']))->toBeTrue()
        ->and(Illuminate\Support\Facades\Schema::hasIndex('collections', ['sale_id']))->toBeTrue();

    $plan = collect(DB::select(
        'EXPLAIN SELECT n.id FROM collections n WHERE n.affiliation_code = ? AND n.status = ? ORDER BY n.filter_next_payment_date ASC, n.id ASC LIMIT 1',
        ['TDEC-IND-000436', CollectionReceivableReport::PENDING_STATUS],
    ))->first();

    expect($plan->key)->toBe('collections_affiliation_status_due_index');
});
