<?php

declare(strict_types=1);

it('muestra integrantes del equipo en infolist de actividades cuando el ejecutor es un grupo', function (): void {
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Activities/Schemas/ActivityInfolist.php';

    expect(file_exists($infolistPath))->toBeTrue();

    $content = file_get_contents($infolistPath);

    expect($content)
        ->toContain("TextEntry::make('assigned_team_members')")
        ->toContain("TextEntry::make('assigned_team_size')")
        ->toContain('resolveTeamMemberNames')
        ->toContain('resolveTeam')
        ->toContain('collaborator_ids')
        ->toContain('->listWithLineBreaks()')
        ->toContain('->bulleted()');
});
