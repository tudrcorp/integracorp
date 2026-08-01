<?php

namespace App\Jobs;

use App\Models\MassNotification;
use App\Services\NotificationMasiveService;
use App\Support\MassNotificationEmailFailureLogger;
use App\Support\MassNotificationRecipientDelivery;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendNotificationMasiveEmail implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 3;

    public function __construct(
        protected string $email,
        protected MassNotification $massNotification,
        protected int $dataNotificationId,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        try {
            NotificationMasiveService::sendEmail($this->email, $this->massNotification);
            MassNotificationRecipientDelivery::markEmailSent($this->dataNotificationId);
        } catch (Throwable $exception) {
            MassNotificationEmailFailureLogger::log(
                exception: $exception,
                stage: 'job_attempt',
                record: $this->massNotification,
                email: $this->email,
                dataNotificationId: $this->dataNotificationId,
                context: [
                    'job' => self::class,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'will_retry' => $this->attempts() < $this->tries,
                    'queue' => $this->queue,
                ],
            );

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        MassNotificationRecipientDelivery::markEmailFailed(
            $this->dataNotificationId,
            $exception?->getMessage() ?? 'Error desconocido en el job de correo',
        );

        if ($exception instanceof Throwable) {
            MassNotificationEmailFailureLogger::log(
                exception: $exception,
                stage: 'job_failed_permanently',
                record: $this->massNotification,
                email: $this->email,
                dataNotificationId: $this->dataNotificationId,
                context: [
                    'job' => self::class,
                    'attempt' => $this->attempts(),
                    'max_tries' => $this->tries,
                    'queue' => $this->queue,
                ],
            );
        }
    }
}
