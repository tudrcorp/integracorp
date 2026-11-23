<?php

declare(strict_types=1);

/**
 * Campanita de Operaciones: avisa con animación y vibración cuando llega una
 * notificación nueva (por ejemplo, «Expediente listo»).
 */
function operationsBellAlertSource(string $relativePath): string
{
    return (string) file_get_contents(dirname(__DIR__, 2).'/'.$relativePath);
}

it('el panel de Operaciones registra la campanita y consulta las notificaciones cada 10 s', function (): void {
    $provider = operationsBellAlertSource('app/Providers/Filament/OperationsPanelProvider.php');

    expect($provider)
        ->toContain("->databaseNotificationsPolling('10s')")
        ->toContain("view('filament.operations.partials.database-notifications-alert')");
});

it('la campanita se sacude, brilla, late y vibra solo cuando sube el contador', function (): void {
    $partial = operationsBellAlertSource('resources/views/filament/operations/partials/database-notifications-alert.blade.php');

    expect($partial)
        ->toContain('@keyframes fi-operations-db-bell-ring')
        ->toContain('@keyframes fi-operations-db-bell-glow')
        ->toContain('@keyframes fi-operations-db-badge-pop')
        ->toContain('body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert')
        ->toContain('navigator.vibrate(vibrationPattern)')
        ->toContain('prefers-reduced-motion: reduce')
        ->toContain('count > lastUnreadCount')
        ->toContain('<script data-navigate-once>')
        ->toContain('window.__tdgOperationsBellAlert');
});
