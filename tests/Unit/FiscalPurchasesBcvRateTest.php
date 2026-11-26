<?php

declare(strict_types=1);

use App\Enums\FiscalBook;
use App\Models\BcvHistoricalRate;
use App\Models\FiscalBookEntry;
use App\Models\OperationAccountsPayable;
use App\Models\User;
use App\Support\Administration\FiscalBooks\FiscalPurchasesPayableImport;
use App\Support\BcvRateHistory;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

/**
 * Libro de Compras: la tasa BCV de cada factura de cuentas por pagar se toma sola según su fecha
 * (fin de semana o feriado: la última publicada antes) y se puede corregir a mano, quedando anotado.
 * La API se simula. Escribe dentro de una transacción que siempre se revierte.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    Cache::forget('bcv-rate-history:sync-attempt');
    Http::swap(new Illuminate\Http\Client\Factory(app('events')));
    Http::fake(['ve.dolarapi.com/*' => Http::response([], 200)]);

    BcvHistoricalRate::query()->whereBetween('rate_date', ['2027-02-15', '2027-04-30'])->delete();
    BcvHistoricalRate::query()->insert([
        ['rate_date' => '2027-03-04', 'rate' => '1001.10000000', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ['rate_date' => '2027-03-05', 'rate' => '1002.25550000', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
        ['rate_date' => '2027-03-08', 'rate' => '1005.00000000', 'source' => 'test', 'created_at' => now(), 'updated_at' => now()],
    ]);

    $this->period = CarbonImmutable::parse('2027-06-01');
    $this->user = (new User)->forceFill(['id' => 999_999_701, 'name' => 'QA COMPRAS']);
    $this->payable = fn (string $date, string $amount = '10.00', string $number = ''): OperationAccountsPayable => OperationAccountsPayable::query()->create([
        'invoice_date' => $date, 'invoice_registration_date' => $date,
        'invoice_number' => $number !== '' ? $number : 'BCV-'.random_int(100000, 999999), 'invoice_control_number' => '00-'.random_int(1000, 9999),
        'supplier_name' => 'Proveedor QA C.A.', 'supplier_rif' => 'J30'.random_int(1000000, 9999999),
        'invoice_amount' => $amount, 'invoice_currency' => 'USD', 'created_by' => 'test',
    ]);
});

afterEach(function (): void {
    DB::rollBack();
    Cache::forget('bcv-rate-history:sync-attempt');
});

it('busca la tasa del día y, sin publicación, la última publicada antes', function (): void {
    $rates = BcvRateHistory::forDates(['2027-03-05', '2027-03-06', '2027-03-07', '2027-03-08']);

    expect($rates['2027-03-05'])->toBe(['rate' => '1002.25550000', 'published' => '2027-03-05'])
        ->and($rates['2027-03-06']['published'])->toBe('2027-03-05')
        ->and($rates['2027-03-07']['published'])->toBe('2027-03-05')
        ->and($rates['2027-03-08']['rate'])->toBe('1005.00000000');
});

it('no inventa: más de 10 días sin publicación o antes del histórico no tiene tasa', function (): void {
    expect(BcvRateHistory::forDate('2027-03-25'))->toBeNull()
        ->and(BcvRateHistory::forDate('2027-02-20'))->toBeNull();
});

it('guarda el histórico de la API, ignora filas malas y no toca nada si la API falla', function (): void {
    Http::swap(new Illuminate\Http\Client\Factory(app('events')));
    Http::fake(['ve.dolarapi.com/*' => Http::sequence()
        ->push([
            ['fuente' => 'oficial', 'promedio' => 1010.5, 'fecha' => '2027-03-09'],
            ['fuente' => 'oficial', 'promedio' => 1002.3, 'fecha' => '2027-03-05'],
            ['fuente' => 'oficial', 'promedio' => null, 'fecha' => '2027-03-10'],
            ['fuente' => 'oficial', 'promedio' => 5, 'fecha' => 'ayer'],
        ])
        ->push('caída', 500)]);

    expect(BcvRateHistory::sync())->toBe(2)
        ->and((string) BcvHistoricalRate::query()->whereDate('rate_date', '2027-03-09')->value('rate'))->toBe('1010.50000000')
        ->and((string) BcvHistoricalRate::query()->whereDate('rate_date', '2027-03-05')->value('rate'))->toBe('1002.30000000')
        ->and(BcvHistoricalRate::query()->whereDate('rate_date', '2027-03-10')->exists())->toBeFalse()
        ->and(BcvRateHistory::sync())->toBeNull()
        ->and((string) BcvHistoricalRate::query()->whereDate('rate_date', '2027-03-09')->value('rate'))->toBe('1010.50000000');
});

it('consulta la API solo una vez cada 10 minutos cuando falta una fecha nueva', function (): void {
    BcvRateHistory::forDate('2099-01-02');
    BcvRateHistory::forDate('2099-01-03');

    expect(Http::recorded(fn ($request): bool => str_contains($request->url(), 'historicos')))->toHaveCount(1);
});

it('importa con la tasa oficial de cada fecha, sin escribir nada', function (): void {
    $friday = ($this->payable)('2027-03-05', '10.00');
    $saturday = ($this->payable)('2027-03-06', '4.00');

    $count = FiscalPurchasesPayableImport::import($this->period, [$friday->id, $saturday->id], [], $this->user);
    $entries = FiscalBookEntry::query()->forBook(FiscalBook::Purchases)->where('source', FiscalBookEntry::SOURCE_PAYABLE)->whereIn('source_id', [$friday->id, $saturday->id])->get()->keyBy('source_id');

    expect($count)->toBe(2)
        ->and($entries[$friday->id]->exchange_rate)->toBe('1002.25550000')
        ->and($entries[$friday->id]->exempt_amount)->toBe('10022.56')
        ->and($entries[$friday->id]->notes)->toBeNull()
        ->and($entries[$saturday->id]->exchange_rate)->toBe('1002.25550000')
        ->and($entries[$saturday->id]->notes)->toBe('Tasa oficial BCV publicada el 05/03/2027 (el 06/03/2027 no hubo publicación).');
});

it('la tasa corregida a mano se usa y queda anotada; la que coincide con la oficial no', function (): void {
    $changed = ($this->payable)('2027-03-04', '2.00');
    $same = ($this->payable)('2027-03-05', '1.00');

    FiscalPurchasesPayableImport::import($this->period, [$changed->id, $same->id], ['2027-03-04' => '1000', '2027-03-05' => '1002.2555'], $this->user);
    $entries = FiscalBookEntry::query()->where('source', FiscalBookEntry::SOURCE_PAYABLE)->whereIn('source_id', [$changed->id, $same->id])->get()->keyBy('source_id');

    expect($entries[$changed->id]->exchange_rate)->toBe('1000.00000000')
        ->and($entries[$changed->id]->notes)->toBe('Tasa BCV corregida a mano: 1.000,0000 (oficial del 04/03/2027: 1.001,1000).')
        ->and($entries[$same->id]->exchange_rate)->toBe('1002.25550000')
        ->and($entries[$same->id]->notes)->toBeNull();
});

it('sin tasa oficial publicada pide escribirla y no importa ninguna (todo o nada)', function (): void {
    $ok = ($this->payable)('2027-03-05');
    $orphan = ($this->payable)('2027-04-20', '3.00', 'SIN-TASA-1');

    expect(fn () => FiscalPurchasesPayableImport::import($this->period, [$ok->id, $orphan->id], [], $this->user))
        ->toThrow(InvalidArgumentException::class, 'No hay tasa oficial BCV publicada para el 20/04/2027 (factura SIN-TASA-1)');

    expect(FiscalBookEntry::query()->where('source', FiscalBookEntry::SOURCE_PAYABLE)->whereIn('source_id', [$ok->id, $orphan->id])->count())->toBe(0);

    FiscalPurchasesPayableImport::import($this->period, [$ok->id, $orphan->id], ['2027-04-20' => '1100'], $this->user);

    expect((string) FiscalBookEntry::query()->where('source_id', $orphan->id)->where('source', FiscalBookEntry::SOURCE_PAYABLE)->value('notes'))
        ->toBe('Tasa BCV corregida a mano: 1.100,0000 (sin tasa oficial publicada para el 20/04/2027).');
});

it('el formulario de importación trae la tasa oficial puesta y dice de dónde sale', function (): void {
    $page = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/FiscalBooks/Pages/ListFiscalPurchasesBook.php');

    expect($page)->toContain('BcvRateHistory::forDates($this->pendingDates())')
        ->and($page)->toContain("'rate' => (\$official = \$this->officialRates()[\$date] ?? null) !== null")
        ->and($page)->toContain('->helperText(fn (Get $get): string => $this->rateHint($get(\'date\')))');
});

it('el scheduler sincroniza las tasas dos veces al día', function (): void {
    $console = (string) file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($console)->toContain("Schedule::command('bcv:sync-rates')")
        ->and($console)->toContain('->twiceDaily(7, 18)');
});
