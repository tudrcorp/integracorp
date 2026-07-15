<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;
use App\Jobs\SendNotificationMasive;
use App\Jobs\SendNotificationMasiveEmail;
use App\Jobs\SweepMassNotificationWhatsAppFailures;
use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationDispatchService;
use App\Support\MassNotificationWhatsAppSender;
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

    ensureMassNotificationWhatsAppSweepSchema();

    DB::table('data_notifications')->delete();
    DB::table('mass_notifications')->delete();
    DB::table('mass_notification_folders')->delete();
    DB::table('mass_notification_folders')->insert([
        'id' => 1,
        'name' => 'Sin organizar',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-14 12:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ensureMassNotificationWhatsAppSweepSchema(): void
{
    if (! Schema::hasTable('mass_notification_folders')) {
        Schema::create('mass_notification_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('mass_notifications')) {
        Schema::create('mass_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mass_notification_folder_id')->nullable();
            $table->string('title');
            $table->text('content')->nullable();
            $table->string('header_title')->nullable();
            $table->string('type')->nullable();
            $table->string('file')->nullable();
            $table->boolean('is_sent')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->string('status')->nullable();
            $table->timestamp('date_programed')->nullable();
            $table->json('channels')->nullable();
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('data_notifications')) {
        Schema::create('data_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mass_notification_id')->nullable();
            $table->string('fullName')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
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

function createSweepTestNotification(array $attributes = []): MassNotification
{
    return MassNotification::query()->create(array_merge([
        'title' => 'Campaña sweep',
        'content' => 'Contenido',
        'header_title' => 'Hola',
        'type' => 'url',
        'status' => 'APROBADA',
        'is_approved' => true,
        'channels' => ['whatsapp'],
        'is_sent' => false,
    ], $attributes));
}

it('rechaza teléfonos vacíos con razón explícita', function (): void {
    $result = MassNotificationWhatsAppSender::send(
        ['phone' => '', 'fullName' => 'Test'],
        ['content' => 'Hola', 'type' => 'url'],
        throttle: false,
    );

    expect($result->success)->toBeFalse()
        ->and($result->errorMessage)->toContain('Teléfono vacío');
});

it('detecta respuestas de API UltraMsg exitosas y fallidas', function (): void {
    expect(MassNotificationWhatsAppSender::apiResponseSucceeded('{"sent":"true","id":"1"}', 200))->toBeTrue()
        ->and(MassNotificationWhatsAppSender::apiResponseSucceeded('{"error":"invalid phone number"}', 200))->toBeFalse()
        ->and(MassNotificationWhatsAppSender::apiResponseSucceeded('{"sent":"true"}', 500))->toBeFalse();
});

it('encola WhatsApp en un batch y programa el barrido final', function (): void {
    Bus::fake();

    $notification = createSweepTestNotification([
        'channels' => ['email', 'whatsapp'],
    ]);

    DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Destinatario',
        'email' => 'test@example.com',
        'phone' => '04141234567',
    ]);

    $result = MassNotificationDispatchService::dispatch($notification->fresh());

    expect($result->success)->toBeTrue()
        ->and($result->queuedJobs)->toBe(2)
        ->and($result->message)->toContain('reintentarán automáticamente');

    Bus::assertBatched(function ($batch) use ($notification): bool {
        return $batch->name === 'mass-notification-whatsapp-'.$notification->id
            && $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof SendNotificationMasive;
    });

    Bus::assertDispatched(SendNotificationMasiveEmail::class);
});

it('el barrido marca fallidos con la razón cuando el teléfono está vacío', function (): void {
    $notification = createSweepTestNotification([
        'is_sent' => true,
    ]);

    $recipient = DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Sin teléfono',
        'phone' => '   ',
        'whatsapp_status' => MassNotificationDeliveryStatus::Pending->value,
    ]);

    (new SweepMassNotificationWhatsAppFailures($notification->id))->handle();

    $recipient->refresh();

    expect($recipient->whatsapp_status)->toBe(MassNotificationDeliveryStatus::Failed)
        ->and($recipient->whatsapp_error)->toContain('Teléfono vacío');
});

it('el barrido ignora destinatarios ya enviados', function (): void {
    $notification = createSweepTestNotification([
        'is_sent' => true,
    ]);

    DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Ok',
        'phone' => '+584121234567',
        'whatsapp_status' => MassNotificationDeliveryStatus::Sent->value,
        'whatsapp_sent_at' => now(),
    ]);

    (new SweepMassNotificationWhatsAppFailures($notification->id))->handle();

    $row = DataNotification::query()->first();

    expect($row?->whatsapp_status)->toBe(MassNotificationDeliveryStatus::Sent)
        ->and($row?->whatsapp_error)->toBeNull();
});
