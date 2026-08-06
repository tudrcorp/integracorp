<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\PortalHelpContacts\Actions\DeletePortalHelpContactAction;
use App\Filament\Operations\Resources\PortalHelpContacts\PortalHelpContactResource;
use App\Models\PortalHelpContact;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;
use App\Support\Filament\UserFormPermissionOptions;

it('registra el recurso de contactos ayuda portal en permisos de operaciones', function (): void {
    expect(DepartmentNavigationPermissionRegistry::slugsFor(PortalHelpContactResource::class))
        ->toBe(['contactos-ayuda-portal'])
        ->and(UserFormPermissionOptions::navToLegacySlugAliases()['portalhelpcontactresource'] ?? null)
        ->toBe(['contactos-ayuda-portal']);
});

it('exige motivo y registra auditoría al eliminar un contacto de ayuda', function (): void {
    $actionPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/Actions/DeletePortalHelpContactAction.php';
    $contents = file_get_contents($actionPath);

    expect($contents)
        ->toContain('deletion_reason')
        ->toContain('SecurityAudit::log')
        ->toContain('AUDIT_OPERATIONS_PORTAL_HELP_CONTACT_DELETED')
        ->toContain('operations.portal-help-contacts.delete')
        ->toContain('minLength(10)');
});

it('expone el contacto inicial de ayuda en la migración', function (): void {
    $migrationPath = dirname(__DIR__, 2).'/database/migrations/2026_08_06_081321_create_portal_help_contacts_table.php';
    $contents = file_get_contents($migrationPath);

    expect($contents)
        ->toContain('portal_help_contacts')
        ->toContain('MediChat')
        ->toContain('+584242132112')
        ->toContain('sort_order');
});

it('define el modelo de contacto de ayuda con scopes activo y ordered', function (): void {
    expect(class_exists(PortalHelpContact::class))->toBeTrue()
        ->and(method_exists(PortalHelpContact::class, 'scopeActive'))->toBeTrue()
        ->and(method_exists(PortalHelpContact::class, 'scopeOrdered'))->toBeTrue()
        ->and((new PortalHelpContact)->getFillable())
        ->toContain('name')
        ->toContain('phone')
        ->toContain('sort_order')
        ->toContain('status');
});

it('usa delete action con motivo en tabla y edición', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/Tables/PortalHelpContactsTable.php';
    $editPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/Pages/EditPortalHelpContact.php';

    expect(file_get_contents($tablePath))
        ->toContain('DeletePortalHelpContactAction::make()')
        ->and(file_get_contents($editPath))
        ->toContain('DeletePortalHelpContactAction::make()')
        ->and(class_exists(DeletePortalHelpContactAction::class))->toBeTrue();
});

it('configura el recurso en el grupo CONFIGURACION del panel operaciones', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/PortalHelpContactResource.php';
    $contents = file_get_contents($resourcePath);

    expect($contents)
        ->toContain("'CONFIGURACION'")
        ->toContain('Contactos Ayuda Portal')
        ->toContain('AuthorizesDepartmentNavigation');
});

it('aplica el estilo del formulario de paciente en el schema de contactos', function (): void {
    $formPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/Schemas/PortalHelpContactForm.php';
    $contents = file_get_contents($formPath);

    expect($contents)
        ->toContain('TABS_CONTAINER')
        ->toContain('SECTION_CARD')
        ->toContain('rounded-[1.75rem]')
        ->toContain('rounded-[1.5rem]')
        ->toContain('persistTab')
        ->toContain('Tabs::make')
        ->toContain('Fieldset::make')
        ->toContain('prefixIcon')
        ->toContain('Heroicon::OutlinedPhone')
        ->toContain('Heroicon::OutlinedEye')
        ->toContain("Tab::make('Contacto')")
        ->toContain("Tab::make('Publicación')")
        ->not->toContain('fi-supplier-status-tabs-ios')
        ->not->toContain('fi-supplier-status-tab-pill')
        ->not->toContain('contained(false)');
});

it('usa acciones de creación alineadas al estilo de afiliaciones', function (): void {
    $createPath = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/PortalHelpContacts/Pages/CreatePortalHelpContact.php';
    $contents = file_get_contents($createPath);

    expect($contents)
        ->toContain("Action::make('regresar')")
        ->toContain('bg-indigo-600')
        ->toContain('FilamentIosButton');
});
