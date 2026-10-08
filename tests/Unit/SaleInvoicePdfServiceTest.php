<?php

declare(strict_types=1);

use App\Models\Log as AuditLog;
use App\Models\Sale;
use App\Services\SaleInvoicePdfService;
use App\Support\Sales\InvoiceVesLineAmounts;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());

afterEach(function (): void {
    DB::rollBack();

    foreach (File::glob(public_path('storage/facturas/FACT-PEST-REGEN-*')) as $file) {
        File::delete($file);
    }
});

/**
 * @param  array<string, mixed>  $attributes
 */
function pestInvoiceSale(array $attributes = []): Sale
{
    return Sale::query()->forceCreate([
        'date_activation' => '08/10/2026',
        'owner_code' => 'TDG-100',
        'code_agency' => 'TDG-100',
        'invoice_number' => 'PEST-RDP',
        'persons' => '1',
        'type' => 'AFILIACION INDIVIDUAL',
        'affiliation_code' => 'PEST-SIN-AFILIACION',
        'affiliate_full_name' => 'TITULAR DE PRUEBA',
        'affiliate_ci_rif' => 'V-12345678',
        'payment_method' => 'TRANSFERENCIA VES',
        'reference_payment' => '13722',
        'pay_amount_usd' => 0,
        'pay_amount_ves' => 1032902.52,
        'total_amount' => 1181.5,
        'invoice_generated' => 'PEST-REGEN-'.uniqid(),
        ...$attributes,
    ]);
}

/*
 * ---------------------------------------------------------------------------
 * Conversión de líneas a bolívares
 * ---------------------------------------------------------------------------
 */

it('convierte las líneas de la factura 01021 con la tasa implícita y cuadra al céntimo', function (): void {
    $resultado = InvoiceVesLineAmounts::distribute([686.25, 209.25, 159.25, 126.75], 1032902.52);

    expect(round(array_sum($resultado['lines']), 2))->toBe(1032902.52)
        ->and($resultado['rate'])->toEqualWithDelta(874.2298, 0.0001)
        ->and($resultado['lines'])->toBe([599940.2, 182932.59, 139221.1, 110808.63]);
});

it('lleva el ajuste de céntimos a la línea de mayor monto', function (): void {
    $resultado = InvoiceVesLineAmounts::distribute([1, 1, 1], 100);

    expect($resultado['lines'])->toBe([33.34, 33.33, 33.33])
        ->and(array_sum($resultado['lines']))->toBe(100.0);
});

it('no inventa montos cuando no hay base para convertir', function (array $usd, mixed $total): void {
    $resultado = InvoiceVesLineAmounts::distribute($usd, $total);

    expect($resultado['rate'])->toBeNull()
        ->and($resultado['lines'])->each->toBeNull();
})->with([
    'total en cero' => [[100, 50], 0],
    'líneas en cero' => [[0, 0], 5000],
    'total no numérico' => [[100], 'abc'],
    'sin líneas' => [[], 5000],
]);

it('formatea en bolívares con el estilo venezolano', function (): void {
    expect(InvoiceVesLineAmounts::format(1032902.52))->toBe('1.032.902,52 Bs.')
        ->and(InvoiceVesLineAmounts::format(null))->toBe('—');
});

/*
 * ---------------------------------------------------------------------------
 * Total y datos de la emisión
 * ---------------------------------------------------------------------------
 */

it('calcula el total en bolívares como la acción original', function (): void {
    expect(SaleInvoicePdfService::totalVes(new Sale(['pay_amount_usd' => 100, 'pay_amount_ves' => 0]), 36.5))->toBe(3650.0)
        ->and(SaleInvoicePdfService::totalVes(new Sale(['pay_amount_usd' => 0, 'pay_amount_ves' => 1032902.52]), null))->toBe(1032902.52);
});

