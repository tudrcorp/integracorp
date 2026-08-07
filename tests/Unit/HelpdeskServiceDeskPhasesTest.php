<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Models\User;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskTicketIdentity;

it('identifica creador por created_by_user_id aunque cambie el nombre', function (): void {
    $user = new User;
    $user->id = 42;
    $user->name = 'Nombre Nuevo';

    $ticket = new HelpDesk([
        'created_by' => 'Nombre Viejo',
        'created_by_user_id' => 42,
    ]);

    expect(HelpdeskTicketIdentity::isCreator($ticket, $user))->toBeTrue();
});

it('cae al nombre solo cuando no hay created_by_user_id', function (): void {
    $user = new User;
    $user->id = 7;
    $user->name = 'Ana Pérez';

    $ticket = new HelpDesk([
        'created_by' => 'Ana Pérez',
        'created_by_user_id' => null,
    ]);

    expect(HelpdeskTicketIdentity::isCreator($ticket, $user))->toBeTrue();
});

it('calcula horas SLA por prioridad', function (): void {
    expect(HelpdeskSla::hoursForPriority('ALTA'))->toBe(['first_response' => 4, 'resolution' => 24])
        ->and(HelpdeskSla::hoursForPriority('MEDIA'))->toBe(['first_response' => 24, 'resolution' => 72])
        ->and(HelpdeskSla::hoursForPriority('BAJA'))->toBe(['first_response' => 48, 'resolution' => 120]);
});

it('ModalActions de paneles delegan al modulo compartido', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Actions/HelpdeskTicketModalActions.php";
    $shared = dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php';

    expect(file_exists($shared))->toBeTrue();
    expect(file_get_contents($path))
        ->toContain('SharedHelpdeskTicketModalActions')
        ->toContain('makeAddNoteAction')
        ->toContain('makeUpdateStatusAction')
        ->toContain('makeUpdatePriorityAction');
})->with(['Business', 'Administration', 'Operations', 'Marketing']);

it('ModalActions compartido incluye CSAT, cancelacion y reapertura', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Shared/Helpdesks/Actions/HelpdeskTicketModalActions.php';
    $contents = file_get_contents($path);

    expect($contents)
        ->toContain('csat_score')
        ->toContain('cancellation_reason')
        ->toContain('HelpDeskCsat::query()->updateOrCreate')
        ->toContain('HelpdeskSla::markResolved')
        ->toContain('HelpdeskSla::markCancelled')
        ->toContain('HelpdeskSla::markReopened')
        ->toContain('TYPE_REOPEN');
});

it('ObservationAppender registra eventos estructurados y first response', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskObservationAppender.php';

    expect(file_get_contents($path))
        ->toContain('HelpdeskEventRecorder::record')
        ->toContain('latest_note_by_user_id')
        ->toContain('HelpdeskSla::markFirstResponseIfNeeded');
});

it('unread usa conteo SQL agregado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskUnreadNoteTracker.php';

    expect(file_get_contents($path))
        ->toContain('whereNotExists')
        ->toContain('HelpdeskTicketIdentity::isLatestNoteAuthor')
        ->not->toContain('chunkById(200');
});

it('tabla helpdesk muestra badge SLA para analistas', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php';

    expect(file_get_contents($path))
        ->toContain("TextColumn::make('sla_badge')")
        ->toContain('HelpdeskSla::badgeLabel')
        ->toContain('fi-helpdesk-ta-sla-breached');
});
