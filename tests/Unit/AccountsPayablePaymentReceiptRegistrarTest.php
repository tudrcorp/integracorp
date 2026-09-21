<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Models\OperationAccountsPayable;
use App\Support\Operations\AccountsPayablePaymentReceiptPreview;
use App\Support\Operations\AccountsPayablePaymentReceiptRegistrar;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(Tests\TestCase::class);

beforeEach(fn () => DB::beginTransaction());
afterEach(fn () => DB::rollBack());

it('registra el comprobante y los datos de pago en una factura pendiente', function (): void {
    Storage::fake('public');

    $factura = OperationAccountsPayable::factory()->create([
        'invoice_amount' => 19.55,
        'invoice_currency' => 'USD',
        'payment_status' => StatusCuentaPorPagar::PendientePorPagar->value,
    ]);

    $resultado = AccountsPayablePaymentReceiptRegistrar::apply($factura, [
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/comprobante.pdf',
        'payment_status' => StatusCuentaPorPagar::EnGestion->value,
        'payment_reference' => 'REF-4411',
        'payment_date' => '2026-09-11',
        'national_bank' => 'BANESCO',
        'international_bank' => null,
        'payment_amount_usd' => 19.55,
        'payment_amount_ves' => 813.74,
    ], 'Analista QA');

    expect($resultado->payment_status)->toBe(StatusCuentaPorPagar::Pagada)
        ->and($resultado->payment_reference)->toBe('REF-4411')
        ->and($resultado->payment_date?->format('Y-m-d'))->toBe('2026-09-11')
        ->and($resultado->national_bank)->toBe('BANESCO')
        ->and($resultado->payment_amount_usd)->toBe('19.5500')
        ->and($resultado->payment_amount_ves)->toBe('813.7400')
        ->and($resultado->hasPaymentReceipt())->toBeTrue()
        ->and($resultado->updated_by)->toBe('Analista QA');
});

it('aplica el mismo comprobante a varias facturas y usa el monto de cada una si no se indica', function (): void {
    $primera = OperationAccountsPayable::factory()->create([
        'invoice_amount' => 100,
        'invoice_currency' => 'USD',
    ]);
    $segunda = OperationAccountsPayable::factory()->create([
        'invoice_amount' => 250.5,
        'invoice_currency' => 'USD',
    ]);

    $resultado = AccountsPayablePaymentReceiptRegistrar::applyMany(collect([$primera, $segunda]), [
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/lote.jpg',
        'payment_reference' => 'TRF-900',
        'payment_date' => '2026-09-12',
        'national_bank' => 'BANCO DE VENEZUELA',
        'international_bank' => null,
        'payment_amount_usd' => null,
        'payment_amount_ves' => null,
    ], 'Tesorería');

    expect($resultado['updated'])->toBe(2)
        ->and($resultado['receipt_path'])->toBe('operation-accounts-payables/payment-receipts/lote.jpg');

    expect($primera->refresh()->payment_amount_usd)->toBe('100.0000')
        ->and($primera->payment_status)->toBe(StatusCuentaPorPagar::Pagada)
        ->and($primera->payment_receipt_path)->toBe('operation-accounts-payables/payment-receipts/lote.jpg')
        ->and($segunda->refresh()->payment_amount_usd)->toBe('250.5000')
        ->and($segunda->payment_receipt_path)->toBe($primera->payment_receipt_path);
});

it('rechaza guardar sin comprobante, referencia o fecha', function (array $data, string $mensaje): void {
    $factura = OperationAccountsPayable::factory()->make([
        'invoice_amount' => 10,
        'invoice_currency' => 'USD',
    ]);

    expect(fn () => AccountsPayablePaymentReceiptRegistrar::applyMany(collect([$factura]), $data, 'QA'))
        ->toThrow(InvalidArgumentException::class, $mensaje);
})->with([
    'sin archivo' => [[
        'payment_receipt_path' => null,
        'payment_reference' => 'REF',
        'payment_date' => '2026-09-12',
    ], 'Adjunta el comprobante de pago (imagen o PDF).'],
    'sin referencia' => [[
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/a.pdf',
        'payment_reference' => '  ',
        'payment_date' => '2026-09-12',
    ], 'Indica la referencia de pago del comprobante.'],
    'sin fecha' => [[
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/a.pdf',
        'payment_reference' => 'REF',
        'payment_date' => null,
    ], 'Indica la fecha del pago.'],
]);

