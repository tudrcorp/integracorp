<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\DataNotification;
use App\Models\MassNotification;
use App\Support\MassNotificationEmailFailureLogger;
use App\Support\MassNotificationRecipientDelivery;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ReconcileMassNotificationEmailDelivery implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public const ORPHAN_ERROR = 'El job de correo no se completó. Posible causa: worker detenido, cola vaciada (queue:clear) o job perdido antes de ejecutarse.';

    public function __construct(
        public int $massNotificationId,
        public bool $allowRequeue = true,
    ) {}

    public function handle(): void
    {
        $notification = MassNotification::query()->find($this->massNotificationId);

        if ($notification === null) {
            Log::warning('ReconcileMassNotificationEmailDelivery: notificación no encontrada', [
                'mass_notification_id' => $this->massNotificationId,
            ]);

            return;
        }

        if (! in_array('email', (array) $notification->channels, true)) {
            return;
        }

        $pending = DataNotification::query()
            ->where('mass_notification_id', $this->massNotificationId)
            ->where('email_status', 'pending')
            ->orderBy('id')
            ->get();

        if ($pending->isEmpty()) {
            Log::info('ReconcileMassNotificationEmailDelivery: sin pendientes', [
                'mass_notification_id' => $this->massNotificationId,
                'allow_requeue' => $this->allowRequeue,
            ]);

            return;
        }

        if ($this->allowRequeue) {
            $this->requeuePending($notification, $pending);

            return;
        }

        $this->markPendingAsFailed($notification, $pending);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DataNotification>  $pending
     */
    private function requeuePending(MassNotification $notification, $pending): void
    {
        $jobs = [];
        $skippedEmpty = 0;

        foreach ($pending as $recipient) {
            $email = trim((string) ($recipient->email ?? ''));

            if ($email === '') {
                MassNotificationRecipientDelivery::markEmailFailed(
                    $recipient->id,
                    'Correo vacío o no disponible',
                );
                $skippedEmpty++;

                continue;
            }

            MassNotificationRecipientDelivery::markEmailPending($recipient->id);
            $jobs[] = new SendNotificationMasiveEmail(
                $email,
                $notification,
                $recipient->id,
            );
        }

        if ($jobs === []) {
            Log::info('ReconcileMassNotificationEmailDelivery: finalizado sin reencolar', [
                'mass_notification_id' => $this->massNotificationId,
                'skipped_empty' => $skippedEmpty,
            ]);

            return;
        }

        $massNotificationId = $this->massNotificationId;

        Bus::batch($jobs)
            ->name('mass-notification-email-retry-'.$massNotificationId)
            ->onQueue('system')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($massNotificationId): void {
                self::dispatch($massNotificationId, allowRequeue: false)
                    ->onQueue('system');
            })
            ->dispatch();

        Log::warning('ReconcileMassNotificationEmailDelivery: reencolando pendientes', [
            'mass_notification_id' => $this->massNotificationId,
            'requeued' => count($jobs),
            'skipped_empty' => $skippedEmpty,
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, DataNotification>  $pending
     */
    private function markPendingAsFailed(MassNotification $notification, $pending): void
    {
        foreach ($pending as $recipient) {
            MassNotificationRecipientDelivery::markEmailFailed(
                $recipient->id,
                self::ORPHAN_ERROR,
            );

            MassNotificationEmailFailureLogger::log(
                exception: new RuntimeException(self::ORPHAN_ERROR),
                stage: 'reconcile_orphan_pending',
                record: $notification,
                email: $recipient->email,
                dataNotificationId: $recipient->id,
                context: [
                    'source' => self::class,
                    'allow_requeue' => false,
                ],
            );
        }

        Log::error('ReconcileMassNotificationEmailDelivery: pendientes marcados como fallidos', [
            'mass_notification_id' => $this->massNotificationId,
            'failed_count' => $pending->count(),
            'emails' => $pending->pluck('email')->all(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception instanceof Throwable) {
            Log::error('ReconcileMassNotificationEmailDelivery: job falló', [
                'mass_notification_id' => $this->massNotificationId,
                'allow_requeue' => $this->allowRequeue,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
