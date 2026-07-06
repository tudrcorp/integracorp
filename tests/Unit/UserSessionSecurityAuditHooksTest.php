<?php

declare(strict_types=1);

it('registra listeners de login y logout para auditoría de sesión', function (): void {
    $providerPath = dirname(__DIR__, 2).'/app/Providers/AppServiceProvider.php';
    $contents = file_get_contents($providerPath);

    expect($contents)
        ->toContain('Event::listen(Login::class, [UserSessionAuditTracker::class, \'onLogin\'])')
        ->and($contents)->toContain('Event::listen(Logout::class, [UserSessionAuditTracker::class, \'onLogout\'])');
});

it('implementa trazabilidad de sesión con duración y panel', function (): void {
    $trackerPath = dirname(__DIR__, 2).'/app/Support/UserSessionAuditTracker.php';
    $trackerContents = file_get_contents($trackerPath);

    expect($trackerContents)
        ->toContain('AUDIT_USER_SESSION_LOGIN')
        ->and($trackerContents)->toContain('AUDIT_USER_SESSION_LOGOUT')
        ->and($trackerContents)->toContain('session_duration_seconds')
        ->and($trackerContents)->toContain('session_duration_human')
        ->and($trackerContents)->toContain('resolvePanelFromRequestPath')
        ->and($trackerContents)->toContain('PlanGeneratorPreAffiliationSession::forget()');
});

it('expone categoría de sesiones en tabla de trazas', function (): void {
    $tablePath = dirname(__DIR__, 2).'/app/Filament/Business/Resources/SystemAuditTraces/Tables/SystemAuditTracesTable.php';
    $tableContents = file_get_contents($tablePath);

    expect($tableContents)
        ->toContain("'sessions' => 'Sesiones de usuario'")
        ->and($tableContents)->toContain("'sessions' => \$query->where('action', 'like', 'AUDIT_USER_SESSION_%')");
});
