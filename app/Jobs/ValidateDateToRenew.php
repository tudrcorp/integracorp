<?php

namespace App\Jobs;

use Throwable;
use Carbon\Carbon;
use App\Models\Affiliation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Mail\NotificationRenewAffiliationMail;

class ValidateDateToRenew implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->validateDate();
    }

    private function validateDate()
    {
        $dates = DB::table('affiliations')
            ->select('id', 'code', 'agent_id', 'code_agency', 'effective_date')
            ->get()
            ->toArray();
        // dd($dates);
        $today = Carbon::createFromFormat('d/m/Y', now()->format('d/m/Y'))->format('Y-m-d');

        for ($i = 0; $i < count($dates); $i++) {
            
            if ($dates[$i]->effective_date == null) {
                continue;
            }

            if ($dates[$i]->effective_date != null) {
                
                $effectiveDate = Carbon::createFromFormat('d/m/Y', $dates[$i]->effective_date)->format('Y-m-d');

                if ($effectiveDate > $today) {
                    //1. Calculo los dias faltantes para lleguar al vencimiento
                    $diasFaltantes = Carbon::parse($today)->diffInDays($effectiveDate);
                    // dd($diasFaltantes);
                    //Faltan 30 dias?
                    if ($diasFaltantes == 30) {
    
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 30))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 20 dias?
                    if ($diasFaltantes == 20) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 20))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 15 dias?
                    if ($diasFaltantes == 15) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 15))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 10 dias?
                    if ($diasFaltantes == 10) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 10))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 7 dias?
                    if ($diasFaltantes == 7) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 7))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 5 dias?
                    if ($diasFaltantes == 5) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 5))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 4 dias?
                    if ($diasFaltantes == 4) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 4))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 3 dias?
                    if ($diasFaltantes == 3) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 3))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 2 dias?
                    if ($diasFaltantes == 2) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 2))->onQueue('renew'));
                        }
                    }
    
                    //Faltan 1 dias?
                    if ($diasFaltantes == 1) {
                        //Si la afiliacion pertenece a un agente
                        if ($dates[$i]->agent_id != null) {
                            //Si pertenece a un agente
                            $dataAgent = DB::table('agents')->select('name', 'email')->where('id', $dates[$i]->agent_id)->first();
                            Mail::to($dataAgent->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                        }
    
                        //si la afiliacion pertenece a una agencia
                        if ($dates[$i]->agent_id == null) {
                            //Si pertenece a un agente
                            $dataAgency = DB::table('agencies')->select('name_corporative', 'email')->where('code', $dates[$i]->code_agency)->first();
                            Mail::to($dataAgency->email)->queue((new NotificationRenewAffiliationMail($dates[$i]->code, 1))->onQueue('renew'));
                        }
                    }
    
                }
    
                if ($effectiveDate < $today) {
                    // dd('es menor');
                    //Actualizo el estatus
                    DB::table('affiliations')->where('code', $dates[$i]->code)->update([
                        'status' => 'VENCIDA-POR-RENOVAR',
                    ]);
                }
            }

        }
    }

    /**
     * Handle a job failure.
     * Trabajo Fallido
     */
    public function failed(?Throwable $exception): void
    {
        Log::info("SendEmailPropuestaEconomicaMultiple: FAILED");
        Log::error($exception->getMessage());

        // Send user notification of failure, etc...

    }
}