<?php

declare(strict_types=1);

use App\Support\Filament\Operations\SupplierIntegracorpManagement;

it('normaliza el alias a mayúsculas y sin espacios sobrantes', function (mixed $input, ?string $expected): void {
    expect(SupplierIntegracorpManagement::normalizeAlias($input))->toBe($expected);
})->with([
    'minúsculas' => ['atenmedi', 'ATENMEDI'],
    'espacios internos y bordes' => ['  auxilio   medico 24 ', 'AUXILIO MEDICO 24'],
    'acentos' => ['clínica ávila', 'CLÍNICA ÁVILA'],
    'saltos de línea' => ["tu dr\nen casa", 'TU DR EN CASA'],
    'vacío' => ['', null],
    'solo espacios' => ['   ', null],
    'nulo' => [null, null],
    'arreglo' => [['ATENMEDI'], null],
]);

it('define el alias obligatorio, único y solo visible con la gestión habilitada', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagement.php');
    $form = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementForm.php');
    $tab = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagementTab.php');
    $model = file_get_contents(dirname(__DIR__, 2).'/app/Models/Supplier.php');
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_09_29_140000_add_integracorp_alias_to_suppliers_table.php');

    expect($shared)
        ->toContain("TextInput::make('integracorp_alias')")
        ->toContain("->visible(fn (Get \$get): bool => (bool) \$get('gestion_integracorp'))")
        ->toContain("->required(fn (Get \$get): bool => (bool) \$get('gestion_integracorp'))")
        ->toContain("->unique(table: 'suppliers', column: 'integracorp_alias', ignoreRecord: true)")
        ->toContain('->dehydrated(fn (): bool => self::userCanManage())')
        ->toContain('módulo de Afiliaciones')
        ->and($form)
        ->toContain('SupplierIntegracorpManagement::aliasInput()')
        ->toContain("\$data['integracorp_alias']")
        ->and($tab)
        ->toContain("TextEntry::make('integracorp_alias')")
        ->and($model)
        ->toContain("'integracorp_alias'")
        ->and($migration)
        ->toContain("Schema::hasColumn('suppliers', 'integracorp_alias')")
        ->toContain("unique('integracorp_alias'");
});

it('impide habilitar la gestión desde la ficha si el proveedor no tiene alias', function (): void {
    $view = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/Suppliers/Pages/ViewSupplier.php');

    expect($view)
        ->toContain('SupplierIntegracorpManagement::normalizeAlias($supplier->integracorp_alias) === null')
        ->toContain('Falta el alias del proveedor');
});
