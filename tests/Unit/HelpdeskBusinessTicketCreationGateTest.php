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

it('permite crear tickets a usuarios del departamento sistemas sin cuota', function (): void {
    $user = new User;
    $user->departament = ['SISTEMAS'];

    expect(HelpdeskUserAccess::hasSystemsDepartment($user))->toBeTrue()
        ->and(HelpdeskBusinessTicketCreationGate::allowsCreation($user)->allowed)->toBeTrue()
        ->and(HelpdeskBusinessTicketCreationGate::allowsCreation($user)->bypassesQuota)->toBeTrue();
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
        ->toContain('HelpdeskBusinessTicketCreationGate::allowsCreation')
        ->toContain('public static function canCreate(): bool')
        ->toContain('canSeeCreateTicketButton');

    expect(file_get_contents($createPath))
        ->toContain('assertBusinessHelpdeskCreationAllowedOrHalt')
        ->toContain('assertBusinessHelpdeskCreationAllowedOrRedirect')
        ->toContain('public static function canAccess')
        ->toContain('HelpdeskResource::canCreate()');

    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';

    expect(file_get_contents($listPath))
        ->toContain('HelpdeskBusinessCreateTicketHeaderAction::make()');

    expect(file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskBusinessCreateTicketHeaderAction.php'))
        ->toContain('->visible(fn (): bool => HelpdeskResource::canSeeCreateTicketButton())')
        ->toContain('HelpdeskBusinessTicketCreationGate::allowsCreation()')
        ->toContain('No puede crear tickets');

    expect(file_get_contents($formPath))
        ->toContain('HelpdeskBusinessTicketCreationGate::DEFAULT_GROUP_QUOTA');

    expect(file_get_contents($modalPath))
        ->toContain('mountUpdateHelpdeskWorkGroupQuota')
        ->toContain('ticketsCreatedCount');

    expect(file_get_contents($traitPath))
        ->toContain('updateHelpdeskWorkGroupQuotaAction');
});
