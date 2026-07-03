<?php

declare(strict_types=1);

it('formulario de usuario usa pestañas del sistema y permisos mejorados', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    expect($php)->not->toBeFalse()
        ->toContain('Tabs::make')
        ->toContain('persistTab')
        ->toContain('TABS_CONTAINER')
        ->toContain("Tabs::make('userFormTabs')")
        ->toContain("Tab::make('Información del usuario')")
        ->toContain("Tab::make('Correo y contraseña')")
        ->toContain("Tab::make('Roles del usuario')")
        ->toContain("Tab::make('Permisos')")
        ->toContain('permissionModuleSections')
        ->toContain('groupedOptionsForModule')
        ->toContain('permissionGroupFieldKey')
        ->toContain('UserPermissionFormUi')
        ->toContain('user-perm-checkbox-list')
        ->toContain('getPermissionAssignableModules')
        ->toContain('PERMISSIONS_EXCLUDED_MODULES')
        ->toContain("'SISTEMAS'")
        ->toContain("'SUPERADMIN'")
        ->toContain("'TELEMEDICINA'")
        ->toContain('permissions_empty_state')
        ->toContain('bulkToggleable')
        ->toContain('->collapsible()');
});
