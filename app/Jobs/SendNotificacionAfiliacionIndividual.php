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
    public $data;
    public $afiliates;
    

    /**
     * Create a new job instance.
     */
    public function __construct($full_name = null, $email = null, $name_pdf = null, $data = null, $afiliates = null)
    {
        $this->full_name = $full_name;
        $this->email = $email;
        $this->name_pdf = $name_pdf;
        $this->data = $data;
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
        $data = $this->data;
        $afiliates = $this->afiliates;

        Log::info($full_name);
        Log::info($email);
        Log::info($name_pdf);
        Log::info($data);
        Log::info($afiliates);
        
        $pdf = Pdf::loadView('documents.certificate', compact('data', 'afiliates'));
        $pdf->save(public_path('storage/certificates/' . $name_pdf));

        /**
         * Despues de guardar el certificado lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($email)->send(new CertificateEmail($full_name, $afiliates, $name_pdf));
        //

    }
}