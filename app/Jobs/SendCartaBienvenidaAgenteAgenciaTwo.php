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
use App\Mail\MailCartaBienvenidaAgenteAgencia;
use App\Http\Controllers\NotificationController;
use App\Mail\MailCartaBienvenidaAgenteAgenciaTwo;

class SendCartaBienvenidaAgenteAgenciaTwo implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $code;
    public $name;
    public $email;

    /**
     * Create a new job instance.
     */
    public function __construct($code, $name, $email)
    {
        $this->code = $code;
        $this->name = $name;
        $this->email = $email;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        ini_set('memory_limit', '2048M');

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        $code = $this->code;
        $name = $this->name;
        $email = $this->email;

        $name_pdf = $code.'.pdf';

        $pdf = Pdf::loadView('documents.carta-bienvenida-agencia', compact('code', 'name'));
        $pdf->save(public_path('storage/' . $name_pdf));

        Mail::to($email)->send(new MailCartaBienvenidaAgenteAgenciaTwo($code, $name, $name_pdf));
    }
}