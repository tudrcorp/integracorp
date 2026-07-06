<?php

namespace App\Jobs;

use App\Models\MassNotification;
use App\Services\NotificationMasiveService;
use App\Support\MassNotificationRecipientDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendNotificationMasiveEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $backoff = 3;

    public function __construct(
        protected string $email,
        protected MassNotification $massNotification,
        protected int $dataNotificationId,
    ) {}

    public function handle(): void
    {
        NotificationMasiveService::sendEmail($this->email, $this->massNotification);
        MassNotificationRecipientDelivery::markEmailSent($this->dataNotificationId);
    }

    public function failed(?Throwable $exception): void
    {
        MassNotificationRecipientDelivery::markEmailFailed(
            $this->dataNotificationId,
            $exception?->getMessage() ?? 'Error desconocido en el job de correo',
        );

        Log::info('SendNotificationMasiveEmail: FAILED', [
            'data_notification_id' => $this->dataNotificationId,
            'email' => $this->email,
            'exception' => $exception,
        ]);
    }
}
