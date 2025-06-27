<?php

namespace App\Jobs;

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

class SendEmailPropuestaEconomicaPlanEspecial implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details = [];
    protected $group_collect = [];

    /**
     * Create a new job instance.
     */
    public function __construct($details, $group_collect)
    {
        $this->details = $details;
        $this->group_collect = $group_collect;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {

        ini_set('memory_limit', '2048M');

        $details = $this->details;
        $group_collect = $this->group_collect;
        $name_pdf = $details['code'] . '.pdf';

        /**
         * Logica para generar el pdf
         * ----------------------------------------------------------------------------------------------------
         */
        $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect'));
        $pdf->save(public_path('storage/' . $name_pdf));

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($details['email'])->send(new SendMailPropuestaPlanEspecial($details['name'], $name_pdf));

        /**
         * NOTIFICACION DE WHATSAPP
         * 
         * Enviaremos la propuesta economica por whatsapp
         * ----------------------------------------------------------------------------------------------------
         */

        $link = env('APP_URL') . '/storage/' . $name_pdf;

        $body = <<<HTML
 
            Hola, buenas tardes. 👋
            Espero se encuentre bien. 
            Le comento que el documento que recibió es la cotización correspondiente al Plan Especial , con todas las coberturas y tarifas detalladas. 
            Si tiene alguna duda o necesita más información, no dude en comunicarse con nosotros. 😊   

            Equipo Integracorp-TDC 
            📱 WhatsApp: (+58) 424 222 00 56
            ✉️ Email: comercial@tudrencasa.com 
 
        HTML;

        NotificationController::sendCotizaPlanInicial($details['phone'], $body, $link, $name_pdf);
        
    }
}