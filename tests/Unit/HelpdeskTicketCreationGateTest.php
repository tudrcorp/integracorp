<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\HelpdeskBusinessTicketCreationDenialReason;
use App\Support\HelpdeskTicketCreationGate;
use App\Support\HelpdeskUserAccess;

it('permite crear tickets a cualquier usuario autenticado', function (): void {
    $user = new User;
    $user->name = 'Usuario Integracorp';
    $user->departament = ['NEGOCIOS'];

    $verdict = HelpdeskTicketCreationGate::allowsCreation($user);

    expect($verdict->allowed)->toBeTrue()
        ->and($verdict->shouldShowCreateTicketButton())->toBeTrue();
});

it('permite crear tickets a usuarios con departamento SUPERADMIN', function (): void {
    $user = new User;
    $user->departament = ['SUPERADMIN'];

    expect(HelpdeskUserAccess::hasSuperAdminDepartment($user))->toBeTrue()
        ->and(HelpdeskTicketCreationGate::allowsCreation($user)->allowed)->toBeTrue();
});

it('reconoce SUPERADMIN aunque el valor del departamento tenga separadores', function (): void {
    $user = new User;
    $user->departament = ['Super Admin'];

    expect(HelpdeskUserAccess::hasSuperAdminDepartment($user))->toBeTrue();
});

it('deniega la creacion si el actor no es un usuario de la aplicacion', function (): void {
    $actor = new class implements \Illuminate\Contracts\Auth\Authenticatable
    {
        public function getAuthIdentifierName(): string
        {
            return 'id';
        }

        public function getAuthIdentifier(): mixed
        {
            return null;
        }

        public function getAuthPasswordName(): string
        {
            return 'password';
        }

        public function getAuthPassword(): string
        {
            return '';
        }

        public function getRememberToken(): ?string
        {
            return null;
        }

        public function setRememberToken($value): void {}

        public function getRememberTokenName(): string
        {
            return 'remember_token';
        }
    };

    $verdict = HelpdeskTicketCreationGate::allowsCreation($actor);

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->denialReason)->toBe(HelpdeskBusinessTicketCreationDenialReason::UNAUTHENTICATED)
        ->and($verdict->shouldShowCreateTicketButton())->toBeFalse();
});

it('ya no exige grupo rrhh ni cuota para crear tickets', function (): void {
    $gate = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTicketCreationGate.php');

    expect($gate)
        ->toContain('Usuario autenticado (sin restricción de creación).')
        ->not->toContain('MISSING_GROUP')
        ->not->toContain('MISSING_RRHH')
        ->not->toContain('QUOTA_EXHAUSTED')
        ->not->toContain('DEFAULT_GROUP_QUOTA');
});

it('paneles helpdesk comparten autorizacion abierta de creacion', function (string $panel): void {
    $resourcePath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";
    $createPath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/CreateHelpdesk.php";
    $listPath = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ListHelpdesks.php";

    $traitPath = dirname(__DIR__, 2).'/app/Filament/Concerns/AuthorizesHelpdeskTicketCreation.php';

    expect(file_get_contents($resourcePath))->toContain('AuthorizesHelpdeskTicketCreation');

    expect(file_get_contents($traitPath))
        ->toContain('canSeeCreateTicketButton')
        ->toContain('HelpdeskTicketCreationGate::allowsCreation')
        ->not->toContain('helpdeskEnforcesCreationQuota');

    expect(file_get_contents($createPath))
        ->toContain('AssertsHelpdeskTicketCreationAccess')
        ->not->toContain('helpdeskTicketCreationEnforcesQuota')
        ->not->toContain('PreparesHelpdeskTeamOnCreate');

    expect(file_get_contents($resourcePath))
        ->toContain('HelpdeskTicketVisibility::constrainVisible');

    $listContents = file_get_contents($listPath);

    if ($panel === 'Business') {
        expect($listContents)->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');
    } else {
        expect($listContents)->toContain('HelpdeskCreateTicketHeaderAction::make');
    }
})->with(['Business', 'Administration', 'Operations', 'Marketing']);
