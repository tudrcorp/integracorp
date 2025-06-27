<?php

namespace App\Jobs;

use App\Models\Plan;
use App\Models\Coverage;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMailTarjetaAfiliado;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendMailPropuestaPlanIdeal;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendTarjetaAfiliado implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $details;

    /**
     * Create a new job instance.
     */
    public function __construct($details)
    {
        $this->details = $details;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');

        $details = $this->details;
        $name_pdf = $details['code'] . '.pdf';
        $plan = Plan::find($details['plan_id'])->description;
        $coverage = Coverage::find($details['coverage_id'])->price;

        $pdf = Pdf::loadView('documents.tarjeta-afiliado', compact('details', 'plan', 'coverage'));
        $pdf->save(public_path('storage/' . $name_pdf));

        /**
         * Despues de guardar el certificado lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($details['email_con'])->send(new SendMailTarjetaAfiliado($details['full_name_con'], $name_pdf));
        //
    }
}