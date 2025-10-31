<?php

namespace App\Jobs;

use Throwable;
use Filament\Actions\Action;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Filament\Notifications\Notification;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use App\Mail\SendMailPropuestaPlanEspecial;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Http\Controllers\NotificationController;

class SendEmailPropuestaEconomicaMultiple implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $collect_final = [];
    protected $details_generals = [];
    protected $user;

    /**
     * Número máximo de intentos.
     *
     * @var int
     */
    public $tries = 5;

    /**
     * Tiempo en segundos para esperar antes de reintentar (opcional).
     *
     * @var int
     */
    public $backoff = 3; // Espera 3 segundos entre intentos

    /**
     * Create a new job instance.
     */
    public function __construct($collect_final, $details_generals, $user) 
    {
        $this->collect_final = $collect_final;
        $this->details_generals = $details_generals;
        $this->user = $user;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->generatePDF();
            
        Notification::make()
            ->title('¡TAREA COMPLETADA!')
            ->body('📎 '.$this->details_generals['code'].'.pdf ya se encuentra disponible para su descarga.')
            ->success()
            ->actions([
                Action::make('download')
                    ->label('Descargar archivo')
                    ->url('/storage/quotes/' . $this->details_generals['code'] . '.pdf')
            ])
            ->sendToDatabase($this->user);
        
    }

    private function generatePDF()
    {
        ini_set('memory_limit', '2048M');

            $details_generals = $this->details_generals;
        
            /**
             * Datos de la propuesta economica
             */
            $data_inicial = null;
            $group_collect_plan_inicial = null;
            $group_collect_plan_ideal = null;
            $group_collect_plan_especial = null;
            
            for ($i = 0; $i < count($this->collect_final); $i++) {
                if($this->collect_final[$i]['plan'] == 1 && !empty($this->collect_final[$i]['data'])){
                    $collect_plan_inicial = collect($this->collect_final[$i]['data']);
                    $group_collect_plan_inicial = $collect_plan_inicial;
                }
                if($this->collect_final[$i]['plan'] == 2 && !empty($this->collect_final[$i]['data'])){
                    $collect_plan_ideal = collect($this->collect_final[$i]['data']);
                    $group_collect_plan_ideal = $collect_plan_ideal->groupBy('age_range');
                }
                if($this->collect_final[$i]['plan'] == 3 && !empty($this->collect_final[$i]['data'])){
                    $collect_plan_especial = collect($this->collect_final[$i]['data']);
                    $group_collect_plan_especial = $collect_plan_especial->groupBy('age_range');
                }
                
            }

            if(!empty($group_collect_plan_inicial)){
                $data_inicial   =  (array) $group_collect_plan_inicial[0];
                $data_ideal     = $group_collect_plan_ideal;
                $data_especial  = $group_collect_plan_especial;

            }else{
                $data_ideal     = $group_collect_plan_ideal;
                $data_especial  = $group_collect_plan_especial;

            }
            // $data_inicial   =  (array) $group_collect_plan_inicial[0];
            // $data_ideal     = $group_collect_plan_ideal;
            // $data_especial  = $group_collect_plan_especial;


            /**
             * Logica para generar el pdf
             * ----------------------------------------------------------------------------------------------------
             */
            $name_user = $this->user->name;
            $pdf = Pdf::loadView('documents.propuesta-economica-multiple', compact('data_inicial', 'data_ideal', 'data_especial', 'details_generals', 'name_user'));
            $name_pdf = $details_generals['code'] . '.pdf';
            $pdf->save(public_path('storage/quotes/' . $name_pdf));

    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaMultiple: FAILED");
        Log::error($exception->getMessage());
        
        Notification::make()
            ->title('¡TAREA NO COMPLETADA!')
            ->body('Hubo un error en la creación de la propuesta economica. Por favor, contacte con el administrador del Sistema.')
            ->danger()
            ->sendToDatabase($this->user);

    }
}