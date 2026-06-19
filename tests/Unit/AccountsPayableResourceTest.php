<?php

declare(strict_types=1);

use App\Models\OperationQuoteGenerator;
use App\Support\Operations\AccountsPayablePresenter;
use App\Support\Operations\CoordinationServiceQuoteManager;

it('AccountsPayableResource usa cotizaciones de coordinacion en el grupo COORDINACION DE SERVICIOS', function (): void {
    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsPayables/AccountsPayableResource.php');
    $table = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsPayables/Tables/AccountsPayablesTable.php');

    expect($resource)
        ->toContain('OperationQuoteGenerator::class')
        ->toContain("'Cuentas por pagar'")
        ->toContain("'COORDINACIÓN DE SERVICIOS'")
        ->toContain('Heroicon::OutlinedBanknotes')
        ->toContain('canCreate(): bool')
        ->toContain('ListAccountsPayables::route')
        ->toContain('ViewAccountsPayable::route')
        ->not->toContain('CreateAccountsPayable::route')
        ->not->toContain('EditAccountsPayable::route');

    expect($table)
        ->toContain('AccountsPayablePresenter::patientName')
        ->toContain('AccountsPayablePresenter::caseCode')
        ->toContain('AccountsPayablePresenter::serviceOrderNumber')
        ->toContain('AccountsPayablePresenter::quoteNumber')
        ->toContain('AccountsPayablePresenter::formatUsd')
        ->toContain('AccountsPayablePresenter::formatVes')
        ->toContain('AccountsPayablePresenter::quoteSupplierLabel')
        ->toContain('AccountsPayablePresenter::orderSupplierLabel')
        ->toContain('OperationsSupplierScope::applyCoordinationListScope');
});

it('AccountsPayablePresenter calcula montos y etiquetas de cotizacion', function (): void {
    $quote = new OperationQuoteGenerator([
        'total' => 100.50,
        'costo_bolivares' => 3658.20,
    ]);
    $quote->id = 12;
    $quote->setRelation('operationServiceOrder', null);

    expect(AccountsPayablePresenter::quoteNumber($quote))
        ->toBe(CoordinationServiceQuoteManager::formatCoordinationQuoteNumber(12))
        ->and(AccountsPayablePresenter::quoteAmountUsd($quote))->toBe(100.50)
        ->and(AccountsPayablePresenter::quoteAmountVes($quote))->toBe(3658.20)
        ->and(AccountsPayablePresenter::serviceOrderNumber($quote))->toBeNull();
});

it('AccountsPayablePresenter convierte a bolivares con tasa BCV cuando no hay monto guardado', function (): void {
    $quote = new OperationQuoteGenerator([
        'id' => 3,
        'total' => 10.00,
        'costo_bolivares' => 0,
    ]);

    $rate = AccountsPayablePresenter::bcvRateForQuote($quote);

    if ($rate === null) {
        expect(AccountsPayablePresenter::quoteAmountVes($quote))->toBeNull();

        return;
    }

    expect(AccountsPayablePresenter::quoteAmountVes($quote))->toBe(round(10.0 * $rate, 2));
});

it('AccountsPayableInfolist y ViewAccountsPayable exponen tabs y acciones PDF con estilo iOS', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsPayables/Schemas/AccountsPayableInfolist.php');
    $viewPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/AccountsPayables/Pages/ViewAccountsPayable.php');
    $preview = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/accounts-payables/pdf-preview.blade.php');

    expect($infolist)
        ->toContain("Tabs::make('accountsPayableInfolistTabs')")
        ->toContain("Tab::make('Resumen')")
        ->toContain("Tab::make('Montos y proveedores')")
        ->toContain('->persistTab()');

    expect($viewPage)
        ->toContain("Action::make('preview_quote_pdf')")
        ->toContain("'Ver PDF Cotización'")
        ->toContain("Action::make('preview_service_order_pdf')")
        ->toContain("'Ver PDF Orden de Servicio'")
        ->toContain("Action::make('back')")
        ->toContain("'Volver'")
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain('filament.operations.accounts-payables.pdf-preview')
        ->toContain('AccountsPayablePresenter::quotePdfPreviewUrl')
        ->toContain('AccountsPayablePresenter::serviceOrderPdfPreviewUrl')
        ->toContain('Width::SevenExtraLarge');

    expect($preview)
        ->toContain('documentLabel')
        ->toContain('documentTitle')
        ->toContain('<iframe');
});
