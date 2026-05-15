<?php

declare(strict_types=1);

it('inyecta modales de acciones en la vista de carpetas para que «Crear carpeta» abra el modal', function (): void {
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Pages/ListMassNotifications.php';
    $bladePath = dirname(__DIR__, 2).'/resources/views/filament/marketing/mass-notifications/page-header-action-modals.blade.php';

    expect(file_get_contents($pagePath))->toContain('function getFooter()')
        ->and(file_get_contents($pagePath))->toContain('page-header-action-modals')
        ->and(file_get_contents($bladePath))->toContain('filament-actions::modals');
});
