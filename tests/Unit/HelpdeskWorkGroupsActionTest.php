<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\HelpdeskFormSchema;
use App\Support\HelpdeskUserAccess;
use App\Support\HelpdeskWorkGroupHeaderAction;

it('detecta departamento SISTEMAS en el arreglo de departamentos del usuario', function (): void {
    $user = new User;
    $user->departament = ['NEGOCIOS', 'SISTEMAS'];

    expect(HelpdeskUserAccess::hasSystemsDepartment($user))->toBeTrue();
});

it('detecta SISTEMAS como subcadena del departamento', function (): void {
    $user = new User;
    $user->departament = ['COORDINACION SISTEMAS TI'];

    expect(HelpdeskUserAccess::hasSystemsDepartment($user))->toBeTrue();
});

it('excluye a Cayetano Batres de las opciones de grupos de trabajo', function (): void {
    expect(HelpdeskFormSchema::isExcludedFromHelpdeskWorkGroups('CAYETANO BATRES'))->toBeTrue()
        ->and(HelpdeskFormSchema::isExcludedFromHelpdeskWorkGroups('CAYETANO, BATRES'))->toBeTrue()
        ->and(HelpdeskFormSchema::isExcludedFromHelpdeskWorkGroups('SOLEYDA RODRIGUEZ'))->toBeFalse();
});

it('rechaza usuarios sin departamento SISTEMAS', function (): void {
    $user = new User;
    $user->departament = ['NEGOCIOS', 'OPERACIONES'];

    expect(HelpdeskUserAccess::hasSystemsDepartment($user))->toBeFalse();
});

it('define la acción manageHelpdeskWorkGroups con visibilidad por departamento', function (): void {
    $actionPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupHeaderAction.php';
    $formPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupFormSchema.php';
    $listPath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Pages/ListHelpdesks.php';

    expect(file_get_contents($actionPath))
        ->toContain("Action::make('manageHelpdeskWorkGroups')")
        ->toContain('HelpdeskUserAccess::hasSystemsDepartment()')
        ->toContain('HelpdeskGroup::query()->create')
        ->toContain('HelpdeskWorkGroupValidator::validate')
        ->toContain('HelpdeskWorkGroupFormSchema::components()')
        ->not->toContain('total_tickets_assigned');

    expect(file_get_contents($formPath))
        ->toContain('rrhhColaboradorOptionsForHelpdeskWorkGroups')
        ->not->toContain('Cuota de tickets');

    expect(file_get_contents($listPath))
        ->toContain('HelpdeskWorkGroupHeaderAction::make()')
        ->toContain('ManagesHelpdeskWorkGroupsOnList');
});

it('registra vista modal de grupos de trabajo sin cuota', function (): void {
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/helpdesks/work-groups-modal.blade.php';

    expect(file_exists($viewPath))->toBeTrue();
    expect(file_get_contents($viewPath))
        ->toContain('mountDeleteHelpdeskWorkGroup')
        ->toContain('mountEditHelpdeskWorkGroup')
        ->not->toContain('mountUpdateHelpdeskWorkGroupQuota')
        ->not->toContain('total_tickets_assigned')
        ->not->toContain('ticketsCreatedCount')
        ->not->toContain('Actualizar cuota');
});

it('permite crear un grupo de trabajo sin cuota de tickets', function (): void {
    $actionPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupHeaderAction.php';
    $formPath = dirname(__DIR__, 2).'/app/Support/HelpdeskWorkGroupFormSchema.php';

    expect(file_get_contents($actionPath))
        ->toContain('HelpdeskWorkGroupFormSchema::components()')
        ->not->toContain('ticketQuota')
        ->toContain("'team_members' => \$validation->members");

    expect(file_get_contents($formPath))
        ->not->toContain("TextInput::make('total_tickets_assigned')")
        ->toContain("Checkbox::make('show_create_form')")
        ->toContain('Nuevo grupo de trabajo')
        ->toContain("Grid::make(['default' => 1, 'sm' => 2])")
        ->toContain("Action::make('submitCreateGroup')")
        ->toContain("->submit('callMountedAction')");

    expect(file_get_contents($actionPath))
        ->toContain('->modalSubmitAction(false)');
});

it('expone make en HelpdeskWorkGroupHeaderAction', function (): void {
    expect(method_exists(HelpdeskWorkGroupHeaderAction::class, 'make'))->toBeTrue();
});
