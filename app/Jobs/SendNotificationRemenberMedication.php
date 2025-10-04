<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Queue\Queueable;
use App\Services\NotificationMasiveService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendNotificationRemenberMedication implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->notification();
    }

    private function notification()
    {
        $masiveNotification = new NotificationMasiveService();
        $masiveNotification->notificationRemenberMedication();
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendNotificationRemenberMedication: FAILED");
        Log::error($exception->getMessage());
    }
}