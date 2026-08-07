<?php

declare(strict_types=1);

use App\Filament\Shared\CommercialStructure\Actions\CommercialStructureIosActionsMenu;
use App\Filament\Shared\CommercialStructure\Actions\ResetCommercialStructureUserPasswordAction;
use App\Filament\Shared\CommercialStructure\Actions\UpdateCommercialStructureEmailAction;
use App\Support\Filament\CommercialStructureEmailUpdater;
use App\Support\Filament\CommercialStructurePasswordResetter;

it('actualiza correo con motivo obligatorio y opcion de sincronizar usuarios', function (): void {
    $php = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/CommercialStructureEmailUpdater.php');

    expect($php)->not->toBeFalse()
        ->toContain('SecurityAudit::log')
        ->toContain('_EMAIL_UPDATED')
        ->toContain('alsoUpdateUser')
        ->toContain('reason')
        ->toContain('findUserByEmail');
});

it('resetea contraseña temporal solo si el correo coincide con users', function (): void {
    $php = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/CommercialStructurePasswordResetter.php');

    expect($php)->not->toBeFalse()
        ->toContain('SecurityAudit::log')
        ->toContain('_USER_PASSWORD_RESET')
        ->toContain('Hash::make(CommercialStructureEmailUpdater::TEMPORARY_PASSWORD)')
        ->toContain('debe ser igual al correo del usuario')
        ->toContain('reason');
});

it('define la contraseña temporal esperada', function (): void {
    expect(CommercialStructureEmailUpdater::TEMPORARY_PASSWORD)->toBe('12345678');
});

it('accion de editar correo exige motivo y check de usuarios', function (): void {
    $php = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/Actions/UpdateCommercialStructureEmailAction.php');

    expect($php)->not->toBeFalse()
        ->toContain("Textarea::make('reason')")
        ->toContain("Checkbox::make('also_update_user')")
        ->toContain('CommercialStructureEmailUpdater::update')
        ->toContain('minLength(10)')
        ->toContain('Actualizar también el correo en la tabla de usuarios');
});

it('accion de resetear contraseña exige motivo y valida coincidencia de email', function (): void {
    $php = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/Actions/ResetCommercialStructureUserPasswordAction.php');

    expect($php)->not->toBeFalse()
        ->toContain("Textarea::make('reason')")
        ->toContain('CommercialStructurePasswordResetter::reset')
        ->toContain('emailsMatchForPasswordReset')
        ->toContain('minLength(10)');
});

it('expone las acciones en vistas de administracion de agencias y agentes', function (): void {
    $agency = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agencies/Pages/ViewAgency.php');
    $agent = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/Agents/Pages/ViewAgent.php');

    expect($agency)
        ->toContain('CommercialStructureIosActionsMenu::make([')
        ->toContain("UpdateCommercialStructureEmailAction::make('agency', 'administration')")
        ->toContain("ResetCommercialStructureUserPasswordAction::make('agency', 'administration')")
        ->and($agent)
        ->toContain('CommercialStructureIosActionsMenu::make([')
        ->toContain("UpdateCommercialStructureEmailAction::make('agent', 'administration')")
        ->toContain("ResetCommercialStructureUserPasswordAction::make('agent', 'administration')");
});

it('expone las acciones en vistas de negocio de agencias y agentes', function (): void {
    $agency = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agencies/Pages/ViewAgency.php');
    $agent = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Resources/Agents/Pages/ViewAgent.php');

    expect($agency)
        ->toContain('CommercialStructureIosActionsMenu::make([')
        ->toContain("UpdateCommercialStructureEmailAction::make('agency', 'business')")
        ->toContain("ResetCommercialStructureUserPasswordAction::make('agency', 'business')")
        ->and($agent)
        ->toContain('CommercialStructureIosActionsMenu::make([')
        ->toContain("UpdateCommercialStructureEmailAction::make('agent', 'business')")
        ->toContain("ResetCommercialStructureUserPasswordAction::make('agent', 'business')");
});

it('define un menu iOS reutilizable para acciones de cabecera', function (): void {
    $php = file_get_contents(dirname(__DIR__, 2).'/app/Support/Filament/FilamentIosActionsMenu.php');
    $alias = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Shared/CommercialStructure/Actions/CommercialStructureIosActionsMenu.php');

    expect($php)->not->toBeFalse()
        ->toContain('ActionGroup::make')
        ->toContain('->label($label)')
        ->toContain('->button()')
        ->toContain('FilamentIosButton::extraClassForFilamentColor')
        ->toContain('fi-ios-actions-menu-trigger')
        ->and($alias)->toContain('FilamentIosActionsMenu::make');
});

it('resuelve las clases de soporte y acciones', function (): void {
    expect(CommercialStructureEmailUpdater::class)->toBeString()
        ->and(CommercialStructurePasswordResetter::class)->toBeString()
        ->and(UpdateCommercialStructureEmailAction::class)->toBeString()
        ->and(ResetCommercialStructureUserPasswordAction::class)->toBeString()
        ->and(CommercialStructureIosActionsMenu::class)->toBeString();
});
