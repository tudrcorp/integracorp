<?php

declare(strict_types=1);

it('registra la animacion de campana de notificaciones en el panel de telemedicina', function (): void {
    $provider = file_get_contents(dirname(__DIR__, 2).'/app/Providers/Filament/TelemedicinaPanelProvider.php');
    $view = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/telemedicina/partials/database-notifications-alert.blade.php'
    );
    $businessView = file_get_contents(
        dirname(__DIR__, 2).'/resources/views/filament/business/partials/database-notifications-alert.blade.php'
    );

    expect($provider)
        ->toContain("view('filament.telemedicina.partials.database-notifications-alert')")
        ->toContain('PanelsRenderHook::BODY_END')
        ->toContain("databaseNotificationsPolling('10s')")
        ->toContain('isLazy: false');

    expect($view)
        ->toContain('fi-panel-telemedicina')
        ->toContain('fi-db-notifications-alert')
        ->toContain('fi-telemedicina-db-bell-ring')
        ->toContain('fi-telemedicina-db-bell-glow')
        ->toContain('fi-telemedicina-db-badge-pop')
        ->toContain('triggerBellAlert')
        ->toContain('.database-notifications.sent');

    expect($businessView)
        ->toContain('fi-panel-business')
        ->toContain('fi-db-bell-ring');
});
