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

it('el formulario de roles usa tarjetas con la descripción real de cada rol', function (): void {
    $form = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserForm.php');
    $view = file_get_contents(__DIR__.'/../../resources/views/filament/forms/components/role-selector.blade.php');
    $catalog = collect(UserRoleProfiles::formCatalog())->keyBy('field');

    expect($form)->toContain("RoleSelector::make('roles_editor')")
        ->toContain('Hidden::make($role[\'field\'])->default(false)')
        ->and($catalog->keys()->all())->toBe(['is_agent', 'is_subagent', 'is_agency', 'is_accountManagers', 'is_business_admin', 'is_superAdmin'])
        ->and($catalog['is_superAdmin']['description'])->toContain('No da acceso total')
        ->and($catalog['is_accountManagers']['effect'])->toBe('restricts')
        ->and($catalog->where('live', true)->keys()->all())->toBe(['is_agent', 'is_agency'])
        ->and(array_column(UserRoleProfiles::readOnlyCatalog(), 'field'))->toBe(['is_admin', 'is_doctor', 'is_designer'])
        ->and($view)->toContain('wire:ignore')
        ->toContain('$wire.$entangle(@js($role[\'path\']), @js($role[\'live\']))')
        ->toContain('Restringe lo que ve')
        ->toContain('Otros indicadores');
});

it('infolist de usuario usa perfiles agrupados con badges', function (): void {
    $infolist = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Schemas/UserInfolist.php');
    $profiles = file_get_contents(__DIR__.'/../../app/Support/Filament/UserRoleProfiles.php');

    expect($infolist)->toContain('UserRoleProfiles::summaryEntry')
        ->toContain('roleGroupSections');

    expect($profiles)->toContain('infolistEntriesForGroup')
        ->toContain('Perfiles comerciales');
});
