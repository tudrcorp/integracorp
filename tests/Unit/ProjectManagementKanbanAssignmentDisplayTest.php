<?php

declare(strict_types=1);

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Group;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityAssignmentDisplay;
use App\Support\Filament\ProjectManagement\ProjectManagementCollaboratorAvatar;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupMembers;

it('limita avatares visibles y genera linea de nombres con overflow', function (): void {
    $members = [
        ['id' => 1, 'name' => 'Ana Pérez', 'initials' => 'AP', 'avatar_url' => null],
        ['id' => 2, 'name' => 'Luis Gómez', 'initials' => 'LG', 'avatar_url' => null],
        ['id' => 3, 'name' => 'María Ruiz', 'initials' => 'MR', 'avatar_url' => null],
        ['id' => 4, 'name' => 'Pedro Díaz', 'initials' => 'PD', 'avatar_url' => null],
        ['id' => 5, 'name' => 'Sofía Leal', 'initials' => 'SL', 'avatar_url' => null],
    ];

    $visible = array_slice($members, 0, ProjectManagementActivityAssignmentDisplay::MAX_VISIBLE_AVATARS);
    $overflow = count($members) - count($visible);

    expect($visible)->toHaveCount(4)
        ->and($overflow)->toBe(1)
        ->and(ProjectManagementCollaboratorAvatar::namesLine($members, $overflow))
        ->toContain('y 1 más');
});

it('resuelve integrantes de equipo desde collaborator_ids del grupo o snapshot en actividad', function (): void {
    $group = new Group([
        'id' => 2,
        'name' => 'EQUIPO 1',
        'collaborator_ids' => [1, 2, 3, 4, 5],
    ]);

    $activity = new Activity([
        'assignment_type' => 'team',
        'executor_type' => Group::class,
        'executor_id' => 2,
        'assigned_collaborator_ids' => [],
    ]);
    $activity->setRelation('executor', $group);

    expect(ProjectManagementGroupMembers::memberIdsForActivity($activity, $group))
        ->toHaveCount(5);

    $visible = array_slice([1, 2, 3, 4, 5], 0, ProjectManagementActivityAssignmentDisplay::MAX_VISIBLE_AVATARS);
    $overflow = max(0, 5 - count($visible));

    expect($visible)->toHaveCount(4)
        ->and($overflow)->toBe(1);
});

it('usa snapshot de colaboradores en actividad cuando el grupo no tiene integrantes', function (): void {
    $group = new Group([
        'id' => 2,
        'name' => 'EQUIPO 1',
        'collaborator_ids' => null,
    ]);

    $activity = new Activity([
        'assignment_type' => 'team',
        'executor_type' => Group::class,
        'executor_id' => 2,
        'assigned_collaborator_ids' => [1, 2, 3, 4, 5],
    ]);

    expect(ProjectManagementGroupMembers::memberIdsForActivity($activity, $group))
        ->toBe([1, 2, 3, 4, 5]);
});

it('registra stack de avatares tdg en kanban de actividades', function (): void {
    $kanbanView = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/projects/pages/kanban.blade.php');
    $assigneesPartial = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/projects/kanban-activity-assignees.blade.php');
    $sharedStack = file_get_contents(dirname(__DIR__, 2).'/resources/views/components/collaborator-avatar-stack.blade.php');
    $kanbanPage = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Kanban.php');

    expect($kanbanView)->toContain('x-projects.kanban-activity-assignees');

    expect($assigneesPartial)
        ->toContain('x-collaborator-avatar-stack')
        ->toContain('all_members');

    expect($sharedStack)
        ->toContain('tdg-calendar-avatar-stack__item')
        ->toContain('tdg-calendar-avatar-stack__overflow')
        ->toContain('-space-x-2');

    expect($kanbanPage)
        ->toContain('ProjectManagementActivityAssignmentDisplay::preload');
});
