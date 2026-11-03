<?php

declare(strict_types=1);

use App\Models\Collection;
use App\Models\CollectionObservation;
use App\Support\Exports\CollectionReceivableXlsxExportService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

function receivableXlsxRecord(array $attributes = []): Collection
{
    $record = new Collection;
    $record->forceFill(array_merge([
        'affiliation_code' => 'TDEC-IND-TEST-XLSX',
        'affiliate_full_name' => 'PRUEBA EXCEL',
        'persons' => 2,
        'follow_up_status' => null,
    ], $attributes));

    foreach (['plan', 'agent', 'agencyByCode', 'affiliationByCode', 'affiliationCorporateByCode'] as $relation) {
        $record->setRelation($relation, null);
    }

    return $record;
}

/**
 * @return list<list<mixed>>
 */
function readReceivableXlsx(string $path): array
{
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(fn ($cell): mixed => $cell->getValue(), $row->getCells());
        }

        break;
    }

    $reader->close();

    return $rows;
}

it('sigue el orden de columnas del modelo, sin periodos de pago y con seguimiento y observaciones', function (): void {
    $headers = CollectionReceivableXlsxExportService::headers();

    expect($headers)->toHaveCount(23)
        ->and($headers)->not->toContain('PERIODOS DE PAGO')
        ->and(array_slice($headers, 15, 4))->toBe(['FECHA DE VENCIMIENTO', 'ESTATUS DE COBRO', 'DIAS', 'ESTATUS DEL AFILIADO O TITULAR'])
        ->and(array_slice($headers, 19))->toBe(['SEGUIMIENTO', 'SEGUIMIENTO', 'SEGUIMIENTO', 'OBSERVACIONES'])
        ->and(array_map(fn ($state): string => $state->value, CollectionReceivableXlsxExportService::followUpColumns()))
        ->toBe(['AVISO ENVIADO', 'AVISO REMITIDO', 'AVISO EN REVISION']);
});

it('marca con check cada estado de gestión de la cuota', function (?string $stored, array $expected): void {
    $values = CollectionReceivableXlsxExportService::rowValues(receivableXlsxRecord(['follow_up_status' => $stored]), null);

    expect($values['follow_up'])->toBe($expected);
})->with([
    'sin estado' => [null, [false, false, false]],
    'enviado' => ['AVISO ENVIADO', [true, false, false]],
    'enviado y remitido' => ['AVISO ENVIADO,AVISO REMITIDO', [true, true, false]],
    'en revisión' => ['AVISO EN REVISION', [false, false, true]],
    'retirado no marca' => ['SEGUIMIENTO', [false, false, false]],
]);

it('pone la última observación con su fecha', function (): void {
    $observation = new CollectionObservation;
    $observation->forceFill(['observation' => '  Cliente pide prórroga  ', 'created_at' => Carbon::create(2026, 11, 2, 10)]);

    $values = CollectionReceivableXlsxExportService::rowValues(receivableXlsxRecord(), $observation);

    expect($values['observation'])->toBe('02/11/2026: Cliente pide prórroga')
        ->and(CollectionReceivableXlsxExportService::rowValues(receivableXlsxRecord(), null)['observation'])->toBe('');
});

it('precarga solo la observación más reciente de cada afiliación', function (): void {
    foreach (['Primera nota', 'Nota más reciente'] as $text) {
        CollectionObservation::query()->create([
            'affiliation_code' => 'TDEC-IND-TEST-XLSX',
            'affiliation_type' => 'individual',
            'observation' => $text,
            'created_by_name' => 'Prueba',
        ]);
    }

    $latest = CollectionReceivableXlsxExportService::latestObservations(collect([
        receivableXlsxRecord(),
        receivableXlsxRecord(['affiliation_code' => 'SIN-NOTAS']),
    ]));

    expect($latest)->toHaveCount(1)
        ->and($latest->get('TDEC-IND-TEST-XLSX')?->observation)->toBe('Nota más reciente');
});

it('no consulta observaciones si el lote no trae códigos', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(CollectionReceivableXlsxExportService::latestObservations(collect([receivableXlsxRecord(['affiliation_code' => ''])])))->toBeEmpty()
        ->and(DB::getQueryLog())->toBeEmpty();
});

it('genera un Excel con encabezado en dos filas y totales aunque no haya registros', function (): void {
    $path = app(CollectionReceivableXlsxExportService::class)->writeToFile(Collection::query()->whereRaw('1 = 0'));

    try {
        $rows = readReceivableXlsx($path);
    } finally {
        @unlink($path);
    }

    expect($rows)->toHaveCount(3)
        ->and($rows[0][0])->toBe('FECHA DE EMISION')
        ->and(array_slice($rows[1], 19, 3))->toBe(['AVISO ENVIADO', 'AVISO REMITIDO', 'AVISO EN REVISION'])
        ->and($rows[2][0])->toBe('TOTAL')
        ->and($rows[2][1])->toBe('0 registros')
        ->and($rows[2][8])->toBe(0)
        ->and((float) $rows[2][11])->toBe(0.0);
});

it('escribe montos y fechas como valores reales de Excel y suma los totales', function (): void {
    $row = Collection::query()->whereNotNull('affiliation_code')->first();

    if ($row === null) {
        $this->markTestSkipped('No hay cuotas en la base para armar el reporte.');
    }

    $query = Collection::query()->whereKey($row->getKey());
    $values = CollectionReceivableXlsxExportService::rowValues($query->clone()->with(\App\Support\Collections\CollectionReceivableReport::eagerLoads())->first(), null);
    $path = app(CollectionReceivableXlsxExportService::class)->writeToFile($query);

    try {
        $rows = readReceivableXlsx($path);
    } finally {
        @unlink($path);
    }

    $data = $rows[2];
    $total = $rows[3];

    expect($data[11])->toEqual($values['annual_fee'] ?? '')
        ->and($total[11])->toEqual(round((float) ($values['annual_fee'] ?? 0), 2))
        ->and($total[1])->toBe('1 registro');

    if ($values['due_date'] !== null) {
        expect($data[15])->toBeInstanceOf(DateTimeInterface::class)
            ->and($data[15]->format('d/m/Y'))->toBe($values['due_date']->format('d/m/Y'));
    }
});

it('ofrece Excel y CSV en la tabla y en la selección', function (): void {
    $source = file_get_contents(app_path('Filament/Administration/Resources/AnnualCollections/Tables/AnnualCollectionsTable.php'));

    expect($source)
        ->toContain('self::exportExcelAction(),')
        ->toContain('self::exportAction(),')
        ->toContain('self::exportSelectedExcelAction(),')
        ->toContain('self::exportSelectedAction(),')
        ->toContain("->label('Descargar Excel')")
        ->toContain("->label('Exportar Excel')");
});
