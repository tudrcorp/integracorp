<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
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

    protected $record;

    public $tries = 1;

    public int $timeout = 600;

    /**
     * Create a new job instance.
     */
    public function __construct($record) 
    {
        $this->record = $record;
        //
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->sendNotifications();
    }

    private function sendNotifications()
    {
        $masiveNotification = new NotificationMasiveService();
        $masiveNotification->send($this->record);
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaMultiple: FAILED");
        Log::error($exception->getMessage());
    }
}