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

    protected $recordID;

    public $tries = 5;

    public int $timeout = 960; // 16 minutes

    /**
     * Create a new job instance.
     */
    public function __construct($recordID) 
    {
        $this->recordID = $recordID;
        //
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            
            $this->sendNotifications($this->recordID);
            
        } catch (Throwable $e) {
            Log::error($e->getMessage());
        }
    }

    private function sendNotifications($id)
    {
        try {

            $record = MassNotification::findOrFail($id);
            
            $masiveNotification = new NotificationMasiveService();
            $masiveNotification->send($record);
            
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