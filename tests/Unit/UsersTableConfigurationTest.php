<?php

declare(strict_types=1);

it('configura UsersTable con UX clara y acciones principales', function (): void {
    $table = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Tables/UsersTable.php');
    $list = file_get_contents(__DIR__.'/../../app/Filament/Business/Resources/Users/Pages/ListUsers.php');
    $ui = file_get_contents(__DIR__.'/../../app/Support/Filament/UserTableUi.php');

    expect($table)->not->toBeFalse()
        ->toContain("->heading('Usuarios INTEGRACORP')")
        ->toContain("->defaultSort('name', 'asc')")
        ->toContain('ColumnGroup::make(\'Usuario\'')
        ->toContain('ColumnGroup::make(\'Acceso\'')
        ->toContain('UserRoleProfiles::activeRoleLabels')
        ->toContain('UserTableUi::moduleBadgeLabels')
        ->toContain("TextColumn::make('modules_display')")
        ->toContain("TextColumn::make('identity_card')")
        ->toContain('ViewAction::make()')
        ->toContain('EditAction::make()')
        ->toContain('SelectFilter::make(\'status\')')
        ->toContain('SelectFilter::make(\'module\')')
        ->toContain('whereJsonContains(\'departament\'')
        ->not->toContain('TextInputColumn::make');

    expect($list)->toContain('Nuevo usuario')
        ->toContain('getSubheading');

    expect($ui)->toContain('statusBadgeColor')
        ->toContain('moduleBadgeLabels')
        ->toContain('commercialSummary');
});

it('expone etiquetas de perfiles activos para la tabla de usuarios', function (): void {
    $user = new \App\Models\User;
    $user->forceFill([
        'is_agent' => true,
        'is_admin' => false,
    ]);

    expect(\App\Support\Filament\UserRoleProfiles::activeRoleLabels($user))
        ->toContain('Agente')
        ->not->toContain('Administrador');
});

it('formatea modulos como etiquetas legibles para la tabla', function (): void {
    expect(\App\Support\Filament\UserTableUi::moduleBadgeLabels(['NEGOCIOS', 'ADMINISTRACION']))
        ->toBe(['Negocios', 'Administración'])
        ->and(\App\Support\Filament\UserTableUi::moduleBadgeLabels(null))
        ->toBe(['Sin módulos'])
        ->and(\App\Support\Filament\UserTableUi::moduleBadgeLabels('OPERACIONES'))
        ->toBe(['Operaciones']);
});
