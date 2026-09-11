<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Models\Permission;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\PermissionNavigationGroupResolver;
use App\Support\HelpdeskTicketReassignment;
use Illuminate\Foundation\Testing\WithFaker;

uses(WithFaker::class, Tests\TestCase::class);

function makeHelpdeskReassignmentUser(array $departments, array $permissionSlugs = [], string $permissionModule = 'NEGOCIOS'): User
{
    $user = new User;
    $user->forceFill([
        'id' => fake()->unique()->randomNumber(5),
        'name' => 'Analista Helpdesk',
        'email' => 'analista@tudrencasa.com',
        'departament' => $departments,
        'status' => 'ACTIVO',
    ]);

    $permissions = collect();

    foreach ($permissionSlugs as $slug) {
        $permissions->push(
            tap(new Permission, fn (Permission $permission) => $permission->forceFill([
                'id' => fake()->unique()->randomNumber(5),
                'name' => $slug,
                'slug' => $slug,
                'module' => $permissionModule,
            ]))
        );
    }

    $user->setRelation('permissions', $permissions);

    return $user;
}

function makeHelpdeskTicketForReassignment(string $status, array $assigneeUserIds = []): HelpDesk
{
    $ticket = new HelpDesk;
    $ticket->forceFill([
        'status' => $status,
        'created_by' => 'Creador',
        'priority' => 'MEDIA',
    ]);

    $colaboradores = collect();

    foreach ($assigneeUserIds as $index => $userId) {
        $colaborador = new RrhhColaborador;
        $colaborador->forceFill([
            'id' => $index + 1,
            'fullName' => 'Colaborador '.($index + 1),
            'user_id' => $userId,
        ]);
        $colaboradores->push($colaborador);
    }

    $ticket->setRelation('rrhhColaboradores', $colaboradores);

    return $ticket;
}

it('registra el permiso de reasignar tickets en los paneles de helpdesk', function (): void {
    $slug = BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET;
    $definition = BusinessFilamentActionPermissionRegistry::all()[$slug];

    expect($slug)->toBe('reasignar-tickets-helpdesk')
        ->and($definition['name'])->toBe('Reasignar tickets')
        ->and($definition['group'])->toBe('HELPDESK')
        ->and(BusinessFilamentActionPermissionRegistry::modulesForSlug($slug))
        ->toBe(['NEGOCIOS', 'ADMINISTRACION', 'OPERACIONES', 'MARKETING']);
});

it('agrupa el permiso de reasignar tickets en HELPDESK', function (string $module): void {
    $permission = new Permission;
    $permission->forceFill([
        'slug' => BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET,
        'module' => $module,
        'name' => 'Reasignar tickets',
    ]);

    expect(PermissionNavigationGroupResolver::groupForPermission($permission))->toBe('HELPDESK');
})->with(['NEGOCIOS', 'ADMINISTRACION', 'OPERACIONES', 'MARKETING']);

it('permite reasignar a superadmin aunque no este asignado', function (): void {
    $user = makeHelpdeskReassignmentUser(['SUPERADMIN', 'NEGOCIOS']);
    $ticket = makeHelpdeskTicketForReassignment('EN PROCESO', [999]);

    expect(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeTrue();
});

it('permite reasignar al asignado con el permiso concedido', function (): void {
    $user = makeHelpdeskReassignmentUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET],
    );
    $ticket = makeHelpdeskTicketForReassignment('EN PROCESO', [(int) $user->id]);

    expect(HelpdeskTicketReassignment::userIsAssignee($user, $ticket))->toBeTrue()
        ->and(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeTrue();
});

