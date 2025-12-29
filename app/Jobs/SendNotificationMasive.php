<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\MassNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use App\Services\NotificationMasiveService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendNotificationMasive implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $dataNotificationArray;
    protected $infoNotificationArray;

    public $tries = 5;

    public int $timeout = 960; // 16 minutes

    /**
     * Create a new job instance.
     */
    public function __construct($dataNotificationArray, $infoNotificationArray) 
    {
        $this->dataNotificationArray = $dataNotificationArray;
        $this->infoNotificationArray = $infoNotificationArray;
        //
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            
            $this->sendNotifications($this->dataNotificationArray, $this->infoNotificationArray);
            
        } catch (Throwable $e) {
            Log::error($e->getMessage());
        }
    }

    private function sendNotifications($dataNotificationArray, $infoNotificationArray)
    {
        try {

            // $record = MassNotification::findOrFail($infoNotificationArray['id']);
            
            $masiveNotification = new NotificationMasiveService();
            $masiveNotification->send($dataNotificationArray, $infoNotificationArray);
            
        } catch (Throwable $e) {
            Log::error($e->getMessage());
        }

    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendNotificationMasive: FAILED");
        Log::error($exception->getMessage());
    }
}