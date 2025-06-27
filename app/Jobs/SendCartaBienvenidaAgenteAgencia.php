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

class SendCartaBienvenidaAgenteAgencia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $id;
    public $name;
    public $email;
    public $type = null;

    /**
     * Create a new job instance.
     */
    public function __construct($id, $name, $email)
    {
        $this->id = $id;
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
        $id = $this->id;
        $name = $this->name;
        $email = $this->email;
        
        $name_pdf = 'AGT-000' .$id. '.pdf';
        
        $pdf = Pdf::loadView('documents.carta-bienvenida-agente', compact('id', 'name'));
        $pdf->save(public_path('storage/' . $name_pdf));
        
        Mail::to($email)->send(new MailCartaBienvenidaAgenteAgencia($id, $name, $name_pdf));
    }
}