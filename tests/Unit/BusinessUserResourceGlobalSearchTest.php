<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Users\UserResource;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Support\Filament\DepartmentNavigationPermissionRegistry;

it('incluye usuarios en la búsqueda global del panel de negocios', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Users/UserResource.php');

    expect($src)->not->toBeFalse()
        ->toContain('ConfiguresBusinessGlobalSearch')
        ->toContain('AuthorizesDepartmentNavigation')
        ->toContain('businessGlobalSearchSelectColumns')
        ->toContain('identity_card')
        ->toContain("'email'")
        ->toContain("'phone'")
        ->toContain('getGlobalSearchResultDetails')
        ->not->toContain('password');

    expect(UserResource::getGloballySearchableAttributes())
        ->toContain('name')
        ->toContain('email')
        ->toContain('phone')
        ->toContain('identity_card');
});

it('mantiene el recurso de usuarios reservado a SUPERADMIN', function (): void {
    expect(DepartmentNavigationPermissionRegistry::isSuperAdminOnly(UserResource::class))->toBeTrue()
        ->and(in_array(AuthorizesDepartmentNavigation::class, class_uses_recursive(UserResource::class), true))->toBeTrue();
});
