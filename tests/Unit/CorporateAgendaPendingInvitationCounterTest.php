<?php

declare(strict_types=1);

use App\Support\CorporateAgendaPendingInvitationCounter;

it('expone contadores de invitaciones pendientes para agenda corporativa', function (): void {
    $counterPath = dirname(__DIR__, 2).'/app/Support/CorporateAgendaPendingInvitationCounter.php';
    $pagePath = dirname(__DIR__, 2).'/app/Filament/Business/Pages/AgendaCorporativa.php';
    $shellPath = dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/corporate-calendar-shell.blade.php';

    expect(file_get_contents($counterPath))
        ->toContain('class CorporateAgendaPendingInvitationCounterTest')
        ->toContain('pendingInvitationCountForAuthenticatedUser')
        ->toContain('pendingInvitationCountsByDateForAuthenticatedUser')
        ->toContain('CorporateAgendaInvitationStatus::Pending')
        ->toContain("whereDate('activity_date', '>=', now()->toDateString())");

    expect(file_get_contents($pagePath))
        ->toContain('getNavigationBadge')
        ->toContain('getNavigationBadgeColor')
        ->toContain('verdeApple')
        ->toContain('CorporateAgendaPendingInvitationCounter')
        ->toContain('has_pending_invitation')
        ->toContain('pending_invitation_count');

    expect(file_get_contents($shellPath))
        ->toContain('has_pending_invitation')
        ->toContain('ring-rose-500')
        ->toContain('pendingInvitationRingClass');

    expect(file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css'))
        ->toContain('fi-agenda-corporativa-nav-item--has-pending-badge')
        ->toContain('fi-agenda-pending-nav-item-pulse')
        ->toContain('.fi-sidebar-item-btn::after')
        ->toContain('#34c759');
});

it('retorna cero invitaciones pendientes sin usuario autenticado', function (): void {
    expect(CorporateAgendaPendingInvitationCounter::pendingInvitationCountForUser(null))->toBe(0)
        ->and(CorporateAgendaPendingInvitationCounter::collaboratorIdsForUser(null))->toBe([]);
});
