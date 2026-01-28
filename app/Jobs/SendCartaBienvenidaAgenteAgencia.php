<?php

namespace App\Jobs;

use App\Http\Controllers\NotificationController;
use App\Mail\MailCartaBienvenidaAgenteAgencia;
use App\Mail\SendMailPropuestaPlanInicial;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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

        // Liberar memoria inmediatamente de la variable pesada
        unset($pdf);
        
        Mail::to($email)
            ->cc('tudrgroup.info@gmail.com')
            ->send(new MailCartaBienvenidaAgenteAgencia($id, $name, $name_pdf));

        Log::info("NEGOCIOS-AGENTES: Job CartaBienvenida completado con éxito.", [
            'id' => $this->id,
            'email'   => $this->email
        ]);
        
    }

    /**
     * Manejo centralizado de errores dentro del Job.
     */
    protected function reportError(Throwable $e, string $context): void
    {
        Log::error("NEGOCIOS-AGENTES: Error en Job SendCartaBienvenida [Contexto: {$context}]", [
            'id'      => $this->id,
            'message' => $e->getMessage(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ]);
    }

    /**
     * Método ejecutado cuando el Job falla definitivamente tras agotar todos los intentos.
     */
    public function failed(Throwable $exception): void
    {
        Log::critical("NEGOCIOS-AGENTES: El Job SendCartaBienvenida ha FALLADO definitivamente.", [
            'id'    => $this->id,
            'email' => $this->email,
            'error' => $exception->getMessage()
        ]);

        // Aquí podrías enviar una notificación interna a Slack o por DB a un administrador
    }
}