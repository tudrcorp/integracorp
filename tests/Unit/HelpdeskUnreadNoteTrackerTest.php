<?php

declare(strict_types=1);

use App\Support\HelpdeskUnreadNoteTracker;

it('HelpdeskObservationAppender registra la última nota para seguimiento de lectura', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskObservationAppender.php';

    expect(file_get_contents($path))
        ->toContain('latest_note_at')
        ->toContain('latest_note_by');
});

it('HelpdeskUnreadNoteTracker expone conteo, detección y marcado de lectura', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskUnreadNoteTracker.php';

    expect(file_get_contents($path))
        ->toContain('class HelpdeskUnreadNoteTracker')
        ->toContain('unreadCountForAuthenticatedUser')
        ->toContain('hasUnreadNotes')
        ->toContain('markAsRead')
        ->toContain('recordRowClass')
        ->toContain('fi-helpdesk-ta-has-unread-note')
        ->toContain('trackingIsAvailable');
});

it('HelpdeskTableConfigurator resalta filas con notas sin revisar', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskUnreadNoteTracker::recordRowClass');
});

it('el trait de navegación helpdesk aplica la clase cuando hay badge', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Concerns/RegistersHelpdeskUnreadNoteNavigation.php';

    expect(file_get_contents($path))
        ->toContain('getNavigationItems')
        ->toContain('fi-helpdesk-nav-item--has-unread-notes')
        ->toContain('extraAttributes');
});

it('los recursos helpdesk registran badge de notas sin revisar', function (): void {
    foreach (['Business', 'Administration', 'Operations', 'Marketing'] as $panel) {
        $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/HelpdeskResource.php";

        expect(file_get_contents($path))
            ->toContain('RegistersHelpdeskUnreadNoteNavigation');
    }
});

it('las vistas de notas y bitácora marcan lectura al abrirse', function (): void {
    expect(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/helpdesks/notes-modal.blade.php'))
        ->toContain('HelpdeskUnreadNoteTracker::markAsRead')
        ->and(file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/helpdesks/timeline-modal.blade.php'))
        ->toContain('HelpdeskUnreadNoteTracker::markAsRead');
});

it('theme.css define el punto con pulse junto al ID para tickets con notas sin revisar', function (): void {
    $css = file_get_contents(dirname(__DIR__, 2).'/resources/css/filament/admin/theme.css');

    expect($css)
        ->toContain('fi-helpdesk-ta-has-unread-note')
        ->toContain('fi-ta-cell-id .fi-ta-text-item::after')
        ->toContain('fi-helpdesk-unread-note-dot-pulse')
        ->toContain('width: 0.5rem')
        ->toContain('background-color: rgb(34 197 94)')
        ->toContain('fi-helpdesk-nav-item--has-unread-notes')
        ->not->toContain('fi-helpdesk-unread-note-shade')
        ->not->toContain('fi-agenda-pending-nav-item-pulse');
});

it('HelpdeskUnreadNoteTracker no marca como no leída la nota del propio usuario', function (): void {
    expect(HelpdeskUnreadNoteTracker::actorsMatch('GUSTAVO CAMACHO', 'gustavo camacho'))->toBeTrue()
        ->and(HelpdeskUnreadNoteTracker::actorsMatch('Ana', 'Pedro'))->toBeFalse();
});

it('HelpdeskObservationAppender conserva la firma de mergeObservation con timestamp opcional', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskObservationAppender.php';

    expect(file_get_contents($path))
        ->toContain('mergeObservation(string $existingRaw, string $newNote, string $userName, ?Carbon $at = null)');
});
