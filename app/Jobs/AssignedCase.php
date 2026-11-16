<?php

namespace App\Jobs;

use App\Enums\SystemNotificationKey;
use App\Http\Controllers\NotificationController;
use App\Mail\MailAssignedCase;
use App\Support\InternalNotifications\InternalMessaging;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class AssignedCase implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $phone;

    public $name;

    public $code;

    public $reason;

    public $name_patient;

    public $email;

    public $address;

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
        /**
         * Por Telegram si el médico lo tiene vinculado (sin el motivo de consulta, que va en el
         * correo y está en el portal); si no, el WhatsApp de siempre. Un reintento del job no
         * repite el aviso por Telegram.
         */
        if ($this->sentByTelegram()) {
            Mail::to($this->email)->send(new MailAssignedCase($this->code, $this->name, $this->name_patient, $this->reason));

            return;
        }

        // Notificacion
        NotificationController::assignedCase(
            $this->phone,
            $this->name,
            $this->code,
            $this->reason,
            $this->name_patient,
            $this->address
        );

        // email
        Mail::to($this->email)->send(new MailAssignedCase($this->code, $this->name, $this->name_patient, $this->reason));
    }

    private function sentByTelegram(): bool
    {
        $guard = 'assigned-case-telegram:'.sha1($this->code.'|'.$this->email.'|'.$this->phone);

        if (Cache::has($guard)) {
            return true;
        }

        $telegramBody = implode("\n", [
            '*Caso asignado · Telemedicina*',
            '',
            'Dr(a). '.$this->name.', el caso *#'.$this->code.'* fue asignado a su equipo.',
            '',
            'Paciente: '.$this->name_patient,
            'Dirección: '.$this->address,
            '',
            'El motivo de consulta y el detalle están en su correo y en el portal: https://integracorp.tudrgroup.com/telemedicina',
        ]);

        $sent = InternalMessaging::routeToPerson(
            SystemNotificationKey::DoctorCaseAssignment,
            'Caso asignado #'.$this->code,
            $telegramBody,
            null,
            is_string($this->phone) ? $this->phone : null,
            email: is_string($this->email) ? $this->email : null,
        );

        if ($sent) {
            Cache::put($guard, true, now()->addDay());
        }

        return $sent;
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info('AssignedCase: FAILED');
        Log::error($exception->getMessage());
    }
}
