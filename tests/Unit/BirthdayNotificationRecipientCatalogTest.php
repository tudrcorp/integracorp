<?php

declare(strict_types=1);

use App\Models\Agent;
use App\Models\BirthdayNotification;
use App\Support\BirthdayNotificationRecipientCatalog;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

uses(Tests\TestCase::class);

it('resuelve el query de destinatarios segun data_type', function (): void {
    $notification = new BirthdayNotification([
        'data_type' => 'agents',
        'channels' => ['whatsapp'],
    ]);

    $query = BirthdayNotificationRecipientCatalog::queryFor($notification);
    $config = BirthdayNotificationRecipientCatalog::configForNotification($notification);

    expect($query)->toBeInstanceOf(Builder::class)
        ->and($query->getModel())->toBeInstanceOf(Agent::class)
        ->and($config['name'])->toBe('name')
        ->and($config['email'])->toBe('email')
        ->and($config['phone'])->toBe('phone')
        ->and($config['birth_date'])->toBe('birth_date');
});

it('filtra destinatarios por dia y mes de cumpleaños', function (): void {
    $query = Agent::query();
    $filtered = BirthdayNotificationRecipientCatalog::applyBirthdayDateFilter(
        $query,
        'birth_date',
        Carbon::parse('2026-07-17'),
    );

    expect($filtered->toSql())->toContain('like')
        ->and($filtered->getBindings())->toContain('17/07/%');
});

it('el relation manager usa el padron de destinatarios', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/RelationManagers/BirthdayNotificationDeliveriesRelationManager.php');

    expect($src)->toContain('BirthdayNotificationRecipientCatalog::queryFor')
        ->and($src)->toContain('Destinatarios de la notificación')
        ->and($src)->toContain("Filter::make('birthday_date')")
        ->and($src)->toContain('Fecha de cumpleaños');
});
