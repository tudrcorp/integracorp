<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendMailPropuestaPlanIdeal;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;
use App\Mail\MailNotificacionSolicitudCotizacion;

class CreateAvisoDeCobro implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;

    protected $data = [];

    /**
     * Create a new job instance.
     */
    public function __construct($data)
    {
        $this->data = $data;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

            ini_set('memory_limit', '2048M');

            $data = $this->data;
            // dd($data);
            $name_pdf = 'ADP-' . $data['invoice_number'] . '.pdf';

            $pdf = Pdf::loadView('documents.aviso-de-cobro', compact('data'));
            $pdf->save(public_path('storage/avisoDeCobro/' . $name_pdf));

            Log::info('AVISO DE COBRO CREADO'.$name_pdf);

        } catch (\Exception $e) {
            Log::error($e->getMessage());
        }
        
    }
}