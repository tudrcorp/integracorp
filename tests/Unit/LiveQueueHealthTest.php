<?php

declare(strict_types=1);

use App\Support\LivePresence\QueueHealth;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

/**
 * Las filas de `jobs` se insertan dentro de una transacción que siempre se
 * revierte, en colas con nombres propios de la prueba: un worker en otra
 * conexión nunca las ve, la base queda intacta y las colas reales no se tocan.
 */
beforeEach(function (): void {
    DB::beginTransaction();
    config([
        'queue.default' => 'database',
        'live-presence.queues.names' => ['lqh-atascada', 'lqh-sistema', 'lqh-programada'],
        'live-presence.queues.stuck_after_minutes' => 30,
    ]);
});

afterEach(fn () => DB::rollBack());

function insertQueueJob(string $queue, int $availableAt, ?int $reservedAt = null): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => '{"uuid":"x","displayName":"App\\\\Jobs\\\\Demo"}',
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => $availableAt,
        'created_at' => $availableAt,
    ]);
}

it('marca atascada la cola cuyo pendiente más viejo supera el umbral', function (): void {
    $now = now()->getTimestamp();

    insertQueueJob('lqh-atascada', $now - 3 * 86400);
    insertQueueJob('lqh-atascada', $now - 60);
    insertQueueJob('lqh-sistema', $now - 30);
    insertQueueJob('lqh-sistema', $now - 10, reservedAt: $now - 5);
    insertQueueJob('lqh-programada', $now + 3600);
    insertQueueJob('lqh-nueva', $now - 120);

    $report = QueueHealth::measure();
    $byName = collect($report['queues'])->keyBy('name');

    expect($report['stuck'])->toContain('lqh-atascada')
        ->and($report['stuck'])->not->toContain('lqh-sistema')
        ->and($report['queues'][0]['stuck'])->toBeTrue()
        ->and($byName['lqh-atascada']['pending'])->toBe(2)
        ->and($byName['lqh-atascada']['oldest_seconds'])->toBeGreaterThanOrEqual(3 * 86400)
        ->and($byName['lqh-sistema']['pending'])->toBe(1)
        ->and($byName['lqh-sistema']['reserved'])->toBe(1)
        ->and($byName['lqh-sistema']['stuck'])->toBeFalse()
        ->and($byName['lqh-programada']['pending'])->toBe(0)
        ->and($byName['lqh-programada']['delayed'])->toBe(1)
        ->and($byName['lqh-programada']['oldest_seconds'])->toBeNull()
        ->and($byName)->toHaveKey('lqh-nueva')
        ->and($report['pending_total'])->toBeGreaterThanOrEqual(4)
        ->and($report['worker_command'])->toStartWith('php artisan queue:work --queue=lqh-atascada,lqh-sistema,lqh-programada,')
        ->and($report['worker_command'])->toContain('lqh-nueva');
});

it('muestra las colas configuradas aunque estén vacías', function (): void {
    $report = QueueHealth::measure();
    $byName = collect($report['queues'])->keyBy('name');

    foreach (['lqh-atascada', 'lqh-sistema', 'lqh-programada'] as $queue) {
        expect($byName[$queue])->toMatchArray(['pending' => 0, 'reserved' => 0, 'delayed' => 0, 'oldest_seconds' => null, 'stuck' => false]);
    }
});

it('agrupa los fallidos por trabajo y primera línea de la excepción', function (): void {
    $caption = ['payload' => '{"uuid":"a","displayName":"App\\\\Jobs\\\\SendNotificacionWhatsApp","job":"x"}', 'exception' => "Exception: UltraMsg image send failed: max length 1024 in /var/www/app/Jobs/SendNotificacionWhatsApp.php:120\nStack trace:\n#0", 'failed_at' => '2026-09-22 10:00:00'];
    $pusher = ['payload' => '{"uuid":"b","displayName":"Illuminate\\\\Broadcasting\\\\BroadcastEvent"}', 'exception' => "Error: Class \"Pusher\\Pusher\" not found in /var/www/vendor/x.php:10\n#0", 'failed_at' => '2026-09-22 09:00:00'];

    $top = QueueHealth::topCauses([$caption, $pusher, $caption, ['payload' => 'basura', 'exception' => '', 'failed_at' => null]]);

    expect($top[0])->toMatchArray([
        'job' => 'SendNotificacionWhatsApp',
        'reason' => 'Exception: UltraMsg image send failed: max length 1024',
        'count' => 2,
        'last_at' => '2026-09-22 10:00:00',
    ])
        ->and($top[1]['job'])->toBe('BroadcastEvent')
        ->and($top[1]['reason'])->toBe('Error: Class "Pusher\Pusher" not found')
        ->and($top[2])->toMatchArray(['job' => 'Desconocido', 'reason' => 'Sin detalle', 'count' => 1]);
});

it('limita las causas a las cinco más repetidas', function (): void {
    $rows = [];

    foreach (range(1, 8) as $i) {
        $rows[] = ['payload' => '{"displayName":"App\\\\Jobs\\\\Job'.$i.'"}', 'exception' => 'Error '.$i, 'failed_at' => null];
    }

    expect(QueueHealth::topCauses($rows))->toHaveCount(5);
});

it('cuenta los fallidos de 24 h y 7 días sin tocar la base', function (): void {
    $report = QueueHealth::measure();

    expect($report['failed']['total'])->toBeInt()
        ->and($report['failed']['last_24h'])->toBeLessThanOrEqual($report['failed']['last_7d'])
        ->and($report['failed']['last_7d'])->toBeLessThanOrEqual($report['failed']['total'])
        ->and(count($report['failed']['top']))->toBeLessThanOrEqual(5);
});

it('describe la antigüedad en palabras', function (?int $seconds, string $label): void {
    expect(QueueHealth::ageLabel($seconds))->toBe($label);
})->with([
    [null, '—'],
    [45, '45 s'],
    [720, '12 min'],
    [3600, '1 h'],
    [11100, '3 h 5 min'],
    [86400, '1 día'],
    [26 * 86400 + 500, '26 días'],
]);
