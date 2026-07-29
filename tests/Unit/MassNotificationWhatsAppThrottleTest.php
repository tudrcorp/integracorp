<?php

declare(strict_types=1);

use App\Support\MassNotificationWhatsAppSender;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'cache.default' => 'array',
        'mass-notifications.whatsapp_throttle_seconds' => 20,
        'mass-notifications.whatsapp_lock_seconds' => 30,
        'mass-notifications.whatsapp_lock_wait_seconds' => 0,
        'mass-notifications.whatsapp_lock_key' => 'mass-notification-whatsapp-send-test',
        'mass-notifications.whatsapp_last_sent_cache_key' => 'mass-notification-whatsapp-last-sent-at-test',
        'parameters.CURLOPT_URL' => '',
    ]);

    Cache::flush();
});

it('no espera si no hay envío previo registrado', function (): void {
    $started = microtime(true);

    MassNotificationWhatsAppSender::paceBeforeSend();

    expect(microtime(true) - $started)->toBeLessThan(0.5);
});

it('registra el timestamp del último envío exitoso', function (): void {
    MassNotificationWhatsAppSender::rememberLastSentAt();

    expect(Cache::get(MassNotificationWhatsAppSender::lastSentCacheKey()))->toBeInt()
        ->and(Cache::get(MassNotificationWhatsAppSender::lastSentCacheKey()))->toBeGreaterThan(time() - 2);
});

it('espera el throttle pendiente desde el último envío', function (): void {
    config(['mass-notifications.whatsapp_throttle_seconds' => 1]);

    Cache::put(MassNotificationWhatsAppSender::lastSentCacheKey(), time(), 60);

    $started = microtime(true);
    MassNotificationWhatsAppSender::paceBeforeSend();
    $elapsed = microtime(true) - $started;

    expect($elapsed)->toBeGreaterThanOrEqual(0.9)
        ->and($elapsed)->toBeLessThan(2.5);
});

it('lanza LockTimeoutException si el canal WhatsApp ya está bloqueado', function (): void {
    $lockKey = (string) config('mass-notifications.whatsapp_lock_key');
    $held = Cache::lock($lockKey, 30);
    expect($held->get())->toBeTrue();

    try {
        MassNotificationWhatsAppSender::send(
            ['phone' => '04141234567', 'fullName' => 'Test'],
            ['content' => 'Hola', 'type' => 'url'],
            throttle: true,
        );
        expect(false)->toBeTrue(); // no debería llegar
    } catch (LockTimeoutException) {
        expect(true)->toBeTrue();
    } finally {
        $held->release();
    }
});

it('con throttle desactivado no exige el lock del canal', function (): void {
    $lockKey = (string) config('mass-notifications.whatsapp_lock_key');
    $held = Cache::lock($lockKey, 30);
    expect($held->get())->toBeTrue();

    try {
        $result = MassNotificationWhatsAppSender::send(
            ['phone' => '04141234567', 'fullName' => 'Test'],
            ['content' => 'Hola', 'type' => 'url'],
            throttle: false,
        );

        expect($result->success)->toBeFalse()
            ->and($result->errorMessage)->toContain('Endpoint de WhatsApp no configurado');
    } finally {
        $held->release();
    }
});

it('el sender ya no hace sleep después del POST exitoso', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Support/MassNotificationWhatsAppSender.php');

    expect($src)->toContain('paceBeforeSend')
        ->and($src)->toContain('Cache::lock')
        ->and($src)->toContain('rememberLastSentAt')
        ->and($src)->toContain("Log::debug('MassNotificationWhatsAppSender: canal ocupado, reintentar'")
        ->and($src)->not->toContain("Log::info('MassNotificationWhatsAppSender: canal ocupado, reintentar'")
        ->and($src)->not->toMatch('/apiResponseSucceeded[\s\S]*sleep\(/');
});

it('el job reencola cuando el lock del canal está ocupado', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/SendNotificationMasive.php');

    expect($src)->toContain('LockTimeoutException')
        ->and($src)->toContain('$this->release(')
        ->and($src)->toContain('backoff')
        ->and($src)->toContain('public int $tries = 100')
        ->and($src)->toContain('public int $maxExceptions = 5')
        ->and($src)->toContain('public int $timeout = 120')
        ->and($src)->toContain('retryUntil');
});
