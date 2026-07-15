<?php

declare(strict_types=1);

use App\Jobs\SendNotificationMasive;
use App\Jobs\SendNotificationMasiveEmail;
use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationDispatchService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
    ]);

    DB::purge('sqlite');
    DB::reconnect('sqlite');

    ensureMassNotificationDispatchTestSchema();

    Schema::connection('sqlite')->getColumnListing('mass_notifications');
    Schema::connection('sqlite')->getColumnListing('data_notifications');

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

    Carbon::setTestNow(Carbon::parse('2026-07-09 12:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ensureMassNotificationDispatchTestSchema(): void
{
    if (! Schema::hasTable('mass_notification_folders')) {
        Schema::create('mass_notification_folders', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        DB::table('mass_notification_folders')->insert([
            'id' => 1,
            'name' => 'Sin organizar',
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    if (! Schema::hasTable('mass_notifications')) {
        Schema::create('mass_notifications', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('mass_notification_folder_id')->nullable();
            $table->string('title');
            $table->text('content')->nullable();
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

function createApprovedMassNotificationForDispatchTest(array $attributes = []): MassNotification
{
    return MassNotification::query()->create(array_merge([
        'title' => 'Campaña de prueba',
        'content' => 'Contenido de prueba',
        'status' => 'APROBADA',
        'is_approved' => true,
        'channels' => ['email', 'whatsapp'],
        'is_sent' => false,
    ], $attributes));
}

function attachRecipientToMassNotification(MassNotification $notification): DataNotification
{
    return DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Destinatario Test',
        'email' => 'test@example.com',
        'phone' => '+584121234567',
    ]);
}

it('no envía de inmediato cuando la fecha programada es futura', function (): void {
    Queue::fake();

    $notification = createApprovedMassNotificationForDispatchTest([
        'date_programed' => now()->addHours(3),
    ]);
    attachRecipientToMassNotification($notification);

    $result = MassNotificationDispatchService::dispatch($notification->fresh());

    expect($result->success)->toBeTrue()
        ->and($result->queuedJobs)->toBe(0)
        ->and($result->message)->toContain('programada para el')
        ->and($notification->fresh()->is_sent)->toBeFalse();

    Queue::assertNothingPushed();
});

it('envía de inmediato cuando no hay fecha programada', function (): void {
    Bus::fake();

    $notification = createApprovedMassNotificationForDispatchTest([
        'date_programed' => null,
    ]);
    attachRecipientToMassNotification($notification);

    $result = MassNotificationDispatchService::dispatch($notification->fresh());

    expect($result->success)->toBeTrue()
        ->and($result->queuedJobs)->toBe(2)
        ->and($notification->fresh()->is_sent)->toBeTrue();

    Bus::assertBatched(fn ($batch): bool => $batch->jobs->count() === 1
        && $batch->jobs->first() instanceof SendNotificationMasive);
    Bus::assertDispatched(SendNotificationMasiveEmail::class);
});

it('rechaza el envío cuando la notificación ya fue encolada', function (): void {
    Queue::fake();

    $notification = createApprovedMassNotificationForDispatchTest([
        'is_sent' => true,
    ]);
    attachRecipientToMassNotification($notification);

    $result = MassNotificationDispatchService::dispatch($notification->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('ya fue encolada');

    Queue::assertNothingPushed();
});

it('rechaza el envío cuando la notificación no está aprobada', function (): void {
    Queue::fake();

    $notification = createApprovedMassNotificationForDispatchTest([
        'status' => 'POR-APROBAR',
        'is_approved' => false,
    ]);
    attachRecipientToMassNotification($notification);

    $result = MassNotificationDispatchService::dispatch($notification->fresh());

    expect($result->success)->toBeFalse()
        ->and($result->message)->toContain('debe estar aprobada');

    Queue::assertNothingPushed();
});

it('dueScheduledNotifications solo devuelve campañas vencidas pendientes', function (): void {
    $due = createApprovedMassNotificationForDispatchTest([
        'date_programed' => now()->subMinute(),
    ]);

    createApprovedMassNotificationForDispatchTest([
        'date_programed' => now()->addHour(),
    ]);

    createApprovedMassNotificationForDispatchTest([
        'date_programed' => now()->subMinute(),
        'is_sent' => true,
    ]);

    $results = MassNotificationDispatchService::dueScheduledNotifications();

    expect($results)->toHaveCount(1)
        ->and($results->first()?->id)->toBe($due->id);
});
