<?php

namespace App\Jobs;

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

class SendEmailPropuestaEconomica implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details = [];
    protected $collect = [];
    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct($details, $collect, $user)
    {
        $this->details = $details;
        $this->collect = $collect;
        $this->user = $user;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generatePDF($this->details, $this->collect);

        Notification::make()
            ->title('¡TAERA COMPLETADA!')
            ->body('📎 ' . $this->details['code'] . '.pdf ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/individual-quotes/' . $this->details['code'] . '.pdf')
            ])
            ->sendToDatabase($this->user);

    }

    private function generatePDF($details, $collect)
    {
        ini_set('memory_limit', '2048M');

        $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'collect'));
        $name_pdf = $details['code'] . '.pdf';
        $pdf->save(public_path('storage/individual-quotes/' . $name_pdf));

        /**
         * Despues de guardar el pdf lo enviamos por email
         * ----------------------------------------------------------------------------------------------------
         */
        Mail::to($details['email'])->send(new SendMailPropuestaPlanInicial($details['name'], $name_pdf));
    }

}