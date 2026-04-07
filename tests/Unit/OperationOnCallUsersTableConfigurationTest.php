<?php

declare(strict_types=1);

it('configura OperationOnCallUsersTable con UX alineada a catálogos de operaciones', function () {
    $path = dirname(__DIR__, 2).'/app/Filament/Operations/Resources/OperationOnCallUsers/Tables/OperationOnCallUsersTable.php';
    $contents = file_get_contents($path);
    expect($contents)->toContain('->heading(\'Roles de guardia\'')
        ->toContain('->defaultSort(\'created_at\', \'desc\'')
        ->toContain("TextColumn::make('name')")
        ->toContain('lineClamp(2)')
        ->toContain('SelectFilter::make(\'status\'')
        ->toContain('OperationOnCallUser::query()')
        ->toContain('ViewAction::make()->label(\'Ver\'')
        ->toContain("TextColumn::make('date_OnCall')")
        ->toContain('diffForHumans()');
});
