<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Support\BirthdayNotificationRecipientDelivery;
use App\Support\BirthdayNotificationRunReport;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    ensureBirthdayNotificationDeliverySchema();

    DB::table('birthday_notification_deliveries')->delete();
    DB::table('birthday_notifications')->delete();

    Carbon::setTestNow(Carbon::parse('2026-07-17 10:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ensureBirthdayNotificationDeliverySchema(): void
{
    if (! Schema::hasTable('birthday_notifications')) {
        Schema::create('birthday_notifications', function (Blueprint $table): void {
            $table->id();
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->string('file')->nullable();
            $table->string('status')->nullable();
            $table->json('channels')->nullable();
            $table->string('data_type')->nullable();
            $table->string('type')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_notification_deliveries')) {
        Schema::create('birthday_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_id');
            $table->string('full_name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->date('delivery_date');
            $table->string('email_status')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->text('email_error')->nullable();
            $table->string('whatsapp_status')->nullable();
            $table->timestamp('whatsapp_sent_at')->nullable();
            $table->text('whatsapp_error')->nullable();
            $table->timestamps();
        });
    }
}

it('resume métricas de correo y whatsapp por notificación', function (): void {
    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta test',
        'content' => 'Feliz cumpleaños',
        'status' => 'APROBADA',
        'channels' => ['email', 'whatsapp'],
        'data_type' => 'agents',
        'type' => 'image',
    ]);

    BirthdayNotificationRecipientDelivery::recordEmailOutcome(
        $notification->id,
        'Ana Pérez',
        'ana@example.com',
        MassNotificationDeliveryStatus::Sent,
    );

    BirthdayNotificationRecipientDelivery::recordEmailOutcome(
        $notification->id,
        'Luis Gómez',
        'luis@example.com',
        MassNotificationDeliveryStatus::Failed,
        'SMTP error',
    );

    BirthdayNotificationRecipientDelivery::recordWhatsappOutcome(
        $notification->id,
        'Ana Pérez',
        '04141234567',
        MassNotificationDeliveryStatus::Sent,
    );

    BirthdayNotificationRecipientDelivery::recordWhatsappOutcome(
        $notification->id,
        'Carla Díaz',
        '04149876543',
        MassNotificationDeliveryStatus::Skipped,
        'Teléfono inválido',
        deliveryDate: Carbon::parse('2026-07-16'),
    );

    $allStats = $notification->deliveryStats();
    $todayStats = $notification->deliveryStats(Carbon::parse('2026-07-17'));

    expect($allStats['email']['sent'])->toBe(1)
        ->and($allStats['email']['failed'])->toBe(1)
        ->and($allStats['whatsapp']['sent'])->toBe(1)
        ->and($allStats['whatsapp']['skipped'])->toBe(1)
        ->and($todayStats['whatsapp']['sent'])->toBe(1)
        ->and($todayStats['whatsapp']['skipped'])->toBe(0);
});

it('persiste entrega whatsapp al encolar desde el reporte de cumpleaños', function (): void {
    Bus::fake();

    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta WP',
        'content' => 'Feliz cumpleaños',
        'file' => 'cards/test.jpg',
        'status' => 'APROBADA',
        'channels' => ['whatsapp'],
        'data_type' => 'agents',
        'type' => 'image',
    ]);

    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentGroup('agentes');
    BirthdayNotificationRunReport::setCurrentNotification($notification->id);

    BirthdayNotificationRunReport::queueWhatsApp(
        'Ana Pérez',
        '04141234567',
        'Feliz cumpleaños',
        'cards/test.jpg',
        'image',
    );

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->full_name)->toBe('Ana Pérez')
        ->and($delivery->whatsapp_status)->toBe(MassNotificationDeliveryStatus::Sent)
        ->and($delivery->delivery_date->toDateString())->toBe('2026-07-17');
});

it('marca email omitido cuando el correo es inválido', function (): void {
    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta email',
        'content' => 'Feliz cumpleaños',
        'status' => 'APROBADA',
        'channels' => ['email'],
        'data_type' => 'agents',
        'type' => 'image',
    ]);

    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentGroup('agentes');
    BirthdayNotificationRunReport::setCurrentNotification($notification->id);

    BirthdayNotificationRunReport::sendBirthdayEmail(
        'correo-invalido',
        'Ana Pérez',
        fn () => null,
    );

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->email_status)->toBe(MassNotificationDeliveryStatus::Skipped)
        ->and($delivery->email_error)->toContain('inválido');
});
