<?php

declare(strict_types=1);

use App\Models\ProjectManagement\Group;
use App\Support\Filament\ProjectManagement\ProjectManagementGroupInfolistDisplay;
use Tests\TestCase;

uses(TestCase::class);

it('consolida integrantes resaltados en el tab general del infolist de grupos', function (): void {
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Groups/Schemas/GroupInfolist.php';
    $displayPath = dirname(__DIR__, 2).'/app/Support/Filament/ProjectManagement/ProjectManagementGroupInfolistDisplay.php';
    $viewPath = dirname(__DIR__, 2).'/resources/views/filament/projects/infolists/group-members-highlight.blade.php';

    expect(file_exists($infolistPath))->toBeTrue();

    $infolist = file_get_contents($infolistPath);
    $display = file_get_contents($displayPath);
    $view = file_get_contents($viewPath);

    expect($infolist)
        ->toContain("TextEntry::make('members_highlight')")
        ->toContain('group-members-highlight')
        ->toContain('ProjectManagementGroupInfolistDisplay::membersPayload')
        ->not->toContain("Tab::make('Integrantes')")
        ->and($display)
        ->toContain('membersPayload')
        ->and($view)
        ->toContain('group-members-highlight__grid')
        ->toContain('Integrantes del equipo');
});

it('resuelve payload resaltado de integrantes del grupo', function (): void {
    $group = Group::make([
        'name' => 'Equipo comercial',
        'collaborator_ids' => [],
    ]);

    $payload = ProjectManagementGroupInfolistDisplay::membersPayload($group);

    expect($payload)
        ->group_name->toBe('Equipo comercial')
        ->has_members->toBeFalse()
        ->total->toBe(0)
        ->members->toBe([]);
});
