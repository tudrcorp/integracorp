<?php

declare(strict_types=1);

use App\Models\MassNotification;
use App\Support\MassNotificationReschedule;
use Carbon\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow(Carbon::parse('2026-07-10 12:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('detecta reprogramación cuando ya se envió y la nueva fecha es futura', function (): void {
    $record = new MassNotification([
        'is_sent' => true,
    ]);

    expect(MassNotificationReschedule::shouldReschedule($record, '2026-09-10 08:00:00'))->toBeTrue();
});

it('no reprograma si la notificación aún no fue enviada', function (): void {
    $record = new MassNotification([
        'is_sent' => false,
    ]);

    expect(MassNotificationReschedule::shouldReschedule($record, '2026-09-10 08:00:00'))->toBeFalse();
});

it('no reprograma si la nueva fecha no es futura', function (): void {
    $record = new MassNotification([
        'is_sent' => true,
    ]);

    expect(MassNotificationReschedule::shouldReschedule($record, '2026-07-09 08:00:00'))->toBeFalse()
        ->and(MassNotificationReschedule::shouldReschedule($record, null))->toBeFalse();
});

it('resetea is_sent al aplicar reprogramación', function (): void {
    $record = new MassNotification([
        'is_sent' => true,
    ]);

    $data = MassNotificationReschedule::applyRescheduleToFormData($record, [
        'date_programed' => '2026-09-10 08:00:00',
        'title' => 'Campaña',
    ]);

    expect($data['is_sent'])->toBeFalse()
        ->and($data['title'])->toBe('Campaña');
});

it('EditMassNotification pide confirmación al guardar con reprogramación', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/MassNotifications/Pages/EditMassNotification.php');

    expect($src)->toContain('MassNotificationReschedule::shouldReschedule')
        ->and($src)->toContain("->modalHeading('Programar nuevo envío')")
        ->and($src)->toContain('applyRescheduleToFormData');
});
