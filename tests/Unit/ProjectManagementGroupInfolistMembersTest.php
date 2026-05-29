<?php

declare(strict_types=1);

it('incluye tab de integrantes en infolist de grupos', function (): void {
    $infolistPath = dirname(__DIR__, 2).'/app/Filament/Projects/Resources/ProjectManagement/Groups/Schemas/GroupInfolist.php';

    expect(file_exists($infolistPath))->toBeTrue();

    $content = file_get_contents($infolistPath);

    expect($content)
        ->toContain("Tab::make('Integrantes')")
        ->toContain("TextEntry::make('team_size')")
        ->toContain("TextEntry::make('team_members')")
        ->toContain('->listWithLineBreaks()')
        ->toContain('->bulleted()')
        ->toContain('ProjectManagementGroupTable::resolveMemberNames');
});
