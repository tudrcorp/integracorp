<?php

declare(strict_types=1);

it('la accion de unificar grupo familiar solo es visible para superadmin', function (): void {
    $action = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/Affiliations/MergeFamilyGroupAction.php');

    expect($action)
        ->toContain("Action::make('mergeFamilyGroup')")
        ->toContain('UserNavigationAccess::isSuperAdmin')
        ->toContain('->steps([')
        ->toContain('preview(')
        ->toContain('execute(')
        ->toContain('confirmed')
        ->toContain('EXCLUIDO')
        ->not->toContain('migrate:fresh');
});

it('expone la accion en la vista de negocios y administracion', function (): void {
    $business = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Affiliations/Pages/ViewAffiliation.php');
    $administration = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Affiliations/Pages/ViewAffiliation.php');

    expect($business)
        ->toContain('MergeFamilyGroupAction::make()')
        ->and($administration)
        ->toContain('MergeFamilyGroupAction::make()');
});

it('arma miembros con un solo titular al cambiar la persona elegida', function (): void {
    $members = [
        ['affiliate_id' => 1, 'full_name' => 'Papa', 'nro_identificacion' => '1', 'from_code' => 'A', 'relationship' => 'TITULAR'],
        ['affiliate_id' => 2, 'full_name' => 'Mama', 'nro_identificacion' => '2', 'from_code' => 'B', 'relationship' => 'TITULAR'],
    ];

    $updated = App\Filament\Shared\Affiliations\MergeFamilyGroupAction::withTitularRelationship($members, 2);

    expect($updated[0]['relationship'])->toBe('OTRO')
        ->and($updated[1]['relationship'])->toBe('TITULAR');
});
