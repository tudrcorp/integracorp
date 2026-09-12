<?php

declare(strict_types=1);

use App\Enums\StatusCuentaPorPagar;
use App\Http\Controllers\OperationAccountsPayableExportCsvController;
use App\Models\OperationAccountsPayable;
use App\Models\OperationServiceOrder;
use App\Support\CsvExportStream;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

it('guarda los ids seleccionados en cache para exportacion de cuentas por pagar', function (): void {
    $token = OperationAccountsPayableExportCsvController::storeIdsAndGetToken(['7', 12, '20', 0, -3]);

    expect($token)->toBeString()->not->toBeEmpty();

    $cachedIds = Cache::pull('operation_accounts_payable_export_csv_'.$token);

    expect($cachedIds)->toBe([7, 12, 20]);
});

it('rechaza la descarga csv de cuentas por pagar cuando el token no existe o expiro', function (): void {
    $controller = new OperationAccountsPayableExportCsvController;

    $request = Request::create('/operations/export-cuentas-por-pagar-csv', 'GET', [
        'token' => 'token-inexistente',
    ]);

    expect(fn (): mixed => $controller($request))
        ->toThrow(HttpException::class, 'Token de exportación no válido o expirado.');
});

it('tiene registrada la ruta autenticada de exportacion csv de cuentas por pagar', function (): void {
    expect(route('operations.operation-accounts-payables.export-csv', ['token' => 'x']))
        ->toContain('export-cuentas-por-pagar-csv');
});

it('invitado es redirigido al intentar exportar csv de cuentas por pagar', function (): void {
    $token = OperationAccountsPayableExportCsvController::storeIdsAndGetToken([1]);

    $this->get(route('operations.operation-accounts-payables.export-csv', ['token' => $token]))
        ->assertRedirect();
});

it('arma la fila csv con las columnas visibles de la tabla de cuentas por pagar', function (): void {
    $factura = new OperationAccountsPayable([
        'invoice_date' => '2026-09-10',
        'invoice_registration_date' => '2026-09-10',
        'supplier_name' => 'FARMACIA CANAAN, C.A.',
        'supplier_rif' => 'J298665319',
        'invoice_number' => '23154356',
        'invoice_control_number' => null,
        'invoice_amount' => 19.55,
        'invoice_currency' => 'USD',
        'payment_status' => StatusCuentaPorPagar::PendientePorPagar,
        'payment_reference' => null,
        'payment_date' => null,
        'national_bank' => null,
        'international_bank' => null,
        'payment_amount_usd' => null,
        'payment_amount_ves' => null,
        'invoice_file_path' => null,
        'created_by' => 'qa.export',
    ]);
    $factura->setRelation('businessUnit', null);
    $factura->setRelation('operationServiceOrder', new OperationServiceOrder([
        'order_number' => 'ORD-0103',
    ]));

    expect(OperationAccountsPayableExportCsvController::headers())
        ->toContain('N.º factura')
        ->toContain('Orden de servicio')
        ->toContain('Estatus de pago')
        ->toContain('Comprobante de pago')
        ->and(OperationAccountsPayableExportCsvController::buildRow($factura))
        ->toBe([
            '10/09/2026',
            '10/09/2026',
            'FARMACIA CANAAN, C.A.',
            'J298665319',
            '—',
            'ORD-0103',
            '23154356',
            '—',
            'US$ 19,55',
            'USD',
            'Pendiente por pagar',
            '—',
            '—',
            '—',
            '—',
            '—',
            '—',
            'Sin adjuntar',
            'Sin adjuntar',
            'qa.export',
        ]);
});

it('la tabla de cuentas por pagar expone exportar csv en las acciones masivas', function (): void {
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationAccountsPayables/Tables/OperationAccountsPayablesTable.php');

    expect($table)
        ->toContain("BulkAction::make('export_accounts_payables_csv')")
        ->toContain("'Exportar CSV'")
        ->toContain('OperationAccountsPayableExportCsvController::storeIdsAndGetToken')
        ->toContain('CsvExportDownloadTrigger::fromAction')
        ->toContain('operations.operation-accounts-payables.export-csv')
        ->toContain('AccountsPayablePaymentReceiptActions::makeRecordAction')
        ->toContain('AccountsPayablePaymentReceiptActions::makeBulkAction');
});

it('la descarga csv incluye el bom utf-8 para excel', function (): void {
    expect(CsvExportStream::UTF8_BOM)->toBe("\xEF\xBB\xBF");
});
