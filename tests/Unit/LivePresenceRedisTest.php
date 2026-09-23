<?php

declare(strict_types=1);

use App\Support\LivePresence\RedisLivePresenceRepository;
use Illuminate\Support\Facades\Redis;

uses(Tests\TestCase::class);

/**
 * La versión de producción del almacén de presencia, contra un Redis real.
 * Corre con los dos clientes (phpredis y predis) porque dentro de una tubería
 * sus firmas difieren. Usa la base 15 y un prefijo propio, y la limpia al final.
 * Se salta si no hay Redis escuchando (por ejemplo, en desarrollo).
 */
function livePresenceRedisAvailable(): bool
{
    $socket = @fsockopen((string) env('REDIS_HOST', '127.0.0.1'), (int) env('REDIS_PORT', 6379), $errno, $error, 0.5);

    if ($socket === false) {
        return false;
    }

    fclose($socket);

    return true;
}

function livePresenceRedisRepository(string $client): RedisLivePresenceRepository
{
    config([
        'database.redis.client' => $client,
        'database.redis.lp_test' => [
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', 6379),
            'database' => 15,
        ],
        'database.redis.options.prefix' => 'lp-test:',
    ]);
    app()->forgetInstance('redis');
    Redis::clearResolvedInstances();

    return new RedisLivePresenceRepository('lp_test', 300, 3, 3600, 10);
}

it('guarda, lista y acota la presencia en Redis', function (string $client): void {
    if (! livePresenceRedisAvailable()) {
        $this->markTestSkipped('No hay Redis escuchando; la versión Redis se valida donde exista.');
    }

    if ($client === 'phpredis' && ! extension_loaded('redis')) {
        $this->markTestSkipped('La extensión phpredis no está instalada.');
    }

    $store = livePresenceRedisRepository($client);
    Redis::connection('lp_test')->flushdb();

    $store->touch('aaaaaaaaaaaaaaaaaaaaaaaa', ['user_id' => 1, 'user_name' => 'Uno', 'visible' => true], true);
    $store->touch('aaaaaaaaaaaaaaaaaaaaaaaa', ['rtt_ms' => 80], true);
    $store->touch('bbbbbbbbbbbbbbbbbbbbbbbb', ['user_id' => 2, 'user_name' => 'Dos'], false);

    $online = collect($store->online(90))->keyBy('session_key');

    expect($online)->toHaveCount(2)
        ->and($online['aaaaaaaaaaaaaaaaaaaaaaaa'])->toMatchArray(['user_name' => 'Uno', 'rtt_ms' => '80', 'visible' => '1', 'requests' => '2']);

    foreach (range(1, 5) as $i) {
        $store->pushTimeline(1, ['label' => 'Evento '.$i, 'at' => $i]);
        $store->recordRequestDuration($i * 10);
    }

    expect(array_column($store->timeline(1, 10), 'label'))->toBe(['Evento 5', 'Evento 4', 'Evento 3'])
        ->and(array_column($store->durationSamples(), 1))->toBe([50.0, 40.0, 30.0, 20.0, 10.0])
        ->and($store->serverInfo()['memory'])->not->toBeNull();

    Redis::connection('lp_test')->flushdb();
})->with(['phpredis', 'predis']);
