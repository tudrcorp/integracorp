<?php

declare(strict_types=1);

use App\Support\Operations\CoordinationServiceItemsManager;

function coveredQuoteManagerSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/CoordinationServiceItemsManager.php'
    );
}

function coveredQuoteFormSource(): string
{
    return file_get_contents(
        dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationCoordinationServices/Schemas/ManageCoordinationServiceItemsForm.php'
    );
}

it('quoteSelectedManagementItemKeys solo agrega los cubiertos cuando el proveedor es no convenido', function (): void {
    $manager = coveredQuoteManagerSource();

    expect($manager)
        ->toContain('public static function quoteSelectedManagementItemKeys(')
        ->toContain('bool $registerUnregisteredProvider = false')
        ->toContain('if ($registerUnregisteredProvider) {')
        ->toContain('self::coveredSelectedManagementItemKeys($record, $selectedKeys)');
});

it('buildManageQuoteLineItemsDefault usa las claves de cotización generalizadas', function (): void {
    $reflection = new ReflectionMethod(CoordinationServiceItemsManager::class, 'buildManageQuoteLineItemsDefault');
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(4)
        ->and($parameters[3]->getName())->toBe('registerUnregisteredProvider')
        ->and($parameters[3]->isDefaultValueAvailable())->toBeTrue()
        ->and($parameters[3]->getDefaultValue())->toBeFalse();

    expect(coveredQuoteManagerSource())
        ->toContain('$quoteKeys = self::quoteSelectedManagementItemKeys($record, $selectedKeys, $registerUnregisteredProvider);')
        ->toContain('in_array($item[\'key\'], $quoteKeys, true)');
});

it('sumItemsUnitPriceUsd suma los precios unitarios en USD de los ítems', function (): void {
    $subtotal = CoordinationServiceItemsManager::sumItemsUnitPriceUsd([
        ['key' => 'medication:1', 'unit_price_usd' => 12.5],
        ['key' => 'lab:2', 'unit_price_usd' => 7.5],
        ['key' => 'study:3', 'unit_price_usd' => null],
    ]);

    expect($subtotal)->toBe(20.0);
});

it('sumItemsUnitPriceUsd devuelve null cuando ningún ítem tiene precio', function (): void {
    expect(CoordinationServiceItemsManager::sumItemsUnitPriceUsd([
        ['key' => 'medication:1', 'unit_price_usd' => null],
    ]))->toBeNull();

    expect(CoordinationServiceItemsManager::sumItemsUnitPriceUsd([]))->toBeNull();
});

it('save() difiere la orden para cubierto + no convenido jurídico y crea solo la cotización pendiente', function (): void {
    $manager = coveredQuoteManagerSource();

    expect($manager)
        ->toContain('$registerUnregistered = (bool) ($data[\'register_unregistered_provider\'] ?? false);')
        ->toContain('=== \'juridico\';')
        ->toContain('$shouldCreateCoveredQuote = $coveredQuoteViaUnregistered && $coveredKeys !== [] && $serviceOrderType !== null;')
        ->toContain('OperationServiceOrderProviderSelection::validationMessage($data)')
        ->toContain('$resolved = OperationServiceOrderProviderSelection::resolveProviders($data);')
        ->toContain('$data[\'register_unregistered_provider\'] = false;')
        ->toContain('if ($shouldCreateServiceOrder && ! $shouldCreateCoveredQuote) {')
        ->toContain('if ($shouldCreateCoveredQuote && $coveredQuoteItemsPayload !== []) {')
        ->toContain('al aprobarla se creará la orden de servicio y la cuenta por pagar.');
});

it('el ítem cubierto con cotización pendiente sin orden muestra el enlace para aprobar', function (): void {
    $manager = coveredQuoteManagerSource();

    expect($manager)
        ->toContain('fi-coordination-clinical-item-quote-link')
        ->toContain('title="Aprobar cotización ')
        ->not->toContain("} elseif (\$item['coverage'] === false) {");
});

it('persistManageQuote acepta un proveedor explícito y cuenta el subtotal desde los ítems', function (): void {
    $reflection = new ReflectionMethod(CoordinationServiceItemsManager::class, 'persistManageQuote');
    $parameters = $reflection->getParameters();

    expect($parameters)->toHaveCount(7)
        ->and($parameters[4]->getName())->toBe('supplierIdOverride')
        ->and($parameters[5]->getName())->toBe('supplierAddressOverride')
        ->and($parameters[6]->getName())->toBe('useSupplierOverride');

    expect(coveredQuoteManagerSource())
        ->toContain('$costoUsd = self::sumItemsUnitPriceUsd($items)')
        ->toContain('if ($useSupplierOverride) {')
        ->toContain('AccountsReceivableManager::syncFromQuote');
});

it('el formulario muestra el paso Cotización para cubiertos con proveedor no convenido', function (): void {
    $form = coveredQuoteFormSource();

    expect($form)
        ->toContain('CoordinationServiceItemsManager::shouldShowManageQuoteStep(')
        ->toContain('CoordinationServiceItemsManager::manageQuoteStepResolvedType(')
        ->toContain('CoordinationServiceItemsManager::manageServiceQuoteItemsTable(')
        ->toContain('Obligatoria para ítems no cubiertos y cubiertos con proveedor no convenido');
});

it('el toggle y el tipo de proveedor no convenido reconstruyen los precios por ítem (incluye cubiertos jurídico)', function (): void {
    $form = coveredQuoteFormSource();

    expect($form)
        ->toContain('OperationServiceOrderProviderFormFields::components(')
        ->toContain('CoordinationServiceItemsManager::rebuildManageQuoteLineItems($livewire->getRecord(), $get, $set)')
        ->toContain('OperationServiceOrderUnregisteredProviderFormFields::wizardStepSchema(');

    $manager = coveredQuoteManagerSource();

    expect($manager)
        ->toContain('public static function coveredQuotedViaUnregisteredProvider(Get $get): bool')
        ->toContain("=== 'juridico';")
        ->toContain('public static function rebuildManageQuoteLineItems(OperationCoordinationService $record, Get $get, Set $set): void');

    $providerFields = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderProviderFormFields.php'
    );

    expect($providerFields)
        ->toContain('public static function components(?Closure $toggleAfterStateUpdated = null): array')
        ->toContain('OperationServiceOrderUnregisteredProviderFormFields::registerToggle($toggleAfterStateUpdated)');

    $toggleFields = file_get_contents(
        dirname(__DIR__, 2).'/app/Support/Operations/OperationServiceOrderUnregisteredProviderFormFields.php'
    );

    expect($toggleFields)
        ->toContain('public static function wizardStepSchema(?Closure $onProviderTypeUpdated = null): array')
        ->toContain('$providerTypeField->afterStateUpdated($onProviderTypeUpdated);')
        ->toContain('public static function registerToggle(?Closure $afterStateUpdated = null): Toggle')
        ->toContain('if ($afterStateUpdated !== null) {')
        ->toContain('$toggle->afterStateUpdated($afterStateUpdated);');
});

it('el formulario condiciona el select de proveedor al flujo no cubierto', function (): void {
    $form = coveredQuoteFormSource();

    expect($form)
        ->toContain('CoordinationServiceItemsManager::shouldShowManageQuoteSupplierSelect(')
        ->toContain('->required(fn (ManageCoordinationServiceItems $livewire, Get $get): bool => CoordinationServiceItemsManager::shouldShowManageQuoteSupplierSelect(')
        ->toContain('manage_quote_unregistered_provider_notice')
        ->toContain('La cotización se generará con el proveedor no convenido registrado en el paso anterior.');
});