it('niega reasignar al asignado sin el permiso', function (): void {
    $user = makeHelpdeskReassignmentUser(['NEGOCIOS'], ['helpdesks']);
    $ticket = makeHelpdeskTicketForReassignment('EN PROCESO', [(int) $user->id]);

    expect(HelpdeskTicketReassignment::userIsAssignee($user, $ticket))->toBeTrue()
        ->and(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeFalse();
});

it('niega reasignar a quien tiene el permiso pero no esta asignado', function (): void {
    $user = makeHelpdeskReassignmentUser(
        ['NEGOCIOS'],
        [BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET],
    );
    $ticket = makeHelpdeskTicketForReassignment('EN PROCESO', [888]);

    expect(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeFalse();
});

it('niega reasignar tickets terminados o cancelados', function (string $status): void {
    $user = makeHelpdeskReassignmentUser(['SUPERADMIN', 'NEGOCIOS']);
    $ticket = makeHelpdeskTicketForReassignment($status, [(int) $user->id]);

    expect(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeFalse();
})->with(['TERMINADO', 'CANCELADO']);

it('no concede el permiso de negocios en el panel de operaciones', function (): void {
    $user = makeHelpdeskReassignmentUser(
        ['NEGOCIOS', 'OPERACIONES'],
        [BusinessFilamentActionPermissionRegistry::REASSIGN_HELPDESK_TICKET],
        'NEGOCIOS',
    );
    $ticket = makeHelpdeskTicketForReassignment('EN PROCESO', [(int) $user->id]);

    expect(HelpdeskTicketReassignment::userCan($user, $ticket, 'business'))->toBeTrue()
        ->and(HelpdeskTicketReassignment::userCan($user, $ticket, 'operations'))->toBeFalse();
});

it('normaliza y diferencia ids de asignados', function (): void {
    expect(HelpdeskTicketReassignment::normalizeIds(['3', 1, 1, 0, 'x', 2]))->toBe([1, 2, 3])
        ->and(HelpdeskTicketReassignment::diffIds([1, 2], [2, 3]))
        ->toBe([
            'added' => [3],
            'removed' => [1],
            'unchanged' => [2],
        ]);
});

it('exige motivo de reasignacion', function (): void {
    expect(HelpdeskTicketReassignment::validateExplanation('<p>  </p>'))
        ->toMatchArray([
            'title' => 'Motivo requerido',
        ])
        ->and(HelpdeskTicketReassignment::validateExplanation('<p>Traslado al equipo de sistemas</p>'))
        ->toBeNull();
});

it('arma la nota de reasignacion con responsables y motivo', function (): void {
    $html = HelpdeskTicketReassignment::buildObservationHtml(
        ['Ana Pérez'],
        ['Luis Mora', 'Carla Díaz'],
        '<p>El caso corresponde a sistemas.</p>',
    );

    expect($html)
        ->toContain('Ticket reasignado')
        ->toContain('Ana Pérez')
        ->toContain('Luis Mora, Carla Díaz')
        ->toContain('Motivo de la reasignación')
        ->toContain('El caso corresponde a sistemas.');
});

it('formatea lista vacia de responsables como sin asignar', function (): void {
    expect(HelpdeskTicketReassignment::formatNameList([]))->toBe('Sin asignar');
});

it('la accion modal de reasignar vive en el modulo compartido y en los paneles', function (): void {
    $shared = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php');
    $support = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTicketReassignment.php');

    expect($shared)
        ->toContain('function makeReassignAction')
        ->toContain("Action::make('reassignTicket')")
        ->toContain("->label('Reasignar ticket')")
        ->toContain('HelpdeskTicketReassignment::apply')
        ->toContain('HelpdeskTicketReassignment::userCan')
        ->and($support)
        ->toContain('AUDIT_HELPDESK_TICKET_REASSIGNED')
        ->toContain('TYPE_ASSIGNMENT_CHANGE')
        ->toContain('rrhhColaboradores()->sync');

    foreach (['Business', 'Administration', 'Operations', 'Marketing'] as $panel) {
        $wrapper = file_get_contents(dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php");
        $view = file_get_contents(dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/ViewHelpdesk.php");
        $edit = file_get_contents(dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Pages/EditHelpdesk.php");

        expect($wrapper)->toContain('makeReassignAction')
            ->and($view)->toContain('HelpdeskTicketModalActions::makeReassignAction()')
            ->and($edit)->toContain('HelpdeskTicketModalActions::makeReassignAction()');
    }
});
