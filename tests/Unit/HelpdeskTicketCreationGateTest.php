<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\HelpdeskTicketCreationGate;
use App\Support\HelpdeskUserAccess;

it('permite crear tickets a usuarios con departamento SUPERADMIN sin grupo', function (): void {
    $user = new User;
    $user->departament = ['SUPERADMIN'];

    expect(HelpdeskUserAccess::hasSuperAdminDepartment($user))->toBeTrue()
        ->and(HelpdeskTicketCreationGate::allowsCreation($user)->allowed)->toBeTrue()
        ->and(HelpdeskTicketCreationGate::allowsCreation($user)->bypassesQuota)->toBeTrue();
});

it('reconoce SUPERADMIN aunque el valor del departamento tenga separadores', function (): void {
    $user = new User;
    $user->departament = ['Super Admin'];

    expect(HelpdeskUserAccess::hasSuperAdminDepartment($user))->toBeTrue();
});

it('exige grupo activo si el usuario no es SUPERADMIN', function (): void {
    $gate = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTicketCreationGate.php');

    expect($gate)
        ->toContain('hasSuperAdminDepartment($user)')
        ->toContain('findActiveGroupForColaborador')
        ->toContain('MISSING_GROUP');
});

it('departamento SISTEMAS sin SUPERADMIN tambien requiere grupo para crear', function (): void {
    $gate = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTicketCreationGate.php');

    expect($gate)
        ->toContain('if (HelpdeskUserAccess::hasSuperAdminDepartment($user))')
        ->not->toContain('if (HelpdeskUserAccess::hasSystemsDepartment($user)) {
            return HelpdeskBusinessTicketCreationVerdict::allowed(
                bypassReason:');
});

it('paneles helpdesk comparten autorizacion de creacion por grupo', function (string $panel): void {
    $resourcePath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";
    $createPath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $listPath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php";

    $traitPath = dirname(__DIR__, 2).'/app/Filament/Concerns/AuthorizesHelpdeskTicketCreation.php';

    expect(file_get_contents($resourcePath))->toContain('AuthorizesHelpdeskTicketCreation');

    expect(file_get_contents($traitPath))
        ->toContain('canSeeCreateTicketButton')
        ->toContain('HelpdeskTicketCreationGate::allowsCreation');

    expect(file_get_contents($createPath))
        ->toContain('AssertsHelpdeskTicketCreationAccess');

    $listContents = file_get_contents($listPath);

    if ($panel === 'Business') {
        expect($listContents)->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');
    } else {
        expect($listContents)->toContain('HelpdeskCreateTicketHeaderAction::make');
    }
})->with(['Business', 'Administration', 'Operations', 'Marketing']);
