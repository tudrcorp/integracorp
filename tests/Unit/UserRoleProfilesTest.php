<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Filament\UserRoleProfiles;

it('calcula perfiles activos del usuario', function (): void {
    $user = new User;
    $user->forceFill([
        'is_admin' => true,
        'is_agent' => false,
        'is_designer' => true,
    ]);

    expect(UserRoleProfiles::activeCount($user))->toBe(2)
        ->and(UserRoleProfiles::totalCount())->toBe(9);
});

it('formulario de roles usa tarjetas mejoradas', function (): void {
    $form = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');
    $ui = file_get_contents(__DIR__.'/../../app/Support/Filament/UserRoleFormUi.php');
    $styles = file_get_contents(__DIR__.'/../../resources/views/filament/business/users/partials/role-form-styles.blade.php');

    expect($form)->toContain('UserRoleFormUi::stylesView()')
        ->and($form)->toContain('UserRoleFormUi::groupShellClass')
        ->and($ui)->toContain('user-role-toggle-card')
        ->and($styles)->toContain('.user-role-toggle-card.fi-fo-field:has([aria-checked="true"])')
        ->and($styles)->toContain('.user-role-group--comerciales');
});

it('infolist de usuario usa perfiles agrupados con badges', function (): void {
    $infolist = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserInfolist.php');
    $profiles = file_get_contents(__DIR__.'/../../app/Support/Filament/UserRoleProfiles.php');

    expect($infolist)->toContain('UserRoleProfiles::summaryEntry')
        ->toContain('roleGroupSections');

    expect($profiles)->toContain('infolistEntriesForGroup')
        ->toContain('Perfiles comerciales');
});
