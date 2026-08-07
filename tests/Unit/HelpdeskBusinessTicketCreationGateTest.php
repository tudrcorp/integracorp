<?php

declare(strict_types=1);

use App\Models\HelpdeskGroup;
use App\Models\User;
use App\Support\HelpdeskBusinessTicketCreationDenialReason;
use App\Support\HelpdeskBusinessTicketCreationVerdict;
use App\Support\HelpdeskTicketCreationGate;
use App\Support\HelpdeskUserAccess;

it('extrae ids de integrantes del grupo de trabajo', function (): void {
    $group = new HelpdeskGroup([
        'status' => 'ACTIVO',
        'team_members' => [
            ['id' => 10, 'name' => 'Integrante Uno'],
            ['id' => 20, 'name' => 'Integrante Dos'],
            ['id' => 0, 'name' => 'Inválido'],
        ],
    ]);

    expect($group->memberColaboradorIds())->toBe([10, 20])
        ->and($group->isActive())->toBeTrue();
});

it('reconoce grupo inactivo', function (): void {
    $group = new HelpdeskGroup(['status' => 'INACTIVO']);

    expect($group->isActive())->toBeFalse();
});

it('en business cualquier usuario autenticado puede crear sin grupo ni cuota', function (): void {
    $user = new User(['departament' => ['SISTEMAS']]);

    expect(HelpdeskUserAccess::hasSystemsDepartment($user))->toBeTrue()
        ->and(HelpdeskTicketCreationGate::allowsCreation($user)->allowed)->toBeTrue();
});

it('oculta el boton crear ticket solo cuando no hay sesion', function (): void {
    $verdict = HelpdeskBusinessTicketCreationVerdict::denied(
        'Debe iniciar sesión para crear un ticket.',
        denialReason: HelpdeskBusinessTicketCreationDenialReason::UNAUTHENTICATED,
    );

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->shouldShowCreateTicketButton())->toBeFalse();
});

it('muestra el boton crear ticket cuando la creacion esta permitida', function (): void {
    $verdict = HelpdeskBusinessTicketCreationVerdict::allowed();

    expect($verdict->allowed)->toBeTrue()
        ->and($verdict->shouldShowCreateTicketButton())->toBeTrue();
});

it('panel business ya no impone cuota al crear tickets', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/HelpdeskResource.php';
    $createPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/CreateHelpdesk.php';
    $formPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupFormSchema.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/helpdesks/work-groups-modal.blade.php';
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Concerns/ManagesHelpdeskWorkGroupsOnList.php';
    $gatePath = dirname(__DIR__, 2).'/app/Support/HelpdeskTicketCreationGate.php';
    $groupPath = dirname(__DIR__, 2).'/app/Models/HelpdeskGroup.php';

    expect(file_get_contents($resourcePath))
        ->toContain('AuthorizesHelpdeskTicketCreation');

    expect(file_get_contents($createPath))
        ->toContain('AssertsHelpdeskTicketCreationAccess')
        ->not->toContain('helpdeskTicketCreationEnforcesQuota');

    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';

    expect(file_get_contents($listPath))
        ->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskCreateTicketHeaderAction.php'))
        ->toContain('canSeeCreateTicketButton()')
        ->toContain('HelpdeskTicketCreationGate::allowsCreation')
        ->toContain('No puede crear tickets');

    expect(file_get_contents($formPath))
        ->not->toContain('total_tickets_assigned')
        ->not->toContain('Cuota de tickets');

    expect(file_get_contents($modalPath))
        ->not->toContain('mountUpdateHelpdeskWorkGroupQuota')
        ->not->toContain('ticketsCreatedCount')
        ->not->toContain('Actualizar cuota');

    expect(file_get_contents($traitPath))
        ->not->toContain('updateHelpdeskWorkGroupQuotaAction')
        ->toContain('editHelpdeskWorkGroupAction');

    expect(file_get_contents($gatePath))
        ->not->toContain('DEFAULT_GROUP_QUOTA')
        ->not->toContain('enforceGroupQuota');

    expect(file_get_contents($groupPath))
        ->not->toContain('total_tickets_assigned')
        ->not->toContain('ticketsCreatedCount');

    expect(file_exists(dirname(__DIR__, 2).'/app/Support/HelpdeskBusinessTicketCreationGate.php'))->toBeFalse();
});