it('exige la tasa BCV si la venta se cobró en dólares', function (): void {
    $sale = new Sale(['type' => 'AFILIACION INDIVIDUAL', 'pay_amount_usd' => 100]);

    expect(fn () => app(SaleInvoicePdfService::class)->build($sale, [
        'invoice_number' => '1', 'date' => '08/10/2026',
    ], now()))->toThrow(RuntimeException::class, 'Indique la tasa BCV');
});

it('calcula la vigencia corporativa desde la emisión, no desde hoy', function (): void {
    $montos = SaleInvoicePdfService::corporateAmounts(
        [['subtotal_anual' => 4000, 'subtotal_quarterly' => 1000, 'payment_frequency' => 'TRIMESTRAL']],
        'TRIMESTRAL',
        500000,
        Carbon::parse('2026-01-31'),
    );

    expect($montos['period_from'])->toBe('31/01/2026')
        ->and($montos['period_to'])->toBe('30/04/2026')
        ->and($montos['lines_ves'])->toBe([500000.0]);
});

it('recupera fecha y destinatario de una factura vieja desde la auditoría', function (): void {
    $sale = pestInvoiceSale();

    AuditLog::query()->forceCreate([
        'user_id' => 0,
        'action' => 'AUDIT_ADMIN_SALES_INVOICE_GENERATION_ATTEMPTED',
        'route' => 'administration.sales.generate-invoice',
        'response' => json_encode(['details' => [
            'sale_id' => $sale->id,
            'invoice_number' => $sale->invoice_generated,
            'date' => '08/10/2026',
            'invoice_in_name_of' => 'tomador',
        ]]),
    ]);

    expect(app(SaleInvoicePdfService::class)->previousInvoiceInput($sale))->toMatchArray([
        'date' => '08/10/2026',
        'invoice_in_name_of' => 'tomador',
        'tasa_bcv' => null,
        'source' => 'audit',
    ]);
});

it('ignora en la auditoría intentos de otro número de factura', function (): void {
    $sale = pestInvoiceSale();

    AuditLog::query()->forceCreate([
        'user_id' => 0,
        'action' => 'AUDIT_ADMIN_SALES_INVOICE_GENERATION_ATTEMPTED',
        'route' => 'administration.sales.generate-invoice',
        'response' => json_encode(['details' => [
            'sale_id' => $sale->id,
            'invoice_number' => 'OTRO-NUMERO',
            'date' => '01/01/2026',
        ]]),
    ]);

    expect(app(SaleInvoicePdfService::class)->previousInvoiceInput($sale))
        ->toMatchArray(['date' => null, 'source' => 'none']);
});

/*
 * ---------------------------------------------------------------------------
 * Regeneración
 * ---------------------------------------------------------------------------
 */

it('regenera conservando número y fecha, y guarda respaldo del PDF anterior', function (): void {
    $sale = pestInvoiceSale([
        'invoice_snapshot' => ['date' => '08/10/2026', 'invoice_in_name_of' => 'titular', 'issued_at' => '2026-10-08T10:00:00-04:00'],
    ]);
    $path = SaleInvoicePdfService::pdfPath($sale->invoice_generated);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'PDF ORIGINAL');

    $resultado = app(SaleInvoicePdfService::class)->regenerate($sale, [
        'date' => '25/12/2030',
        'invoice_in_name_of' => 'titular',
    ]);

    $sale->refresh();

    expect($resultado['path'])->toBe($path)
        ->and(File::get($path))->toStartWith('%PDF')
        ->and(File::get((string) $resultado['replaced_path']))->toBe('PDF ORIGINAL')
        ->and($sale->invoice_snapshot['date'])->toBe('08/10/2026')
        ->and($sale->invoice_snapshot['total_ves'])->toBe(1032902.52)
        ->and($sale->invoice_snapshot)->toHaveKey('regenerated_at')
        ->and(str_starts_with($sale->invoice_generated, 'PEST-REGEN-'))->toBeTrue();
});

