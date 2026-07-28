<?php

declare(strict_types=1);

use App\Models\HelpDesk;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\ProjectManagement\ToBacklogSession;
use Illuminate\Support\Collection;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    ToBacklogSession::clear();
});

it('guarda en sesion solo tickets en proceso con id y descripcion', function (): void {
    $inProgress = new HelpDesk([
        'description' => '<p>Corregir plantilla de correos</p>',
        'status' => HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS,
    ]);
    $inProgress->id = 31;

    $pending = new HelpDesk([
        'description' => 'No debe entrar',
        'status' => HelpdeskTaskStatusOptions::STATUS_PENDING,
    ]);
    $pending->id = 99;

    $result = ToBacklogSession::addFromRecords(Collection::make([$inProgress, $pending]));

    expect($result)->toBe(['added' => 1, 'skipped' => 1])
        ->and(ToBacklogSession::tickets())->toBe([
            [
                'id' => 31,
                'description' => 'Corregir plantilla de correos',
            ],
        ])
        ->and(ToBacklogSession::options())->toBe([
            31 => '#31 — Corregir plantilla de correos',
        ]);
});

it('elimina el ticket de la sesion al consumirlo', function (): void {
    $ticket = new HelpDesk([
        'description' => 'Ticket A',
        'status' => HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS,
    ]);
    $ticket->id = 10;

    ToBacklogSession::addFromRecords(Collection::make([$ticket]));
    ToBacklogSession::remove(10);

    expect(ToBacklogSession::tickets())->toBe([])
        ->and(session()->has(ToBacklogSession::SESSION_KEY))->toBeFalse();
});

it('expira la variable to_backlog despues de 8 horas', function (): void {
    session([
        ToBacklogSession::SESSION_KEY => [
            'expires_at' => now()->subMinute()->getTimestamp(),
            'tickets' => [
                5 => ['id' => 5, 'description' => 'Vencido'],
            ],
        ],
    ]);

    expect(ToBacklogSession::tickets())->toBe([])
        ->and(session()->has(ToBacklogSession::SESSION_KEY))->toBeFalse();
});

it('registra bulk action Para BackLog y consume sesion en el backlog', function (): void {
    $configurator = file_get_contents(dirname(__DIR__, 2).'/app/Support/HelpdeskTableConfigurator.php');
    $backlog = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Projects/Pages/Backlog.php');
    $session = file_get_contents(dirname(__DIR__, 2).'/app/Support/ProjectManagement/ToBacklogSession.php');

    expect($configurator)
        ->toContain("BulkAction::make('toBacklog')")
        ->toContain("->label('Para BackLog')")
        ->toContain('ToBacklogSession::addFromRecords');

    expect($backlog)
        ->toContain('ToBacklogSession::options()')
        ->toContain('ToBacklogSession::remove($helpDeskId)');

    expect($session)
        ->toContain("public const SESSION_KEY = 'to_backlog'")
        ->toContain('public const TTL_HOURS = 8')
        ->toContain('HelpdeskTaskStatusOptions::STATUS_IN_PROGRESS');
});
