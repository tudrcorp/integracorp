<?php

namespace App\Jobs;

use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use App\Mail\SendMailPropuestaPlanInicial;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendEmailPropuestaEconomica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details = [];
    protected $collect = [];

    /**
     * Create a new job instance.
     */
    public function __construct($details, $collect)
    {
        $this->details = $details;
        $this->collect = $collect;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');
        
        $details = $this->details;
        $collect = $this->collect;
        // dd($details, $collect);
        
        $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'collect'));
        $name_pdf = $details['code'] . '.pdf';
        $pdf->save(public_path('storage/' . $name_pdf));

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($details['email'])->send(new SendMailPropuestaPlanInicial($details['name'], $name_pdf));

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
            Le comento que el documento que recibió es la cotización correspondiente al Plan Inicial , con todas las coberturas y tarifas detalladas. 
            Si tiene alguna duda o necesita más información, no dudes den comunicarse con nosotros. 😊   

            Equipo Integracorp-TDC
            📱 WhatsApp: (+58) 424 222 00 56
            ✉️ Email: comercial@tudrencasa.com 

        HTML;
            
        NotificationController::sendCotizaPlanInicial($details['phone'], $body, $link, $name_pdf);

        $user = User::find(Auth::user()->id);
        Notification::make()
            ->title('Éxito')
            ->body('La cotización ha sido generada correctamente.')
            ->success()
            ->sendToDatabase($user);

    }

}