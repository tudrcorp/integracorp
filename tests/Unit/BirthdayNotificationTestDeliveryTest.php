<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;
use App\Filament\Marketing\Resources\BirthdayNotifications\Actions\BirthdayNotificationTestActions;
use App\Filament\Marketing\Resources\BirthdayNotifications\Pages\ViewBirthdayNotification;
use App\Http\Controllers\NotificationController;
use App\Mail\NotificationMasiveMailBirthday;
use App\Models\BirthdayNotification;
use App\Models\BirthdayNotificationDelivery;
use App\Support\Birthday\BirthdayCelebrationCard;
use App\Support\BirthdayNotificationTestDelivery;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Mockery\MockInterface;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
        'mail.default' => 'array',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

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

    DB::table('birthday_notification_deliveries')->delete();
    DB::table('birthday_notifications')->delete();
});

afterEach(function (): void {
    Mockery::close();
});

it('expone acciones de prueba en la vista de cumpleaños', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Pages/ViewBirthdayNotification.php');
    $tableSrc = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Marketing/Resources/BirthdayNotifications/Tables/BirthdayNotificationsTable.php');

    expect($src)->toContain('BirthdayNotificationTestActions::all()')
        ->and($tableSrc)->toContain('BirthdayNotificationTestActions::whatsapp()')
        ->and($tableSrc)->toContain('BirthdayNotificationTestActions::email()')
        ->and(method_exists(ViewBirthdayNotification::class, 'getHeaderActions'))->toBeTrue()
        ->and(BirthdayNotificationTestActions::whatsapp()->getName())->toBe('test_notification_wp')
        ->and(BirthdayNotificationTestActions::email()->getName())->toBe('test_notification_email');
});

it('resuelve la tarjeta animada como medio de prueba cuando corresponde', function (): void {
    $notification = new BirthdayNotification([
        'sends_celebration_card' => true,
        'file' => 'otro.jpg',
        'type' => 'video',
    ]);

    expect(BirthdayNotificationTestDelivery::resolveMedia($notification))
        ->toBe([BirthdayCelebrationCard::PUBLIC_RELATIVE_PATH, 'image']);
});

it('envia una prueba de correo con la plantilla de cumpleaños', function (): void {
    Mail::fake();

    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta test',
        'content' => 'Feliz cumpleaños',
        'file' => 'birthday/card.jpg',
        'status' => 'APROBADA',
        'channels' => ['email'],
        'data_type' => ['agents'],
        'type' => 'image',
        'sends_celebration_card' => false,
    ]);

    $success = BirthdayNotificationTestDelivery::sendEmail($notification, [
        'email' => 'prueba@tudrencasa.com',
        'name' => 'Ana Prueba',
    ]);

    expect($success)->toBeTrue();

    Mail::assertSent(NotificationMasiveMailBirthday::class, function (NotificationMasiveMailBirthday $mail): bool {
        return $mail->hasTo('prueba@tudrencasa.com')
            && $mail->name === 'Ana Prueba'
            && $mail->file === 'birthday/card.jpg';
    });

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->email_status)->toBe(MassNotificationDeliveryStatus::Sent)
        ->and($delivery->full_name)->toBe('Ana Prueba');
});

it('envia una prueba de whatsapp usando el controlador de cumpleaños', function (): void {
    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta test',
        'content' => 'Feliz cumpleaños',
        'file' => 'birthday/card.jpg',
        'status' => 'APROBADA',
        'channels' => ['whatsapp'],
        'data_type' => ['agents'],
        'type' => 'image',
        'sends_celebration_card' => false,
    ]);

    $this->mock('alias:'.NotificationController::class, function (MockInterface $mock): void {
        $mock->shouldReceive('notificationBirthday')
            ->once()
            ->withArgs(function (string $name, string $phone, string $content, string $file, string $type): bool {
                return $name === 'Luis Prueba'
                    && $phone === '+584121111111'
                    && $content === 'Feliz cumpleaños'
                    && $file === 'birthday/card.jpg'
                    && $type === 'image';
            })
            ->andReturn(['status' => 'success', 'data' => 'ok']);
    });

    $success = BirthdayNotificationTestDelivery::sendWhatsApp($notification, [
        'phone' => '+584121111111',
        'name' => 'Luis Prueba',
    ]);

    expect($success)->toBeTrue();

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery)->not->toBeNull()
        ->and($delivery->whatsapp_status)->toBe(MassNotificationDeliveryStatus::Sent)
        ->and($delivery->phone)->toBe('+584121111111');
});

it('rechaza correos inválidos en la prueba sin lanzar excepción', function (): void {
    Mail::fake();

    $notification = BirthdayNotification::query()->create([
        'title' => 'Tarjeta test',
        'content' => 'Feliz cumpleaños',
        'file' => 'birthday/card.jpg',
        'status' => 'APROBADA',
        'channels' => ['email'],
        'data_type' => ['agents'],
        'type' => 'image',
    ]);

    $success = BirthdayNotificationTestDelivery::sendEmail($notification, [
        'email' => 'no-es-email',
        'name' => 'Ana',
    ]);

    expect($success)->toBeFalse();
    Mail::assertNothingSent();

    $delivery = BirthdayNotificationDelivery::query()->first();

    expect($delivery?->email_status)->toBe(MassNotificationDeliveryStatus::Skipped);
});
