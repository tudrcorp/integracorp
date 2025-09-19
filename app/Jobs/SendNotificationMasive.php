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
    protected $user;

    public $tries = 1;

    /**
     * Create a new job instance.
     */
    public function __construct($record, $user) 
    {
        $this->record = $record;
        $this->user = $user;
        //
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->sendNotifications();

        $recipient = User::where('is_designer', 1)->where('departament', 'MARKETING')->get();
        foreach ($recipient as $user) {
            $recipient_for_user = User::find($user->id);
            Notification::make()
                ->title('¡TAREA COMPLETADA!')
                ->body('La notificación masiva via WhatsApp ha sido enviada correctamente.')
                ->icon('heroicon-m-tag')
                ->iconColor('success')
                ->success()
                ->sendToDatabase($recipient_for_user);
        }
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

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error enviando la notificación masiva. Por favor, intente nuevamente.')
            ->danger()
            ->sendToDatabase($this->user);
    }
}