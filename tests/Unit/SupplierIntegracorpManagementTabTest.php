<?php

declare(strict_types=1);

it('define el tab de gestion integracorp en el formulario de proveedores', function (): void {
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierForm.php');
    $formTab = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementForm.php');
    $create = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/CreateSupplier.php');
    $edit = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/EditSupplier.php');

    expect($form)
        ->toContain('SupplierIntegracorpManagementForm::formTab')
        ->and($formTab)
        ->toContain("Toggle::make('gestion_integracorp')")
        ->toContain('->visible(fn (): bool => OperationsSuperAdmin::check())')
        ->toContain('SupplierIntegracorpManagement::modulesPanelHtml()')
        ->toContain('OperationsSuperAdmin::check()')
        ->toContain('stripUnauthorizedFormData')
        ->and($create)
        ->toContain('mutateFormDataBeforeCreate')
        ->and($edit)
        ->toContain('stripUnauthorizedFormData');
});

it('define el tab de gestion integracorp en el infolist de proveedores', function (): void {
    $infolist = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Schemas/SupplierInfolist.php');
    $tab = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementTab.php');
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagement.php');
    $panel = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/operations/suppliers/partials/integracorp-modules-panel.blade.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_02_231300_add_gestion_integracorp_to_suppliers_table.php');

    expect($infolist)
        ->toContain('SupplierIntegracorpManagementTab::make()')
        ->and($tab)
        ->toContain("Tab::make('Gestion de Procesos en Integracorp')")
        ->toContain('->visible(fn (): bool => OperationsSuperAdmin::check())')
        ->toContain('gestion_integracorp')
        ->toContain('OperationsSuperAdmin::check()')
        ->toContain('gestion-integracorp-tab')
        ->and($panel)
        ->toContain('Servicios médicos')
        ->toContain('Órdenes de servicio')
        ->and($migration)
        ->toContain("boolean('gestion_integracorp')->default(false)");
});
