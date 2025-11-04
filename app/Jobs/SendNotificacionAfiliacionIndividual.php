<?php

namespace App\Jobs;

use Throwable;
use App\Models\User;
use Filament\Actions\Action;
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

class SendNotificacionAfiliacionIndividual implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $pagador;
    protected $beneficios_table;
    protected $name_pdf;
    protected $afiliates;
    protected $user;
    

    /**
     * Create a new job instance.
     */
    public function __construct($pagador, $beneficios_table, $name_pdf, $afiliates, $user)
    {
        $this->pagador          = $pagador;
        $this->beneficios_table = $beneficios_table;
        $this->name_pdf         = $name_pdf;
        $this->afiliates        = $afiliates;
        $this->user             = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generateCertificate($this->pagador, $this->beneficios_table, $this->name_pdf, $this->afiliates);

        Log::info('Se ha generado el certificado de afiliacion');
        Log::info($this->name_pdf);
        Log::info($this->user);
        
        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 ' . $this->name_pdf . ' ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/certificados-doc/' . $this->name_pdf)
            ])
            ->sendToDatabase($this->user);
    }

    private function generateCertificate($pagador, $beneficios_table, $name_pdf, $afiliates)
    {
        ini_set('memory_limit', '2048M');

        Log::info($pagador);
        Log::info($beneficios_table);
        Log::info($name_pdf);
        Log::info($afiliates);

        $pdf = Pdf::loadView('documents.certificate', compact('pagador', 'beneficios_table', 'afiliates'));
        $pdf->save(public_path('storage/certificados-doc/' . $name_pdf));

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        // Mail::to($details['email'])->send(new SendMailPropuestaPlanInicial($details['name'], $name_pdf));
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendNotificacionAfiliacionIndividual: FAILED");
        Log::error($exception->getMessage());

        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación del certificado. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

        // Send user notification of failure, etc...

    }
}