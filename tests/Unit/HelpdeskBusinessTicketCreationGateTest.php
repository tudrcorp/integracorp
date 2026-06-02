<?php

declare(strict_types=1);

use App\Models\HelpdeskGroup;
use App\Models\User;
use App\Support\HelpdeskBusinessTicketCreationDenialReason;
use App\Support\HelpdeskBusinessTicketCreationGate;
use App\Support\HelpdeskBusinessTicketCreationVerdict;
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

it('en business el departamento sistemas sin grupo no puede crear hasta pertenecer a un grupo', function (): void {
    $gate = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTicketCreationGate.php');

    $businessGate = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskBusinessTicketCreationGate.php');

    expect(HelpdeskUserAccess::hasSystemsDepartment(new User(['departament' => ['SISTEMAS']])))->toBeTrue()
        ->and($businessGate)->toContain('enforceGroupQuota: true')
        ->and($gate)->toContain('hasSystemsDepartment($user)');
});

it('define cuota por defecto de cinco tickets para grupos nuevos', function (): void {
    expect(HelpdeskBusinessTicketCreationGate::DEFAULT_GROUP_QUOTA)->toBe(5);
});

it('muestra el boton crear ticket sin grupo pero bloquea el formulario', function (): void {
    $verdict = HelpdeskBusinessTicketCreationVerdict::denied(
        'Comuníquese con el Departamento de Tecnología para ser incluido en un grupo de trabajo.',
        denialReason: HelpdeskBusinessTicketCreationDenialReason::MISSING_GROUP,
    );

    expect($verdict->allowed)->toBeFalse()
        ->and($verdict->shouldShowCreateTicketButton())->toBeTrue();
});

it('oculta el boton crear ticket cuando la cuota del grupo esta agotada', function (): void {
    $verdict = HelpdeskBusinessTicketCreationVerdict::denied(
        'Cuota agotada.',
        denialReason: HelpdeskBusinessTicketCreationDenialReason::QUOTA_EXHAUSTED,
    );

    expect($verdict->shouldShowCreateTicketButton())->toBeFalse();
});

it('panel business valida creacion con la regla de grupo y cuota', function (): void {
    $resourcePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/HelpdeskResource.php';
    $createPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/CreateHelpdesk.php';
    $formPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupFormSchema.php';
    $modalPath = dirname(__DIR__, 2).'/resources/views/filament/helpdesks/work-groups-modal.blade.php';
    $traitPath = dirname(__DIR__, 2).'/app/Filament/Concerns/ManagesHelpdeskWorkGroupsOnList.php';

    expect(file_get_contents($resourcePath))
        ->toContain('AuthorizesHelpdeskTicketCreation')
        ->toContain('helpdeskEnforcesCreationQuota');

    expect(file_get_contents($createPath))
        ->toContain('AssertsHelpdeskTicketCreationAccess')
        ->toContain('helpdeskTicketCreationEnforcesQuota');

    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';

    expect(file_get_contents($listPath))
        ->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskCreateTicketHeaderAction.php'))
        ->toContain('canSeeCreateTicketButton()')
        ->toContain('HelpdeskTicketCreationGate::allowsCreation')
        ->toContain('No puede crear tickets');

    expect(file_get_contents($formPath))
        ->toContain('HelpdeskBusinessTicketCreationGate::DEFAULT_GROUP_QUOTA');

    expect(file_get_contents($modalPath))
        ->toContain('mountUpdateHelpdeskWorkGroupQuota')
        ->toContain('ticketsCreatedCount');

    expect(file_get_contents($traitPath))
        ->toContain('updateHelpdeskWorkGroupQuotaAction');
});
