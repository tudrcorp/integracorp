<?php

declare(strict_types=1);

use App\Enums\MassNotificationDeliveryStatus;
use App\Jobs\ReconcileMassNotificationEmailDelivery;
use App\Jobs\ReconcileOrphanedMassNotificationEmails;
use App\Jobs\SendNotificationMasiveEmail;
use App\Models\DataNotification;
use App\Models\MassNotification;
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

    ensureMassNotificationEmailReconcileSchema();

    DB::table('data_notifications')->delete();
    DB::table('mass_notifications')->delete();
    DB::table('mass_notification_folders')->delete();
    DB::table('jobs')->delete();
    DB::table('mass_notification_folders')->insert([
        'id' => 1,
        'name' => 'Sin organizar',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-31 20:00:00', 'America/Caracas'));
});

afterEach(function (): void {
    Carbon::setTestNow();
});

function ensureMassNotificationEmailReconcileSchema(): void
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
            $table->boolean('is_sent')->default(false);
            $table->boolean('is_approved')->default(false);
            $table->string('status')->nullable();
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
            $table->timestamps();
        });
    }

    if (! Schema::hasTable('jobs')) {
        Schema::create('jobs', function (Blueprint $table): void {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
    }
}

function createEmailReconcileNotification(): MassNotification
{
    return MassNotification::query()->create([
        'title' => 'Campaña email reconcile',
        'content' => 'Contenido',
        'status' => 'APROBADA',
        'is_approved' => true,
        'channels' => ['email'],
        'is_sent' => true,
    ]);
}

it('reencola pendientes en el primer reconcile de email', function (): void {
    Bus::fake();

    $notification = createEmailReconcileNotification();
    $pending = DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Pendiente',
        'email' => 'pending@example.com',
        'email_status' => MassNotificationDeliveryStatus::Pending->value,
    ]);

    (new ReconcileMassNotificationEmailDelivery($notification->id, allowRequeue: true))->handle();

    Bus::assertBatched(function ($batch) use ($pending): bool {
        return $batch->name === 'mass-notification-email-retry-'.$pending->mass_notification_id
            && $batch->jobs->count() === 1
            && $batch->jobs->first() instanceof SendNotificationMasiveEmail;
    });

    expect($pending->fresh()->email_status)->toBe(MassNotificationDeliveryStatus::Pending);
});

it('marca pendientes como fallidos en el reconcile final de email', function (): void {
    $notification = createEmailReconcileNotification();
    $pending = DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Huérfano',
        'email' => 'orphan@example.com',
        'email_status' => MassNotificationDeliveryStatus::Pending->value,
    ]);

    (new ReconcileMassNotificationEmailDelivery($notification->id, allowRequeue: false))->handle();

    $pending->refresh();

    expect($pending->email_status)->toBe(MassNotificationDeliveryStatus::Failed)
        ->and($pending->email_error)->toContain('job de correo no se completó');
});

it('el job programado marca pendientes huérfanos sin job en cola', function (): void {
    config(['queue.default' => 'database']);

    $notification = createEmailReconcileNotification();
    $orphan = DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Stale',
        'email' => 'stale@example.com',
        'email_status' => MassNotificationDeliveryStatus::Pending->value,
    ]);
    DB::table('data_notifications')->where('id', $orphan->id)->update([
        'updated_at' => now()->subMinutes(ReconcileOrphanedMassNotificationEmails::STALE_MINUTES + 1),
    ]);

    $freshPending = DataNotification::query()->create([
        'mass_notification_id' => $notification->id,
        'fullName' => 'Fresh',
        'email' => 'fresh@example.com',
        'email_status' => MassNotificationDeliveryStatus::Pending->value,
    ]);

    (new ReconcileOrphanedMassNotificationEmails)->handle();

    expect($orphan->fresh()->email_status)->toBe(MassNotificationDeliveryStatus::Failed)
        ->and($orphan->fresh()->email_error)->toContain('job de correo no se completó')
        ->and($freshPending->fresh()->email_status)->toBe(MassNotificationDeliveryStatus::Pending);
});

it('soporta detección de jobs pendientes en redis y database', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/app/Jobs/ReconcileOrphanedMassNotificationEmails.php');

    expect($src)->toContain("'redis' => \$this->redisHasQueuedEmailJob")
        ->and($src)->toContain("'database' => \$this->databaseHasQueuedEmailJob")
        ->and($src)->toContain('queues:{$queue}');
});

it('agenda el reconcile de emails huérfanos en el scheduler', function (): void {
    $src = file_get_contents(dirname(__DIR__, 2).'/routes/console.php');

    expect($src)->toContain('ReconcileOrphanedMassNotificationEmails')
        ->and($src)->toContain('everyFiveMinutes()');
});
