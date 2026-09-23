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

it('la campanita sondea una sola vez por pestaña aunque se navegue dentro del panel', function (string $view, string $flag): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/'.$view.'/partials/database-notifications-alert.blade.php');

    /** El panel es SPA: sin data-navigate-once cada navegación sumaba otra copia del script (un usuario llegó a 450 peticiones por minuto). */
    expect($source)
        ->toContain('<script data-navigate-once>')
        ->toContain('window.'.$flag)
        ->toContain('topbarEnd === observedNode')
        ->not->toContain('setInterval(pollBellAlertSignal, 2000)');
})->with([
    'negocios' => ['business', '__tdgBusinessBellAlert'],
    'telemedicina' => ['telemedicina', '__tdgTelemedicinaBellAlert'],
]);

it('el sondeo de Negocios se pausa con la pestaña oculta y no se solapa', function (): void {
    $source = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/partials/database-notifications-alert.blade.php');

    expect($source)
        ->toContain('const pollEveryMs = 20000;')
        ->toContain('document.hidden || pollInFlight')
        ->toContain("addEventListener('visibilitychange'");
});
