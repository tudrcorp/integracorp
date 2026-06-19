<?php

declare(strict_types=1);

use App\Models\OperationAccountsReceivable;
use App\Models\OperationQuoteGenerator;
use App\Support\Operations\AccountsReceivableManager;
use App\Support\Operations\AccountsReceivablePresenter;
use App\Support\Operations\CoordinationServiceQuoteManager;

it('AccountsReceivableResource se registra en COORDINACION DE SERVICIOS junto a cuentas por pagar', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsReceivables/AccountsReceivableResource.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsReceivables/Tables/AccountsReceivablesTable.php');
    $coordinationTable = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Tables/OperationCoordinationServicesTable.php');

    expect($resource)
        ->toContain('OperationAccountsReceivable::class')
        ->toContain("'Cuentas por cobrar'")
        ->toContain("'COORDINACIÓN DE SERVICIOS'")
        ->toContain('Heroicon::OutlinedCurrencyDollar')
        ->toContain('ListAccountsReceivables::route')
        ->toContain('ViewAccountsReceivable::route');

    expect($table)
        ->toContain('AccountsReceivablePresenter::patientName')
        ->toContain('AccountsReceivablePresenter::quoteNumber')
        ->toContain('AccountsReceivablePresenter::serviceOrderNumber')
        ->toContain('AccountsReceivablePresenter::reassignmentSupplierName')
        ->toContain('AccountsReceivablePresenter::reassignedAnalystName')
        ->toContain('OperationsSupplierScope::applyCoordinationListScope');

    expect($coordinationTable)
        ->toContain('AccountsReceivableManager::createFromTdgReassignment')
        ->toContain('Se generó la cuenta por cobrar correspondiente');
});

it('migration de operation_accounts_receivables define campos pendientes de gestion TDG', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_16_214507_create_operation_accounts_receivables_table.php');

    expect($migration)
        ->toContain('operation_accounts_receivables')
        ->toContain('operation_quote_generator_id')
        ->toContain('operation_service_order_id')
        ->toContain('quote_number')
        ->toContain('service_order_number')
        ->toContain('quote_amount_usd')
        ->toContain('quote_amount_ves')
        ->toContain('reassignment_supplier_name')
        ->toContain('reassigned_by_analyst_name')
        ->toContain('reassignment_reason')
        ->toContain('->nullable()');
});

it('AccountsReceivablePresenter formatea numero y estatus de cuenta por cobrar', function (): void {
    $record = new OperationAccountsReceivable([
        'id' => 5,
        'quote_number' => null,
        'service_order_number' => null,
        'quote_amount_usd' => null,
        'quote_amount_ves' => null,
        'reassignment_supplier_name' => 'Proveedor AMD Test',
        'reassigned_by_analyst_name' => 'Analista Proveedor',
        'status' => OperationAccountsReceivable::STATUS_PENDING_TDG,
    ]);
    $record->id = 5;

    expect(AccountsReceivablePresenter::receivableNumber($record))
        ->toBe(AccountsReceivableManager::formatReceivableNumber(5))
        ->and(AccountsReceivablePresenter::quoteNumber($record))->toBeNull()
        ->and(AccountsReceivablePresenter::formatUsd(null))->toBe('—')
        ->and(AccountsReceivablePresenter::reassignmentSupplierName($record))->toBe('Proveedor AMD Test')
        ->and(AccountsReceivablePresenter::reassignedAnalystName($record))->toBe('Analista Proveedor')
        ->and(AccountsReceivablePresenter::statusLabel(OperationAccountsReceivable::STATUS_PENDING_TDG))
        ->toBe('Pendiente gestión TDG');
});

it('AccountsReceivableManager sincroniza cotizacion en cuenta por cobrar pendiente', function (): void {
    $manager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/AccountsReceivableManager.php');
    $itemsManager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php');
    $quoteManager = file_get_contents(dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceQuoteManager.php');

    expect($manager)
        ->toContain('createFromTdgReassignment')
        ->toContain('syncFromQuote')
        ->toContain('syncFromServiceOrder')
        ->toContain('STATUS_PENDING_TDG');

    expect($itemsManager)->toContain('AccountsReceivableManager::syncFromQuote');
    expect($quoteManager)
        ->toContain('AccountsReceivableManager::syncFromServiceOrder')
        ->toContain('AccountsReceivableManager::syncFromQuote');
});

it('AccountsReceivableManager asigna numero de cotizacion al sincronizar', function (): void {
    $receivable = new OperationAccountsReceivable([
        'operation_coordination_service_id' => 10,
        'status' => OperationAccountsReceivable::STATUS_PENDING_TDG,
    ]);
    $receivable->id = 1;

    $quote = new OperationQuoteGenerator([
        'id' => 22,
        'operation_coordination_service_id' => 10,
        'total' => 150.00,
        'costo_bolivares' => 5400.00,
    ]);
    $quote->id = 22;

    $expectedQuoteNumber = CoordinationServiceQuoteManager::formatCoordinationQuoteNumber(22);

    expect($expectedQuoteNumber)->toBe('COT-000022');
});
