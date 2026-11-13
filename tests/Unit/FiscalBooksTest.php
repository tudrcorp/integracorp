<?php

declare(strict_types=1);

use App\Console\Commands\ImportLegacyFiscalBooks;
use App\Enums\FiscalBook;
use App\Models\FiscalBookEntry;
use App\Models\FiscalBookPeriodEvent;
use App\Models\OperationAccountsPayable;
use App\Models\Sale;
use App\Models\User;
use App\Support\Administration\FiscalBooks\FiscalAmount;
use App\Support\Administration\FiscalBooks\FiscalBookEntryWriter;
use App\Support\Administration\FiscalBooks\FiscalBookExcelWriter;
use App\Support\Administration\FiscalBooks\FiscalBookLedger;
use App\Support\Administration\FiscalBooks\FiscalBookPdf;
use App\Support\Administration\FiscalBooks\FiscalBookPeriods;
use App\Support\Administration\FiscalBooks\FiscalPurchasesPayableImport;
use App\Support\Administration\FiscalBooks\FiscalSalesBookSync;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use OpenSpout\Reader\XLSX\Reader;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    DB::beginTransaction();
    FiscalBookLedger::flush();
});

afterEach(fn () => DB::rollBack());

/**
 * Mes de prueba lejos de los datos reales (el libro acepta hasta un año adelante).
 */
function fiscalTestPeriod(): CarbonImmutable
{
    return CarbonImmutable::parse('2027-06-01');
}

