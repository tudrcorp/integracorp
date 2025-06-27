<?php

namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
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

    /**
     * Create a new job instance.
     */
    public function __construct($collect_final, $details_generals) 
    {
        $this->collect_final = $collect_final;
        $this->details_generals = $details_generals;
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {

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

            /**
             * Datos generales
             */
            // $data_inicial   =  (array) $group_collect_plan_inicial[0];
            // $data_ideal     = $group_collect_plan_ideal;
            // $data_especial  = $group_collect_plan_especial;
            // $data_ideal     = $group_collect_plan_ideal;
            // $data_especial  = $group_collect_plan_especial;

            $data_inicial   =  $group_collect_plan_inicial;
            $data_ideal     = $group_collect_plan_ideal;
            $data_especial  = $group_collect_plan_especial;


            /**
             * Logica para generar el pdf
             * ----------------------------------------------------------------------------------------------------
             */
            $pdf = Pdf::loadView('documents.propuesta-economica-multiple', compact('data_inicial', 'data_ideal', 'data_especial', 'details_generals'));
            $name_pdf = $details_generals['code'] . '.pdf';
            $pdf->save(public_path('storage/' . $name_pdf));
            //

        } catch (\Throwable $th) {
           Log::info($th);
        }
        
    }
}