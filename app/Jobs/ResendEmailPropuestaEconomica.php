<?php

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\SendMailPropuestaPlanEspecial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class ResendEmailPropuestaEconomica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $record = [];

    protected $email;

    protected $phone;

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
    public function __construct($record, $email, $phone)
    {
        $this->record = $record;
        $this->email = $email;
        $this->phone = $phone;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $name_pdf = $this->record['code'].'.pdf';

        if ($this->email != null) {
            /**
             * Despues de guardar el pdf lo enviamos por email
             * ----------------------------------------------------------------------------------------------------
             */
            Mail::to($this->email)
                ->cc('cotizacionestdg.ve@gmail.com')
                ->bcc('solrodriguez@tudrencasa.com')
                ->send(new SendMailPropuestaPlanEspecial($this->record['full_name'], $name_pdf));
        }

        if ($this->phone != null) {

            /**
             * NOTIFICACION DE WHATSAPP
             *
             * Enviaremos la propuesta economica por whatsapp
             * ----------------------------------------------------------------------------------------------------
             */
            $link = env('APP_URL').'/storage/quotes/'.$name_pdf;

            $body = <<<'HTML'

                Hola, buenas tardes. 👋
                Espero se encuentre bien. 
                Le comento que el documento que recibió es la cotización correspondiente al Plan Especial , con todas las coberturas y tarifas detalladas. 
                Si tiene alguna duda o necesita más información, no dude en comunicarse con nosotros. 😊   

                Equipo Integracorp-TDC 
                📱 WhatsApp: (+58) 424 222 00 56
                ✉️ Email: comercial@tudrencasa.com 

            HTML;

            NotificationController::sendQuote($this->phone, $body, $link, $name_pdf);
        }

        // if(!file_exists(public_path('storage/' . $name_pdf))){
        //     return;
        // }

    }
}
