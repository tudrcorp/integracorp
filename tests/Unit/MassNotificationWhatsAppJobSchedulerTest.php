<?php

declare(strict_types=1);

use App\Jobs\SendNotificationMasive;
use App\Support\MassNotificationWhatsAppJobScheduler;
use Carbon\Carbon;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-29 10:00:00', 'America/Caracas'));
    config(['mass-notifications.whatsapp_throttle_seconds' => 20]);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('aplica delays escalonados segun el throttle', function (): void {
    $jobs = [
        new SendNotificationMasive(['phone' => '04141111111'], ['content' => 'a'], 1),
        new SendNotificationMasive(['phone' => '04142222222'], ['content' => 'b'], 2),
        new SendNotificationMasive(['phone' => '04143333333'], ['content' => 'c'], 3),
    ];

    $base = now()->timestamp;
    $staggered = MassNotificationWhatsAppJobScheduler::withStaggeredDelays($jobs);

    expect($staggered)->toHaveCount(3)
        ->and($staggered[0]->delay?->timestamp)->toBe($base)
        ->and($staggered[1]->delay?->timestamp)->toBe($base + 20)
        ->and($staggered[2]->delay?->timestamp)->toBe($base + 40);
});

it('arma el mensaje de exito con ETA para WhatsApp', function (): void {
    $message = MassNotificationWhatsAppJobScheduler::successMessage(10, 4);

    expect($message)
        ->toContain('10 job(s) en total')
        ->toContain('4 WhatsApp')
        ->toContain('1 cada 20 s')
        ->toContain('ETA ~1 min')
        ->toContain('reintentarán automáticamente');
});

it('omite ETA cuando no hay WhatsApp', function (): void {
    $message = MassNotificationWhatsAppJobScheduler::successMessage(3, 0);

    expect($message)
        ->toContain('Envío encolado exitosamente')
        ->not->toContain('ETA')
        ->not->toContain('WhatsApp');
});