function fiscalTestUser(int $id = 990101): User
{
    return User::factory()->make(['id' => $id, 'name' => 'Analista Fiscal '.$id]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fiscalSale(array $overrides = []): FiscalBookEntry
{
    return FiscalBookEntryWriter::create(FiscalBook::Sales, [
        'period' => '2027-06',
        'document_date' => '2027-06-10',
        'document_number' => 'T-'.fake()->unique()->numberBetween(100000, 999999),
        'counterparty_name' => 'Cliente de prueba',
        'counterparty_rif' => 'v 12345678',
        'exempt_amount' => '1000.00',
        ...$overrides,
    ], fiscalTestUser());
}

/**
 * @param  array<string, mixed>  $overrides
 */
function fiscalPurchase(array $overrides = []): FiscalBookEntry
{
    return FiscalBookEntryWriter::create(FiscalBook::Purchases, [
        'period' => '2027-06',
        'document_date' => '2027-05-28',
        'document_number' => 'C-'.fake()->unique()->numberBetween(100000, 999999),
        'counterparty_name' => 'Proveedor de prueba',
        'counterparty_rif' => 'J305657386',
        'exempt_amount' => '500.00',
        ...$overrides,
    ], fiscalTestUser());
}

it('opera los montos sin errores de punto flotante', function (): void {
    expect(FiscalAmount::add('0.1', '0.2'))->toBe('0.30')
        ->and(FiscalAmount::add(...array_fill(0, 1000, '0.01')))->toBe('10.00')
        ->and(FiscalAmount::of('2.675'))->toBe('2.68')
        ->and(FiscalAmount::of('-2.675'))->toBe('-2.68')
        ->and(FiscalAmount::of('-0.001'))->toBe('0.00')
        ->and(FiscalAmount::of(194671.9827586207))->toBe('194671.98')
        ->and(FiscalAmount::of('1.234.567,89'))->toBe('1234567.89')
        ->and(FiscalAmount::format('-1234567.5'))->toBe('-1.234.567,50')
        ->and(FiscalAmount::format('0'))->toBe('0,00')
        ->and(FiscalAmount::taxFromBase('110593.75', '16'))->toBe('17695.00')
        ->and(FiscalAmount::convert('9.77', '233.55786543'))->toBe('2281.86')
        ->and(FiscalAmount::precise('233.557865431', 8))->toBe('233.55786543');
});

it('separa el IVA de un monto gravado sin perder el céntimo', function (string $withTax, string $base, string $tax): void {
    $split = FiscalAmount::splitTaxIncluded($withTax, '16');

    expect($split)->toBe(['base' => $base, 'tax' => $tax])
        ->and(FiscalAmount::add($split['base'], $split['tax']))->toBe(FiscalAmount::of($withTax));
})->with([
    'Daka (libro de octubre)' => ['225819.50', '194671.98', '31147.52'],
    'Mapfre (libro de octubre)' => ['128288.75', '110593.75', '17695.00'],
    'un céntimo' => ['0.01', '0.01', '0.00'],
]);

it('rechaza montos que no son números', function (): void {
    expect(fn () => FiscalAmount::of('12abc'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => FiscalAmount::of(NAN))->toThrow(InvalidArgumentException::class);
});

it('calcula IVA y total al registrar y normaliza el RIF', function (): void {
    $entry = fiscalSale([
        'exempt_amount' => '1000.10',
        'taxable_base' => '110593.75',
        'counterparty_rif' => 'j-502344691',
    ]);

    expect($entry->tax_amount)->toBe('17695.00')
        ->and($entry->total_amount)->toBe('129288.85')
        ->and($entry->counterparty_rif)->toBe('J-502344691')
        ->and($entry->counterparty_name)->toBe('CLIENTE DE PRUEBA')
        ->and($entry->source)->toBe(FiscalBookEntry::SOURCE_MANUAL);
});

it('acepta el IVA impreso con redondeo del emisor pero no uno que no corresponde', function (): void {
    $entry = fiscalPurchase(['exempt_amount' => '0', 'taxable_base' => '100.00', 'tax_amount' => '16.03']);

    expect($entry->tax_amount)->toBe('16.03')
        ->and($entry->total_amount)->toBe('116.03')
        ->and(fn () => fiscalPurchase(['exempt_amount' => '0', 'taxable_base' => '100.00', 'tax_amount' => '17.00']))
        ->toThrow(InvalidArgumentException::class, 'no corresponde a la base');
});

it('resta las notas de crédito y exige la factura afectada', function (): void {
    expect(fn () => fiscalSale(['document_type' => FiscalBookEntry::TYPE_CREDIT_NOTE]))
        ->toThrow(InvalidArgumentException::class, 'factura afectada');

    $note = fiscalSale([
        'document_type' => FiscalBookEntry::TYPE_CREDIT_NOTE,
        'affected_document_number' => '01011',
        'exempt_amount' => '250.00',
    ]);

    expect($note->total_amount)->toBe('-250.00')
        ->and($note->exempt_amount)->toBe('-250.00')
        ->and(FiscalBookLedger::totals(FiscalBook::Sales, fiscalTestPeriod())['total'])->toBe('-250.00');
});

it('valida fecha, montos y retención', function (array $overrides, string $message): void {
    expect(fn () => fiscalSale($overrides))->toThrow(InvalidArgumentException::class, $message);
})->with([
    'venta fuera del mes' => [['document_date' => '2027-07-01'], 'debe estar dentro de'],
    'sin montos' => [['exempt_amount' => '0'], 'no tiene montos'],
    'monto negativo' => [['exempt_amount' => '-5'], 'en positivo'],
    'retención sin comprobante' => [['taxable_base' => '100', 'withholding_amount' => '12'], 'número de comprobante'],
    'retención mayor que el IVA' => [['taxable_base' => '100', 'withholding_amount' => '20', 'withholding_number' => 'R-1', 'withholding_date' => '2027-06-11'], 'mayor que el IVA'],
    'sin número' => [['document_number' => '  '], 'número del documento'],
]);

it('no duplica facturas: ventas por número sin importar los ceros, compras por número y proveedor', function (): void {
    fiscalSale(['document_number' => '0099001']);

    expect(fn () => fiscalSale(['document_number' => '99001']))
        ->toThrow(InvalidArgumentException::class, 'ya está en el libro de ventas');

    fiscalPurchase(['document_number' => '555777', 'counterparty_rif' => 'J-1']);
    $otherSupplier = fiscalPurchase(['document_number' => '555777', 'counterparty_rif' => 'J-2']);

    expect($otherSupplier->exists)->toBeTrue()
        ->and(fn () => fiscalPurchase(['document_number' => '555777', 'counterparty_rif' => 'j 1']))
        ->toThrow(InvalidArgumentException::class, 'ya está en el libro de compras');
});

it('una compra puede ir en el libro del mes en que se recibe, nunca en uno anterior a su fecha', function (): void {
    expect(fiscalPurchase(['document_date' => '2027-04-15'])->period->format('Y-m'))->toBe('2027-06')
        ->and(fn () => fiscalPurchase(['document_date' => '2027-07-02']))->toThrow(InvalidArgumentException::class, 'después de');
});

it('anular deja el número con montos en cero; quitar solo aplica a cargas manuales', function (): void {
    $entry = fiscalSale(['taxable_base' => '100']);

    expect(fn () => FiscalBookEntryWriter::annul($entry, 'corta', fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'mínimo');

    $annulled = FiscalBookEntryWriter::annul($entry, 'Factura emitida con datos errados', fiscalTestUser());

    expect($annulled->status)->toBe(FiscalBookEntry::STATUS_ANNULLED)
        ->and($annulled->total_amount)->toBe('0.00')
        ->and($annulled->tax_amount)->toBe('0.00')
        ->and($annulled->document_number)->toBe($entry->document_number)
        ->and(fn () => FiscalBookEntryWriter::update($annulled, ['exempt_amount' => '5'], fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'anulada');

    $manual = fiscalPurchase();
    FiscalBookEntryWriter::delete($manual, fiscalTestUser());

    expect(FiscalBookEntry::query()->find($manual->getKey()))->toBeNull()
        ->and(FiscalBookEntry::withTrashed()->find($manual->getKey())->deleted_by_name)->toBe('Analista Fiscal 990101');

    $fromSales = fiscalSale();
    $fromSales->forceFill(['source' => FiscalBookEntry::SOURCE_SALE])->saveQuietly();

    expect(fn () => FiscalBookEntryWriter::delete($fromSales, fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'anúlela');
});

it('un mes cerrado no acepta cambios y reabrirlo exige razón y queda en la bitácora', function (): void {
    $entry = fiscalSale();
    $period = fiscalTestPeriod();

    expect(fn () => FiscalBookPeriods::close(FiscalBook::Sales, $period, fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'aún no empieza');

    $this->travelTo(CarbonImmutable::parse('2027-07-02 09:00'));

    $closed = FiscalBookPeriods::close(FiscalBook::Sales, $period, fiscalTestUser());

    expect($closed->isClosed())->toBeTrue()
        ->and($closed->totals['total'])->toBe('1000.00')
        ->and(fn () => fiscalSale())->toThrow(InvalidArgumentException::class, 'está cerrado')
        ->and(fn () => FiscalBookEntryWriter::update($entry, ['exempt_amount' => '5'], fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'está cerrado')
        ->and(fn () => FiscalBookEntryWriter::annul($entry, 'Razón suficientemente larga', fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'está cerrado')
        ->and(fn () => FiscalBookPeriods::close(FiscalBook::Sales, $period, fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'ya está cerrado')
        ->and(fn () => FiscalBookPeriods::reopen(FiscalBook::Sales, $period, 'corta', fiscalTestUser()))->toThrow(InvalidArgumentException::class, 'mínimo');

    $reopened = FiscalBookPeriods::reopen(FiscalBook::Sales, $period, 'Corrección de la factura de Mapfre', fiscalTestUser());

    expect($reopened->isClosed())->toBeFalse()
        ->and($reopened->reopen_reason)->toBe('Corrección de la factura de Mapfre')
        ->and(FiscalBookPeriodEvent::query()->where('fiscal_book_period_id', $reopened->getKey())->pluck('action')->all())
        ->toBe([FiscalBookPeriodEvent::ACTION_CLOSED, FiscalBookPeriodEvent::ACTION_REOPENED])
        ->and(fiscalSale()->exists)->toBeTrue();
});

it('no deja cerrar un mes vacío', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-07-02 09:00'));

    expect(fn () => FiscalBookPeriods::close(FiscalBook::Purchases, fiscalTestPeriod(), fiscalTestUser()))
        ->toThrow(InvalidArgumentException::class, 'no tiene documentos');
});

it('arma totales y resumen fiscal separando contribuyentes, alícuotas y retenciones', function (): void {
    fiscalPurchase(['exempt_amount' => '3770665.05']);
    fiscalPurchase(['exempt_amount' => '0', 'taxable_base' => '194671.98', 'tax_amount' => '31147.52']);
    fiscalPurchase(['exempt_amount' => '0', 'taxable_base' => '1000', 'tax_rate' => '8', 'taxpayer_type' => FiscalBookEntry::NON_TAXPAYER]);
    fiscalPurchase(['exempt_amount' => '0', 'taxable_base' => '200', 'withholding_amount' => '24', 'withholding_number' => 'RET-1', 'withholding_date' => '2027-06-05']);

    $totals = FiscalBookLedger::totals(FiscalBook::Purchases, fiscalTestPeriod());

    expect($totals['documents'])->toBe(4)
        ->and($totals['exempt'])->toBe('3770665.05')
        ->and($totals['general_base'])->toBe('194871.98')
        ->and($totals['general_tax'])->toBe('31179.52')
        ->and($totals['reduced_base'])->toBe('1000.00')
        ->and($totals['reduced_tax'])->toBe('80.00')
        ->and($totals['non_taxpayer_base'])->toBe('1000.00')
        ->and($totals['taxpayer_base'])->toBe('194871.98')
        ->and($totals['withholding'])->toBe('24.00')
        ->and($totals['total'])->toBe(FiscalAmount::add('3770665.05', '225819.50', '1080', '232'))
        ->and($totals['determination_base'])->toBe(FiscalAmount::add('3770665.05', '194871.98', '1000'));

    $lines = FiscalBookLedger::summaryLines(FiscalBook::Purchases, $totals);

    expect(array_column($lines, 'item'))->toBe(['40', '41', '42 / 43', '442 / 452', '443 / 453', '46'])
        ->and(end($lines)['tax'])->toBe('31259.52')
        ->and(end($lines)['withholding'])->toBe('24.00');
});

it('numera las operaciones en el orden del libro', function (): void {
    $late = fiscalSale(['document_date' => '2027-06-20']);
    $early = fiscalSale(['document_date' => '2027-06-02']);
    $legacy = FiscalBookEntryWriter::create(FiscalBook::Sales, [
        'period' => '2027-06', 'document_date' => '2027-06-25', 'document_number' => 'L-1',
        'counterparty_name' => 'Histórica', 'exempt_amount' => '10',
    ], null, ['source' => FiscalBookEntry::SOURCE_LEGACY, 'position' => 1]);

    expect(FiscalBookLedger::operationNumbers(FiscalBook::Sales, fiscalTestPeriod()))
        ->toBe([(int) $legacy->getKey() => 1, (int) $early->getKey() => 2, (int) $late->getKey() => 3]);
});

it('lleva al libro la factura emitida en Ventas, sin duplicarla y respetando el ajuste manual', function (): void {
    $sale = new Sale;
    $sale->forceFill([
        'id' => 999999901,
        'invoice_generated' => '091001',
        'affiliate_full_name' => 'TITULAR',
        'invoice_snapshot' => [
            'date' => '15/06/2027',
            'total_ves' => 98705.08,
            'billing_party' => ['full_name_ti' => 'Inversiones Chesterpack, C.A.', 'ci_rif_ti' => '502344691'],
        ],
    ]);

    $first = FiscalSalesBookSync::syncSale($sale, fiscalTestUser());

    expect($first['result'])->toBe('creada')
        ->and($first['entry']->exempt_amount)->toBe('98705.08')
        ->and($first['entry']->counterparty_rif)->toBe('J-502344691')
        ->and($first['entry']->source)->toBe(FiscalBookEntry::SOURCE_SALE);

    $sale->invoice_snapshot = [...$sale->invoice_snapshot, 'total_ves' => 98800.00];

    expect(FiscalSalesBookSync::syncSale($sale, fiscalTestUser())['result'])->toBe('actualizada')
        ->and($first['entry']->refresh()->total_amount)->toBe('98800.00');

    FiscalBookEntryWriter::update($first['entry'], ['exempt_amount' => '0', 'taxable_base' => '85172.41'], fiscalTestUser());
    $sale->invoice_snapshot = [...$sale->invoice_snapshot, 'total_ves' => 1.00];

    expect(FiscalSalesBookSync::syncSale($sale, fiscalTestUser())['result'])->toBe('existente')
        ->and($first['entry']->refresh()->taxable_base)->toBe('85172.41')
        ->and($first['entry']->manually_adjusted)->toBeTrue()
        ->and(FiscalBookEntry::query()->where('document_number_key', '91001')->count())->toBe(1);
});

it('no trae ventas sin monto en bolívares ni las mete en un mes cerrado', function (): void {
    $withoutAmount = (new Sale)->forceFill(['id' => 999999902, 'invoice_generated' => '091002', 'invoice_snapshot' => ['date' => '15/06/2027']]);

    expect(FiscalSalesBookSync::syncSale($withoutAmount, null)['result'])->toBe('sin_monto');

    fiscalSale();
    $this->travelTo(CarbonImmutable::parse('2027-07-02 09:00'));
    FiscalBookPeriods::close(FiscalBook::Sales, fiscalTestPeriod(), fiscalTestUser());

    $late = (new Sale)->forceFill(['id' => 999999903, 'invoice_generated' => '091003', 'invoice_snapshot' => ['date' => '20/06/2027', 'total_ves' => 50]]);

    expect(FiscalSalesBookSync::syncSale($late, null)['result'])->toBe('mes_cerrado')
        ->and(FiscalSalesBookSync::syncSaleSafely($late, null))->toBeNull();
});

it('deduce la letra del RIF de la factura: nueve dígitos es jurídico', function (?string $raw, ?string $expected): void {
    expect(FiscalSalesBookSync::rifFor($raw))->toBe($expected);
})->with([
    'cédula' => ['6431109', 'V-6431109'],
    'jurídico' => ['502344691', 'J-502344691'],
    'ya trae letra' => ['E-81234567', 'E-81234567'],
    'vacío' => ['', null],
]);

it('importa de cuentas por pagar convirtiendo con la tasa de cada fecha, todo o nada', function (): void {
    $payable = OperationAccountsPayable::query()->create([
        'invoice_date' => '2027-06-03',
        'invoice_registration_date' => '2027-06-04',
        'invoice_number' => 'CXP-77001',
        'invoice_control_number' => '00-77001',
        'supplier_name' => 'Clínica de prueba C.A.',
        'supplier_rif' => 'J305657386',
        'invoice_amount' => '9.77',
        'invoice_currency' => 'USD',
        'created_by' => 'test',
    ]);

    expect(collect(FiscalPurchasesPayableImport::pending(fiscalTestPeriod()))->pluck('id'))->toContain($payable->getKey())
        ->and(fn () => FiscalPurchasesPayableImport::import(fiscalTestPeriod(), [$payable->getKey()], [], fiscalTestUser()))
        ->toThrow(InvalidArgumentException::class, 'tasa BCV del 03/06/2027');

    $count = FiscalPurchasesPayableImport::import(fiscalTestPeriod(), [$payable->getKey()], ['2027-06-03' => '233.55786543'], fiscalTestUser());
    $entry = FiscalBookEntry::query()->where('source', FiscalBookEntry::SOURCE_PAYABLE)->where('source_id', $payable->getKey())->firstOrFail();

    expect($count)->toBe(1)
        ->and($entry->exempt_amount)->toBe('2281.86')
        ->and($entry->exchange_rate)->toBe('233.55786543')
        ->and($entry->source_amount_usd)->toBe('9.7700')
        ->and($entry->counterparty_rif)->toBe('J-305657386')
        ->and(collect(FiscalPurchasesPayableImport::pending(fiscalTestPeriod()))->pluck('id'))->not->toContain($payable->getKey())
        ->and(fn () => FiscalPurchasesPayableImport::import(fiscalTestPeriod(), [$payable->getKey()], ['2027-06-03' => '1'], fiscalTestUser()))
        ->toThrow(InvalidArgumentException::class, 'ya están en el libro');
});

it('el PDF arrastra «VAN / VIENEN» exactos por página', function (): void {
    $rows = array_map(fn (int $i): array => [
        'total' => '0.10', 'exempt' => '0.10', 'export' => '0.00', 'taxpayer_base' => '0.00', 'taxpayer_tax' => '0.00',
        'non_taxpayer_base' => '0.00', 'non_taxpayer_tax' => '0.00', 'withholding' => '0.00',
    ], range(1, FiscalBookPdf::ROWS_PER_PAGE * 2 + 3));

    $pages = FiscalBookPdf::pages($rows);

    expect($pages)->toHaveCount(3)
        ->and($pages[0]['carried'])->toBeNull()
        ->and($pages[0]['carry']['total'])->toBe('3.30')
        ->and($pages[1]['carried']['total'])->toBe('3.30')
        ->and($pages[2]['carry']['total'])->toBe('6.90')
        ->and($pages[2]['last'])->toBeTrue()
        ->and(FiscalBookPdf::pages([]))->toHaveCount(1);
});

it('el Excel trae el encabezado fiscal, cada documento y totales que cuadran con el libro', function (): void {
    fiscalSale(['document_number' => 'X-1', 'exempt_amount' => '98705.08']);
    fiscalSale(['document_number' => 'X-2', 'exempt_amount' => '0', 'taxable_base' => '110593.75']);

    $path = sys_get_temp_dir().'/libro-ventas-test-'.uniqid().'.xlsx';
    FiscalBookExcelWriter::write(FiscalBook::Sales, fiscalTestPeriod(), $path);

    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }

    $reader->close();
    @unlink($path);

    $flat = json_encode($rows, JSON_UNESCAPED_UNICODE);
    $totalRow = collect($rows)->first(fn (array $row): bool => str_starts_with((string) ($row[6] ?? ''), 'TOTAL DEL MES'));

    expect($flat)->toContain('LIBRO DE VENTAS CORRESPONDIENTE AL MES DE JUNIO 2027')
        ->toContain('J-503583681')
        ->toContain('X-1')
        ->toContain('Total ventas internas gravadas por alícuota general 16 %')
        ->and($totalRow[9])->toBe(226993.83)
        ->and($totalRow[12])->toBe(110593.75)
        ->and((float) $totalRow[13])->toBe(17695.0);
});

it('el PDF se genera por la vía real', function (): void {
    fiscalSale(['document_number' => 'P-1']);
    $path = sys_get_temp_dir().'/libro-ventas-test-'.uniqid().'.pdf';

    FiscalBookPdf::write(FiscalBook::Sales, fiscalTestPeriod(), $path);

    expect(file_get_contents($path, length: 5))->toBe('%PDF-')
        ->and(filesize($path))->toBeGreaterThan(2000);

    @unlink($path);
});

it('la descarga exige sesión y el permiso del libro', function (): void {
    $this->get(route('administration.fiscal-books.download', ['book' => 'ventas', 'period' => '2027-06', 'format' => 'xlsx']))
        ->assertRedirect();

    $outsider = User::factory()->make(['id' => 990102, 'email' => 'externo@gmail.com', 'departament' => ['ADMINISTRACION'], 'status' => 'ACTIVO']);

    $this->actingAs($outsider)
        ->get(route('administration.fiscal-books.download', ['book' => 'ventas', 'period' => '2027-06', 'format' => 'xlsx']))
        ->assertForbidden();

    $analyst = User::factory()->make(['id' => 990103, 'email' => 'analista@tudrencasa.com', 'departament' => ['ADMINISTRACION', 'SUPERADMIN'], 'status' => 'ACTIVO']);

    $this->actingAs($analyst)
        ->get(route('administration.fiscal-books.download', ['book' => 'compras', 'period' => '2027-06', 'format' => 'xlsx']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('importa el histórico del Excel tal como se declaró', function (): void {
    [$input] = ImportLegacyFiscalBooks::input(FiscalBook::Purchases, fiscalTestPeriod(), [
        'date' => '2027-05-27', 'type' => 'FACTURA', 'number' => '34003', 'control' => 'Z7C7028559', 'name' => 'TIENDA DAKA CA',
        'rif' => 'J-408488906', 'contributor' => 'C', 'total' => '225819.5', 'exempt' => '0', 'export' => '0',
        'base' => '194671.9827586207', 'tax' => '31147.51724137931', 'nc_base' => '0', 'rate' => '0.16',
    ]);

    expect($input['taxable_base'])->toBe('194671.98')
        ->and($input['tax_amount'])->toBe('31147.52')
        ->and($input['tax_rate'])->toBe('16.00');

    [$annulled] = ImportLegacyFiscalBooks::input(FiscalBook::Sales, fiscalTestPeriod(), ['name' => 'ANULADO', 'total' => '0']);
    [$withoutNumber, $notes] = ImportLegacyFiscalBooks::input(FiscalBook::Purchases, fiscalTestPeriod(), [
        'date' => '2027-06-01', 'number' => '', 'name' => 'CORPORACION VMC C A', 'total' => '921240.37', 'exempt' => '921240.37',
    ]);

    expect($annulled)->toBeNull()
        ->and($withoutNumber['document_number'])->toBe('S/N')
        ->and($notes)->toContain('sin número de factura en el Excel: se importó como «S/N»');
});

it('la emisión de facturas en Ventas alimenta el libro y el menú tiene sus permisos', function (): void {
    $service = file_get_contents(app_path('Services/SaleInvoicePdfService.php'));
    $registry = file_get_contents(app_path('Support/Filament/DepartmentNavigationPermissionRegistry.php'));

    expect(substr_count($service, 'FiscalSalesBookSync::syncSaleSafely($sale, self::fiscalBookUser());'))->toBe(2)
        ->and($registry)->toContain("FiscalSalesBookResource::class => ['libro-de-ventas']")
        ->toContain("FiscalPurchasesBookResource::class => ['libro-de-compras']")
        ->and(App\Support\Filament\BusinessFilamentActionPermissionRegistry::all())->toHaveKey('cerrar-reabrir-libros-fiscales');
});

it('las pantallas muestran el mes elegido y un mes cerrado no ofrece cambios', function (): void {
    Filament\Facades\Filament::setCurrentPanel('administration');

    $analyst = User::factory()->make([
        'id' => 990104,
        'email' => 'analista@tudrencasa.com',
        'departament' => ['ADMINISTRACION', 'SUPERADMIN'],
        'status' => 'ACTIVO',
    ]);

    fiscalSale(['document_number' => 'UI-1', 'counterparty_name' => 'Cliente visible en pantalla']);

    $open = Livewire\Livewire::withQueryParams(['mes' => '2027-06'])
        ->actingAs($analyst)
        ->test(App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalSalesBook::class);

    $open->assertSuccessful()
        ->assertSee('CLIENTE VISIBLE EN PANTALLA')
        ->assertSee('Mes abierto')
        ->assertSee('Resumen de débitos fiscales')
        ->assertActionVisible('registerDocument')
        ->assertActionVisible('syncFromSales');

    $this->travelTo(CarbonImmutable::parse('2027-07-02 09:00'));
    FiscalBookPeriods::close(FiscalBook::Sales, fiscalTestPeriod(), $analyst);

    Livewire\Livewire::withQueryParams(['mes' => '2027-06'])
        ->actingAs($analyst)
        ->test(App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalSalesBook::class)
        ->assertSee('Mes cerrado')
        ->assertActionHidden('registerDocument')
        ->assertActionHidden('syncFromSales')
        ->assertActionVisible('reopenPeriod');

    Livewire\Livewire::withQueryParams(['mes' => '2027-06'])
        ->actingAs($analyst)
        ->test(App\Filament\Administration\Resources\FiscalBooks\Pages\ListFiscalPurchasesBook::class)
        ->assertSuccessful()
        ->assertSee('Resumen de créditos fiscales')
        ->assertActionVisible('importPayables');
});
