<?php

namespace App\Jobs;

use Throwable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\MailUploadDataCorporate;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendNotificacionUploadDataCorporate implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $name_pdf;
    public $name_agent;
    public $code;

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
    public function __construct($name_pdf, $name_agent, $code)
    {
        $this->name_pdf = $name_pdf;
        $this->name_agent = $name_agent;
        $this->code = $code;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to(config('parameters.EMAIL_COTIZACIONES'))->send(new MailUploadDataCorporate($this->name_pdf, $this->name_agent, $this->code));
        Mail::to(config('parameters.EMAIL_AFILIACIONES'))->send(new MailUploadDataCorporate($this->name_pdf, $this->name_agent, $this->code));
    }
}