<?php

declare(strict_types=1);

use App\Filament\Operations\Resources\OperationOnCallUsers\OperationOnCallUserResource;

it('oculta el recurso Roles de Guardia para todos los usuarios y roles', function (): void {
    expect(OperationOnCallUserResource::canAccess())->toBeFalse()
        ->and(OperationOnCallUserResource::shouldRegisterNavigation())->toBeFalse()
        ->and(OperationOnCallUserResource::canViewAny())->toBeFalse()
        ->and(OperationOnCallUserResource::canCreate())->toBeFalse()
        ->and(OperationOnCallUserResource::canDeleteAny())->toBeFalse();

    $resource = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationOnCallUsers/OperationOnCallUserResource.php');

    expect($resource)
        ->toContain('protected static bool $shouldRegisterNavigation = false')
        ->toContain('return false');
});
