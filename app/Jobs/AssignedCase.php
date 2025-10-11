<?php

namespace App\Jobs;

use Throwable;
use App\Mail\MailAssignedCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class AssignedCase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phone, $name, $code, $reason, $name_patient, $email, $address;


    /**
     * Número máximo de intentos.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Tiempo en segundos para esperar antes de reintentar (opcional).
     *
     * @var int
     */
    public $backoff = 3; // Espera 3 segundos entre intentos

    /**
     * Create a new job instance.
     */
    public function __construct($phone, $name, $code, $reason, $name_patient, $email, $address)
    {
        $this->phone = $phone;
        $this->name = $name;
        $this->code = $code;
        $this->reason = $reason;
        $this->name_patient = $name_patient;
        $this->email = $email;
        $this->address = $address;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->sendNotificacionAndEmail();
    }

    private function sendNotificacionAndEmail(): void
    {
        //Notificacion
        NotificationController::assignedCase(
            $this->phone,
            $this->name,
            $this->code,
            $this->reason,
            $this->name_patient,
            $this->address
        );

        //email
        Mail::to($this->email)->send(new MailAssignedCase($this->code, $this->name, $this->name_patient, $this->reason));
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("AssignedCase: FAILED");
        Log::error($exception->getMessage());
    }
}