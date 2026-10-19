<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;
use App\Jobs\WhatsAppBirthdayNotification;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Support\Birthday\BirthdayCelebrationCard;
use App\Support\Birthday\BirthdayCelebrationRecipientGate;
use App\Support\BirthdayNotificationRecipientDelivery;
use App\Support\BirthdayNotificationRunReport;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
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
            $table->boolean('sends_celebration_card')->default(false);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_notification_exclusions')) {
        Schema::create('birthday_notification_exclusions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_id');
            $table->string('data_type', 100);
            $table->unsignedBigInteger('recipient_id');
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
            $table->string('document_number', 32)->nullable();
            $table->date('birth_date')->nullable();
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

    if (! Schema::hasTable('birthday_celebration_accesses')) {
        Schema::create('birthday_celebration_accesses', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_notification_delivery_id')->nullable();
            $table->string('public_token', 64)->unique();
            $table->string('full_name');
            $table->string('document_number', 32)->nullable();
            $table->string('phone', 32)->nullable();
            $table->date('birth_date')->nullable();
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('birthday_coupon_tokens')) {
        Schema::create('birthday_coupon_tokens', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('birthday_celebration_access_id');
            $table->string('coupon_type', 32);
            $table->string('public_token', 64)->unique();
            $table->string('status', 32)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->unsignedBigInteger('used_by_user_id')->nullable();
            $table->string('used_from_ip', 45)->nullable();
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
        'sends_celebration_card' => true,
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
        'V12345678',
        '27/04/1990',
    );

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->full_name)->toBe('Ana Pérez')
        ->and($delivery->whatsapp_status)->toBe(MassNotificationDeliveryStatus::Sent)
        ->and($delivery->delivery_date->toDateString())->toBe('2026-07-17');

    Bus::assertDispatched(\App\Jobs\WhatsAppBirthdayNotification::class, function (\App\Jobs\WhatsAppBirthdayNotification $job): bool {
        $ctaAt = mb_strpos($job->content, '¡Haz clic aquí para ver tus regalos!');
        $linkAt = mb_strpos($job->content, '/feliz-cumpleanos/');

        return $job->file === BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH
            && $job->type === 'image'
            && $ctaAt !== false
            && $linkAt !== false
            && $linkAt > $ctaAt
            && preg_match('#https?://\S+/feliz-cumpleanos/\S+$#', trim($job->content)) === 1;
    });
});

it('una notificación normal conserva su archivo y no agrega el enlace animado', function (): void {
    Bus::fake();

    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta clásica',
        'content' => 'Feliz cumpleaños',
        'file' => 'cards/clasica.jpg',
        'status' => 'APROBADA',
        'channels' => ['whatsapp'],
        'data_type' => 'agents',
        'type' => 'image',
        'sends_celebration_card' => false,
    ]);

    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentNotification($notification->id);

    BirthdayNotificationRunReport::queueWhatsApp(
        'Ana Pérez',
        '04141234567',
        'Feliz cumpleaños',
        'cards/clasica.jpg',
        'image',
    );

    Bus::assertDispatched(\App\Jobs\WhatsAppBirthdayNotification::class, function (\App\Jobs\WhatsAppBirthdayNotification $job): bool {
        return $job->file === 'cards/clasica.jpg'
            && $job->type === 'image'
            && ! str_contains($job->content, 'feliz-cumpleanos');
    });
});

it('omite al destinatario apagado de la notificación animada', function (): void {
    $notification = BirthdayNotification::query()->create([
        'title' => 'CUMPLEAÑOS ANIMADO',
        'content' => 'Feliz cumpleaños',
        'file' => 'animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg',
        'status' => 'APROBADA',
        'channels' => ['whatsapp'],
        'data_type' => 'affiliates',
        'type' => 'image',
        'sends_celebration_card' => true,
    ]);

    BirthdayCelebrationRecipientGate::setReceives($notification->id, 'affiliates', 15, false);
    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentNotification($notification->id);

    $rows = BirthdayNotificationRunReport::withoutExcludedRecipients([
        (object) ['id' => 15, 'full_name' => 'Apagado'],
        (object) ['id' => 16, 'full_name' => 'Encendido'],
    ]);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]->full_name)->toBe('Encendido');
});

it('copia en oculto el correo animado y avisa por whatsapp al número fijo', function (): void {
    Mail::fake();
    Bus::fake();

    $notification = BirthdayNotification::query()->create([
        'title' => 'CUMPLEAÑOS ANIMADO',
        'content' => 'Feliz cumpleaños',
        'file' => 'animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg',
        'status' => 'APROBADA',
        'channels' => ['email', 'whatsapp'],
        'data_type' => 'affiliates',
        'type' => 'image',
        'sends_celebration_card' => true,
    ]);

    BirthdayNotificationRunReport::begin();
    BirthdayNotificationRunReport::setCurrentNotification($notification->id);

    BirthdayNotificationRunReport::mailerWithWitnessCopies('cumple@example.com')
        ->send(new NotificationMasiveMailBirthday(
            'Ana Pérez',
            'animacion-cumpleanos/assets/tarjeta-cumpleanos.jpg',
            'cumple@example.com',
            'https://integracorp.tudrgroup.com/feliz-cumpleanos/abc123',
        ));

    Mail::assertQueued(NotificationMasiveMailBirthday::class, function (NotificationMasiveMailBirthday $mail): bool {
        return $mail->hasTo('cumple@example.com')
            && $mail->hasBcc('solrodriguez@tudrencasa.com')
            && $mail->hasBcc('aaular@tudrencasa.com');
    });

    BirthdayNotificationRunReport::queueWitnessWhatsAppCopies(
        'Ana Pérez',
        'Feliz cumpleaños',
        BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH,
        'image',
    );

    Bus::assertDispatched(WhatsAppBirthdayNotification::class, function (WhatsAppBirthdayNotification $job): bool {
        return $job->phone === '+584121931865'
            && $job->isControlCopy
            && $job->file === BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH
            && ! str_contains($job->content, 'feliz-cumpleanos');
    });
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
        fn (?string $animationUrl = null) => null,
    );

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->email_status)->toBe(MassNotificationDeliveryStatus::Skipped)
        ->and($delivery->email_error)->toContain('inválido');
});