it('pide la fecha original cuando no hay registro de la emisión', function (): void {
    $sale = pestInvoiceSale();

    expect(fn () => app(SaleInvoicePdfService::class)->regenerate($sale, ['invoice_in_name_of' => 'titular']))
        ->toThrow(RuntimeException::class, 'Indique la fecha de emisión original');
});

it('si falla la regeneración el PDF existente queda intacto', function (): void {
    $sale = pestInvoiceSale(['pay_amount_usd' => 100, 'invoice_snapshot' => ['date' => '08/10/2026']]);
    $path = SaleInvoicePdfService::pdfPath($sale->invoice_generated);
    File::ensureDirectoryExists(dirname($path));
    File::put($path, 'PDF ORIGINAL');

    expect(fn () => app(SaleInvoicePdfService::class)->regenerate($sale, ['invoice_in_name_of' => 'titular']))
        ->toThrow(RuntimeException::class, 'Indique la tasa BCV');

    expect(File::get($path))->toBe('PDF ORIGINAL')
        ->and(File::glob($path.'.reemplazada-*'))->toBe([]);
});

it('no regenera una venta sin factura emitida', function (): void {
    expect(fn () => app(SaleInvoicePdfService::class)->regenerate(new Sale, []))
        ->toThrow(RuntimeException::class, 'no tiene una factura emitida');
});

/*
 * ---------------------------------------------------------------------------
 * Plantilla y acción
 * ---------------------------------------------------------------------------
 */

it('la factura corporativa muestra montos en Bs. y solo la cobertura en US$', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/resources/views/documents/factura-corporativa.blade.php');

    expect($source)
        ->toContain("InvoiceVesLineAmounts::format(\$data_factura['total_amount'])")
        ->toContain('InvoiceVesLineAmounts::format($line_amount_ves)')
        ->toContain("\$data_factura['period_from']")
        ->not->toContain('number_format($total_amount, 2) }}US$')
        ->not->toContain("number_format(\$data_factura['total_amount'], 2) }}US$")
        ->and(substr_count($source, 'US$'))->toBe(1);
});

it('registra la acción de regenerar factura junto a la de generar', function (): void {
    $source = (string) file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Sales/Tables/SalesTable.php');

    expect($source)
        ->toContain("Action::make('regenerate_invoice')")
        ->toContain('->visible(fn (Sale $record): bool => filled($record->invoice_generated))')
        ->toContain('self::regenerateInvoiceAction(),')
        ->toContain('AUDIT_ADMIN_SALES_INVOICE_REGENERATED')
        ->toContain('app(SaleInvoicePdfService::class)->generate($record, $data)');
});

it('la acción solo aparece en ventas facturadas y precarga la fecha original', function (): void {
    $usuario = App\Models\User::factory()->create([
        'email' => 'qa.regenerar.factura@tudrencasa.com',
        'status' => 'ACTIVO',
        'departament' => ['SUPERADMIN', 'ADMINISTRACION'],
    ]);
    test()->actingAs($usuario);
    Filament\Facades\Filament::setCurrentPanel('administration');

    $facturada = pestInvoiceSale([
        'invoice_snapshot' => ['date' => '15/03/2026', 'invoice_in_name_of' => 'tomador'],
    ]);
    $sinFactura = pestInvoiceSale(['invoice_generated' => null]);

    Livewire\Livewire::test(App\Filament\Administration\Resources\Sales\Pages\ListSales::class)
        ->assertTableActionVisible('regenerate_invoice', $facturada)
        ->assertTableActionHidden('regenerate_invoice', $sinFactura)
        ->mountTableAction('regenerate_invoice', $facturada)
        ->assertSet('mountedActions.0.data.date', fn (mixed $fecha): bool => str_starts_with((string) $fecha, '2026-03-15'))
        ->assertSet('mountedActions.0.data.date_known', true)
        ->assertSet('mountedActions.0.data.invoice_in_name_of', 'tomador');
});
