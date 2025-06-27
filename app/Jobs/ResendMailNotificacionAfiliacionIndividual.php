<?php

namespace App\Jobs;

use App\Mail\ReSendDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class ResendMailNotificacionAfiliacionIndividual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $email;
    protected $phone;
    protected $title;
    protected $name_ti;
    protected $name_pdf;

    /**
     * Create a new job instance.
     */
    public function __construct($email, $phone, $title, $name_ti, $name_pdf)
    {
        $this->email = $email;
        $this->phone = $phone;
        $this->title = $title;
        $this->name_ti = $name_ti;
        $this->name_pdf = $name_pdf;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        
        if ($this->email != null) {
            /**
             * Despues de guardar el pdf lo enviamos por email
             * ----------------------------------------------------------------------------------------------------
             */
            Mail::to($this->email)->send(new ReSendDocument($this->title, $this->name_ti, $this->name_pdf));
        }

        if ($this->phone != null) {

            /**
             * NOTIFICACION DE WHATSAPP
             * 
             * Enviaremos la propuesta economica por whatsapp
             * ----------------------------------------------------------------------------------------------------
             */
            $link = env('APP_URL') . '/storage/' . $this->name_pdf;

            $body = <<<HTML

                Hola, buenas tardes. 👋
                Espero se encuentre bien. 
                Le comento que el documento que recibió es la cotización correspondiente al Plan Especial , con todas las coberturas y tarifas detalladas. 
                Si tiene alguna duda o necesita más información, no dude en comunicarse con nosotros. 😊   

                Equipo Integracorp-TDC 
                📱 WhatsApp: (+58) 424 222 00 56
                ✉️ Email: comercial@tudrencasa.com 

            HTML;

            NotificationController::sendCotizaPlanInicial($this->phone, $body, $link, $this->name_pdf);
        }

        // if(!file_exists(public_path('storage/' . $name_pdf))){
        //     return;
        // }

    }
}