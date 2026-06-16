<?php

declare(strict_types=1);

use App\Models\Permission;
use App\Support\Filament\UserFormPermissionOptions;
use Illuminate\Support\Collection;

it('oculta permisos nav duplicados cuando existe un permiso legacy equivalente', function (): void {
    $permissions = new Collection([
        tap(new Permission, fn (Permission $permission) => $permission->forceFill(['id' => 1, 'name' => 'Colaboradores', 'slug' => 'colaboradores', 'module' => 'MARKETING'])),
        tap(new Permission, fn (Permission $permission) => $permission->forceFill(['id' => 2, 'name' => 'Colaboradores', 'slug' => 'nav.marketing.rrhhcolaboradorresource', 'module' => 'MARKETING'])),
        tap(new Permission, fn (Permission $permission) => $permission->forceFill(['id' => 3, 'name' => 'Helpdesk', 'slug' => 'nav.marketing.helpdeskresource', 'module' => 'MARKETING'])),
    ]);

    $assignable = UserFormPermissionOptions::filterAssignable($permissions);

    expect($assignable->pluck('id')->all())->toBe([1, 3]);
});

it('conserva permisos nav cuando no hay equivalente legacy en el modulo', function (): void {
    $permissions = new Collection([
        tap(new Permission, fn (Permission $permission) => $permission->forceFill(['id' => 1, 'name' => 'Acceso al panel Operaciones', 'slug' => 'panel.operations', 'module' => 'OPERACIONES'])),
        tap(new Permission, fn (Permission $permission) => $permission->forceFill(['id' => 2, 'name' => 'Pacientes', 'slug' => 'nav.operations.telemedicinepatientresource', 'module' => 'OPERACIONES'])),
    ]);

    $assignable = UserFormPermissionOptions::filterAssignable($permissions);

    expect($assignable)->toHaveCount(2);
});

it('formulario de usuario usa el filtro de permisos asignables', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    expect($php)->toContain('UserFormPermissionOptions::optionsForModule');
});
