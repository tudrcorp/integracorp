<?php

namespace App\Jobs;

use App\Mail\CertificateEmail;
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

class SendNotificacionAfiliacionIndividual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $full_name;
    public $email;
    public $name_pdf;
    public $data_ti;
    public $afiliates;
    

    /**
     * Create a new job instance.
     */
    public function __construct($full_name, $email, $name_pdf, $data_ti, $afiliates)
    {
        $this->full_name = $full_name;
        $this->email = $email;
        $this->name_pdf = $name_pdf;
        $this->data_ti = $data_ti;
        $this->afiliates = $afiliates;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');
        
        $full_name = $this->full_name;
        $email = $this->email;
        $name_pdf = $this->name_pdf;
        $data_ti = $this->data_ti;
        $afiliates = $this->afiliates;
        
        $pdf = Pdf::loadView('documents.certificate', compact('data_ti', 'afiliates'));
        $pdf->save(public_path('storage/' . $name_pdf));

        /**
         * Despues de guardar el certificado lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($email)->send(new CertificateEmail($full_name, $afiliates, $name_pdf));
        //

    }
}