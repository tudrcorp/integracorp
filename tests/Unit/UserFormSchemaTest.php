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
        ->toContain("Tab::make('Módulos')")
        ->toContain("Tab::make('Correo y contraseña')")
        ->toContain("Tab::make('Roles del usuario')")
        ->toContain("Tab::make('Permisos de red')")
        ->toContain("Tab::make('Permisos')")
        ->toContain('modulesTabSchema')
        ->toContain('UserModulesFormUi')
        ->toContain('user-modules-checkbox-list')
        ->toContain('CheckboxList::make(\'departament\')')
        ->toContain('->required(fn (Get $get, ?User $record): bool => ! CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record))')
        ->toContain('commercialNetworkPermissionsTabSchema')
        ->toContain('CommercialNetworkPermissionRegistry')
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
        ->toContain('identity_card')
        ->toContain('Documento de identidad')
        ->toContain('->collapsible()');
});

it('campo departament vive en la pestaña modulos y no en informacion del usuario', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');

    $infoTabStart = strpos($php, "Tab::make('Información del usuario')");
    $modulesTabStart = strpos($php, "Tab::make('Módulos')");
    $credentialsTabStart = strpos($php, "Tab::make('Correo y contraseña')");

    expect($infoTabStart)->not->toBeFalse()
        ->and($modulesTabStart)->not->toBeFalse()
        ->and($credentialsTabStart)->not->toBeFalse()
        ->and($infoTabStart)->toBeLessThan($modulesTabStart)
        ->and($modulesTabStart)->toBeLessThan($credentialsTabStart);

    $infoTabSection = substr($php, $infoTabStart, $modulesTabStart - $infoTabStart);

    expect($infoTabSection)->not->toContain("Select::make('departament')")
        ->and($infoTabSection)->not->toContain("CheckboxList::make('departament')");
});

it('modulos internos no son requeridos al asignar permisos de red a agente o agencia', function (): void {
    $php = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');
    $modulesTabStart = strpos($php, 'public static function modulesTabSchema');
    $configureStart = strpos($php, 'public static function configure(Schema $schema)');

    expect($php)->not->toBeFalse()
        ->and($modulesTabStart)->not->toBeFalse()
        ->and($configureStart)->not->toBeFalse();

    $modulesTab = substr($php, $modulesTabStart, $configureStart - $modulesTabStart);

    expect($modulesTab)
        ->toContain("CheckboxList::make('departament')")
        ->toContain('! CommercialNetworkAccess::formUserIsCommercialNetwork($get, $record)')
        ->toContain('->default([])')
        ->toContain('Los módulos internos son opcionales para agentes y agencias')
        ->toContain('Opcional. Si no asignas módulos internos');
});
