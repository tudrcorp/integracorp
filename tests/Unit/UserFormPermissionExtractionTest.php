<?php

declare(strict_types=1);

it('centraliza la extraccion de permisos filtrando por modulos asignados', function (): void {
    $userForm = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    expect($userForm)->not->toBeFalse()
        ->toContain('public static function extractPermissionIdsFromState')
        ->toContain('if (! in_array($module, $departments, true))')
        ->toContain('continue;');
});

it('editar y crear usuario sincronizan permisos sin reinyectar defaults de analista', function (): void {
    $editUser = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/EditUser.php');
    $createUser = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/CreateUser.php');

    expect($editUser)->not->toBeFalse()
        ->not->toContain('mergeAnalystDefaultPermissionIds')
        ->toContain('UserForm::extractPermissionIdsFromState')
        ->toContain('syncWithPivotValues');

    expect($createUser)->not->toBeFalse()
        ->not->toContain('mergeAnalystDefaultPermissionIds')
        ->toContain('UserForm::extractPermissionIdsFromState');
});
