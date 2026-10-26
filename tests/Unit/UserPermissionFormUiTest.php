<?php

declare(strict_types=1);

it('ui de permisos incluye estilos y temas por modulo', function (): void {
    $form = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');
    $ui = file_get_contents(__DIR__.'/../../app/Support/Filament/UserPermissionFormUi.php');
    $styles = file_get_contents(__DIR__.'/../../resources/views/filament/business/users/partials/permission-form-styles.blade.php');

    $catalog = file_get_contents(__DIR__.'/../../app/Support/Filament/UserPermissionCatalog.php');

    expect($form)->toContain('UserPermissionFormUi::permissionsIntroHtml')
        ->and($catalog)->toContain('UserPermissionFormUi::moduleDisplayLabel')
        ->and($catalog)->toContain('UserPermissionFormUi::moduleAccent')
        ->and($ui)->toContain('user-perm-module-badge')
        ->and($ui)->toContain('user-perm-panel-')
        ->and($ui)->toContain('user-perm-stat-pill')
        ->and($styles)->toContain('.dark .user-perm-intro')
        ->and($styles)->toContain('.user-perm-module--negocios { border-left:')
        ->and($styles)->toContain('.user-perm-checkbox-list .fi-fo-checkbox-list-option:has(.fi-checkbox-input:checked)')
        ->and($ui)->toContain('pestaña <strong>Módulos</strong>');
});