it('no borra un comprobante compartido al actualizar una sola factura', function (): void {
    Storage::fake('public');
    Storage::disk('public')->put('operation-accounts-payables/payment-receipts/compartido.pdf', 'pdf');

    $primera = OperationAccountsPayable::factory()->create([
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/compartido.pdf',
        'payment_status' => StatusCuentaPorPagar::Pagada->value,
        'payment_reference' => 'OLD',
        'payment_date' => '2026-09-01',
        'payment_amount_usd' => 10,
    ]);
    $segunda = OperationAccountsPayable::factory()->create([
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/compartido.pdf',
        'payment_status' => StatusCuentaPorPagar::Pagada->value,
        'payment_reference' => 'OLD',
        'payment_date' => '2026-09-01',
        'payment_amount_usd' => 20,
    ]);

    AccountsPayablePaymentReceiptRegistrar::apply($primera, [
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/nuevo.pdf',
        'payment_reference' => 'NEW',
        'payment_date' => '2026-09-12',
        'national_bank' => 'BANESCO',
        'payment_amount_usd' => 10,
    ], 'QA');

    expect(Storage::disk('public')->exists('operation-accounts-payables/payment-receipts/compartido.pdf'))->toBeTrue()
        ->and($segunda->refresh()->payment_receipt_path)->toBe('operation-accounts-payables/payment-receipts/compartido.pdf')
        ->and($primera->refresh()->payment_receipt_path)->toBe('operation-accounts-payables/payment-receipts/nuevo.pdf');
});

it('la tabla y las fichas exponen cargar comprobante en fila y en acciones masivas', function (): void {
    $tabla = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Tables/OperationAccountsPayablesTable.php'));
    $acciones = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Actions/AccountsPayablePaymentReceiptActions.php'));
    $vista = file_get_contents(base_path('app/Filament/Operations/Resources/OperationAccountsPayables/Pages/ViewOperationAccountsPayable.php'));

    expect($tabla)
        ->toContain('AccountsPayablePaymentReceiptActions::makeRecordAction')
        ->toContain('AccountsPayablePaymentReceiptActions::makeBulkAction')
        ->toContain('AccountsPayablePaymentReceiptPreview::action')
        ->and($acciones)
        ->toContain("Action::make('uploadPaymentReceipt')")
        ->toContain("BulkAction::make('uploadPaymentReceipts')")
        ->toContain("make('payment_receipt_path')")
        ->toContain("make('payment_reference')")
        ->toContain("make('payment_date')")
        ->toContain("make('national_bank')")
        ->toContain("make('international_bank')")
        ->toContain("make('payment_amount_usd')")
        ->toContain("make('payment_amount_ves')")
        ->toContain('AccountsPayablePaymentReceiptRegistrar::applyMany')
        ->and($vista)
        ->toContain('AccountsPayablePaymentReceiptActions::makeRecordAction');
});

it('incrusta el PDF del comprobante en la vista previa', function (): void {
    $cuenta = OperationAccountsPayable::factory()->make([
        'payment_receipt_path' => 'operation-accounts-payables/payment-receipts/demo.pdf',
        'payment_reference' => 'REF-1',
        'invoice_number' => 'F-1',
    ]);

    $html = AccountsPayablePaymentReceiptPreview::render($cuenta)->toHtml();

    expect(AccountsPayablePaymentReceiptPreview::isPdf($cuenta))->toBeTrue()
        ->and($html)->toContain('<iframe')
        ->and($html)->toContain('demo.pdf')
        ->and($html)->toContain('height:78vh');
});
