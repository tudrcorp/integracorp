<?php

declare(strict_types=1);

use App\Support\Filament\Operations\SupplierIntegracorpManagement;

uses(Tests\TestCase::class);

it('define el repeater de usuarios en la tabla users', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/Filament/Operations/SupplierIntegracorpManagement.php';

    expect(file_get_contents($path))
        ->toContain("Repeater::make('integracorpUsers')")
        ->toContain("->relationship('integracorpUsers')")
        ->toContain("table: 'users'")
        ->toContain("TextInput::make('name')")
        ->toContain("TextInput::make('email')")
        ->not->toContain("TextInput::make('password')")
        ->toContain('DEFAULT_PORTAL_USER_PASSWORD')
        ->toContain("Hidden::make('departament')")
        ->not->toContain('supplier_integracorp_portal_users');
});

it('asigna departamentos fijos a usuarios portal de proveedor', function (): void {
    expect(SupplierIntegracorpManagement::portalUserDepartaments())
        ->toBe(['OPERACIONES', 'PROVEEDOR AMD']);
});

it('normaliza datos de usuario al crear y al editar', function (): void {
    $created = SupplierIntegracorpManagement::normalizeIntegracorpUserData([
        'name' => 'Ana',
        'email' => 'ana@test.com',
    ], creating: true);

    expect($created)
        ->toMatchArray([
            'name' => 'Ana',
            'email' => 'ana@test.com',
            'password' => '12345678',
            'departament' => ['OPERACIONES', 'PROVEEDOR AMD'],
            'status' => 'ACTIVO',
        ])
        ->toHaveKey('created_by')
        ->toHaveKey('updated_by');

    $updated = SupplierIntegracorpManagement::normalizeIntegracorpUserData([
        'name' => 'Ana',
        'password' => '',
    ], creating: false);

    expect($updated)
        ->not->toHaveKey('password')
        ->not->toHaveKey('created_by');
});

it('elimina la tabla intermedia de usuarios portal', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_06_03_120000_drop_supplier_integracorp_portal_users_table.php');

    expect($migration)
        ->toContain("Schema::dropIfExists('supplier_integracorp_portal_users')")
        ->toContain("DB::table('users')");
});
