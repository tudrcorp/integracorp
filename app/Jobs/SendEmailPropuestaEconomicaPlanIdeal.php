<?php

namespace App\Jobs;

use Closure;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use App\Mail\SendMailPropuestaPlanIdeal;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendEmailPropuestaEconomicaPlanIdeal implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $details = [];
    protected $group_collect = [];
    protected $user;

    /**
     * Create a new job instance.
     */
    public function __construct($details, $group_collect, $user)
    {
        $this->details = $details;
        $this->group_collect = $group_collect;
        $this->user = $user;
    }


    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generatePDF($this->details, $this->group_collect);

        Notification::make()
            ->title('¡TAERA COMPLETADA!')
            ->body('📎 '.$this->details['code'].'.pdf ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/individual-quotes/' . $this->details['code'] . '.pdf')
            ])
            ->sendToDatabase($this->user);
    }

    private function generatePDF($details, $group_collect)
    {
        ini_set('memory_limit', '2048M');
        
        $pdf = Pdf::loadView('documents.propuesta-economica', compact('details', 'group_collect'));
        $name_pdf = $details['code'] . '.pdf';
        $pdf->save(public_path('storage/individual-quotes/' . $name_pdf));

    }
}