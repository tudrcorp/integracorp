<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationEmailFailureLogger;
use App\Support\MassNotificationRecipientDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use RuntimeException;

class ReconcileOrphanedMassNotificationEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    /**
     * Minutos que un destinatario puede permanecer "pending" sin job en cola
     * antes de marcarse como fallido.
     */
    public const STALE_MINUTES = 10;

    public const QUEUE_NAME = 'system';

    public function handle(): void
    {
        $cutoff = now()->subMinutes(self::STALE_MINUTES);

        $orphans = DataNotification::query()
            ->where('email_status', 'pending')
            ->where('updated_at', '<=', $cutoff)
            ->orderBy('id')
            ->get();

        if ($orphans->isEmpty()) {
            return;
        }

        $marked = 0;

        foreach ($orphans as $recipient) {
            if ($this->hasQueuedEmailJob($recipient->id)) {
                continue;
            }

            $notification = MassNotification::query()->find($recipient->mass_notification_id);

            MassNotificationRecipientDelivery::markEmailFailed(
                $recipient->id,
                ReconcileMassNotificationEmailDelivery::ORPHAN_ERROR,
            );

            MassNotificationEmailFailureLogger::log(
                exception: new RuntimeException(ReconcileMassNotificationEmailDelivery::ORPHAN_ERROR),
                stage: 'scheduled_orphan_pending',
                record: $notification,
                email: $recipient->email,
                dataNotificationId: $recipient->id,
                context: [
                    'source' => self::class,
                    'stale_minutes' => self::STALE_MINUTES,
                    'queue_connection' => config('queue.default'),
                    'pending_since' => optional($recipient->updated_at)?->toDateTimeString(),
                ],
            );

            $marked++;
        }

        if ($marked > 0) {
            Log::error('ReconcileOrphanedMassNotificationEmails: pendientes huérfanos marcados como fallidos', [
                'marked' => $marked,
                'scanned' => $orphans->count(),
                'queue_connection' => config('queue.default'),
            ]);
        }
    }

    private function hasQueuedEmailJob(int $dataNotificationId): bool
    {
        return match (config('queue.default')) {
            'database' => $this->databaseHasQueuedEmailJob($dataNotificationId),
            'redis' => $this->redisHasQueuedEmailJob($dataNotificationId),
            default => false,
        };
    }

    private function databaseHasQueuedEmailJob(int $dataNotificationId): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('jobs')) {
            return false;
        }

        return DB::table('jobs')
            ->where('payload', 'like', '%SendNotificationMasiveEmail%')
            ->where(function ($query) use ($dataNotificationId): void {
                $query->where('payload', 'like', '%"dataNotificationId";i:'.$dataNotificationId.';%')
                    ->orWhere('payload', 'like', '%dataNotificationId":'.$dataNotificationId.'%')
                    ->orWhere('payload', 'like', '%data_notification_id":'.$dataNotificationId.'%');
            })
            ->exists();
    }

    private function redisHasQueuedEmailJob(int $dataNotificationId): bool
    {
        $connection = (string) config('queue.connections.redis.connection', 'default');
        $redis = Redis::connection($connection);
        $queue = self::QUEUE_NAME;
        $needles = [
            'SendNotificationMasiveEmail',
            (string) $dataNotificationId,
            'dataNotificationId',
        ];

        foreach ($this->redisQueuePayloads($redis, $queue) as $payload) {
            if ($this->payloadMatchesRecipient($payload, $needles)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  \Illuminate\Redis\Connections\Connection  $redis
     * @return list<string>
     */
    private function redisQueuePayloads($redis, string $queue): array
    {
        $payloads = [];

        foreach ($redis->lrange("queues:{$queue}", 0, -1) ?: [] as $payload) {
            $payloads[] = (string) $payload;
        }

        foreach (["queues:{$queue}:reserved", "queues:{$queue}:delayed"] as $key) {
            foreach ($redis->zrange($key, 0, -1) ?: [] as $payload) {
                $payloads[] = (string) $payload;
            }
        }

        return $payloads;
    }

    /**
     * @param  list<string>  $needles
     */
    private function payloadMatchesRecipient(string $payload, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! str_contains($payload, $needle)) {
                return false;
            }
        }

        return true;
    }
}
