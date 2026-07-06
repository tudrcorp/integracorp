<?php

namespace App\Jobs;

use App\Support\MassNotificationDispatchService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class DispatchScheduledMassNotifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        $dueNotifications = MassNotificationDispatchService::dueScheduledNotifications();

        foreach ($dueNotifications as $notification) {
            $result = MassNotificationDispatchService::dispatch($notification);

            Log::info('DispatchScheduledMassNotifications', [
                'mass_notification_id' => $notification->id,
                'success' => $result->success,
                'message' => $result->message,
                'queued_jobs' => $result->queuedJobs,
            ]);
        }
    }
}
