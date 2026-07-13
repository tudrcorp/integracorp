<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Panel;

uses(Tests\TestCase::class);

it('expone el flag is_proveedor_amd separado de los módulos', function (): void {
    $user = new User([
        'is_proveedor_amd' => true,
        'departament' => ['OPERACIONES'],
    ]);

    expect($user->isProveedorAmd())->toBeTrue()
        ->and($user->departament)->toBe(['OPERACIONES'])
        ->and($user->departament)->not->toContain('PROVEEDOR AMD');
});

it('permite acceso al panel operations a usuarios proveedor con el flag', function (): void {
    $panel = Mockery::mock(Panel::class);
    $panel->shouldReceive('getId')->andReturn('operations');

    $user = new User([
        'status' => 'ACTIVO',
        'supplier_id' => 10,
        'departament' => [],
        'is_proveedor_amd' => true,
        'email' => 'proveedor@externo.com',
    ]);

    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('migra PROVEEDOR AMD desde departament hacia is_proveedor_amd', function (): void {
    $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_07_13_100832_add_is_proveedor_amd_to_users_table.php');

    expect($migration)
        ->toContain("boolean('is_proveedor_amd')")
        ->toContain('PROVEEDOR AMD')
        ->toContain('backfillFromLegacyDepartament');
});
