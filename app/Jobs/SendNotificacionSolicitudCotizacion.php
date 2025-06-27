<?php

namespace App\Jobs;

use App\Models\User;
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

class SendNotificacionSolicitudCotizacion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $record = [];

    /**
     * Create a new job instance.
     */
    public function __construct($record)
    {
        $this->record = $record;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        $user_name = User::select('agent_id', 'name')->where('agent_id', $this->record['agent_id'])->first('name');
        $email = 'gustavoalberto.camachop@gmail.com';
        Mail::to($email)->send(new MailNotificacionSolicitudCotizacion($this->record['full_name'], $this->record['code'], $user_name->name));
    }
}