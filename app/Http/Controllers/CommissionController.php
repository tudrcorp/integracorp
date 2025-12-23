<?php

namespace App\Http\Controllers;

use PgSql\Lob;
use App\Models\Agent;
use App\Models\Agency;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CommissionPayroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class CommissionController extends Controller
{
    public static function calculateCommissionAgente($agent_id, $record)
    {
        
        try {

            $porcentaje_agente = 0;

            //Concultamos la tabla de agente para traernos el porcentaje de comision
            $commission_tdec_agent = Agent::where('id', $agent_id)->first()->commission_tdec;

            //Validamos el monto pagado por el cliente para calcular la comision
            
            //Pago solo en USD
            if($record->pay_amount_usd > 0 && $record->pay_amount_ves == 0){
                
                $money = 'usd';
                $porcentaje_agente = $record->pay_amount_usd * $commission_tdec_agent / 100;
                
            }

            //Pago solo en BS
            if ($record->pay_amount_usd == 0 && $record->pay_amount_ves > 0) {
                
                $money = 'ves';

                //conversion de dolares a bolivares del monto a apagar
                $ves = $record->total_amount * $record->tasa_bcv;
                $porcentaje_agente = $ves * $commission_tdec_agent / 100;
            }

            //Pago Multiple en USD y BS
            //Cuando el pago es multiple la comision se calcula en bolivares
            if ($record->pay_amount_usd > 0 && $record->pay_amount_ves > 0) {
                
                $money = 'ves';
                
                //conversion de dolares a bolivares del monto a apagar
                $ves = $record->pay_amount_usd * $record->tasa_bcv;
                
                $total = $ves + $record->pay_amount_ves;
                
                //Calculamos la comision en bolivares
                $porcentaje_agente = $total * $commission_tdec_agent / 100;
            }
 
            $res = [
                'porcentaje_agente' => $porcentaje_agente,
                'money'             => $money,
                'porcent_agent'     => $commission_tdec_agent
            ];

            return $res;

            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    public static function calculateCommissionGeneral($code, $record, $porcentaje_agente)
    {
        try {

            $porcentaje_agencia_general = 0;
            $porcentaje_agencia_master = 0;

            //Concultamos la tabla de agente para traernos el porcentaje de comision
            $data_agency_general = Agency::where('code', $code)->first();

            //CALCULAMOS LA COMISION PARA LA AGENCIA GENERAL
            //Validamos el monto pagado por el cliente para calcular la comision
            //Pago solo en USD
            if ($record->pay_amount_usd > 0 && $record->pay_amount_ves == 0) {
                
                $money = 'usd';

                // porcentaje_dinamico = porcentaje_agente - porcentaje_agencia_general
                $porcentaje_dinamico = $porcentaje_agente - $data_agency_general->commission_tdec;
                Log::info('porcentaje general '.$porcentaje_dinamico);
                
                $porcentaje_agencia_general = $record->pay_amount_usd * $porcentaje_dinamico / 100;
            }

            //Pago solo en BS
            if ($record->pay_amount_usd == 0 && $record->pay_amount_ves > 0) {
                
                $money = 'ves';

                // porcentaje_dinamico = porcentaje_agente - porcentaje_agencia_general
                $porcentaje_dinamico = $porcentaje_agente - $data_agency_general->commission_tdec;
                Log::info('porcentaje general'.$porcentaje_dinamico);
                
                //conversion de dolares a bolivares del monto a apagar
                $ves = $record->total_amount * $record->tasa_bcv;
                $porcentaje_agencia_general = $ves * $porcentaje_dinamico / 100;
            }

            //Pago Multiple en USD y BS
            //Cuando el pago es multiple la comision se calcula en bolivares
            if ($record->pay_amount_usd > 0 && $record->pay_amount_ves > 0) {

                $money = 'ves';

                // porcentaje_dinamico = porcentaje_agente - porcentaje_agencia_general
                $porcentaje_dinamico = $porcentaje_agente - $data_agency_general->commission_tdec;
                Log::info('porcentaje general' . $porcentaje_dinamico);

                //conversion de dolares a bolivares del monto a apagar
                $ves = $record->pay_amount_usd * $record->tasa_bcv;
                $total = $ves + $record->pay_amount_ves;

                //Calculamos la comision en bolivares
                $porcentaje_agencia_general = $total * $porcentaje_dinamico / 100;
                
            }

            //AHORA DETERMINAMOS SI LA AGENCIA GENERAL PERTENECE A UNA AGENCIA MASTER
            if ($data_agency_general->owner_code != 'TDG-100') {

                //ESTA AGENCIA PERTENECE A UNA AGENCIA MASTER
                $data_agency_master = Agency::where('code', $data_agency_general->owner_code)->first();

                if ($record->pay_amount_usd > 0 && $record->pay_amount_ves == 0) {
                    
                    $money = 'usd';
                    
                    $porcentaje_dinamico_agencia_master = $data_agency_general->commission_tdec - $data_agency_master->commission_tdec;
                    Log::info('porcentaje master cuando tiene una agencia general'.$porcentaje_dinamico_agencia_master);
                    
                    $porcentaje_agencia_master = $record->pay_amount_usd * $porcentaje_dinamico_agencia_master / 100;
                }

                //Pago solo en BS
                if ($record->pay_amount_usd == 0 && $record->pay_amount_ves > 0) {
                    $money = 'ves';
                    //conversion de dolares a bolivares del monto a apagar
                    $porcentaje_dinamico_agencia_master = $data_agency_general->commission_tdec - $data_agency_master->commission_tdec;
                    Log::info('porcentaje master cuando tiene una agencia general' . $porcentaje_dinamico_agencia_master);
                    
                    $ves = $record->total_amount * $record->tasa_bcv;
                    
                    $porcentaje_agencia_master = $ves * $porcentaje_dinamico_agencia_master / 100;
                }

                //Cuando el pago es multiple la comision se calcula en bolivares
                if ($record->pay_amount_usd > 0 && $record->pay_amount_ves > 0) {

                    $money = 'ves';

                    // porcentaje_dinamico = porcentaje_agente - porcentaje_agencia_general
                    $porcentaje_dinamico_agencia_master = $data_agency_general->commission_tdec - $data_agency_master->commission_tdec;
                    Log::info('porcentaje master cuando tiene una agencia general' . $porcentaje_dinamico_agencia_master);

                    //conversion de dolares a bolivares del monto a apagar
                    $ves = $record->pay_amount_usd * $record->tasa_bcv;
                    $total = $ves + $record->pay_amount_ves;

                    //Calculamos la comision en bolivares
                    $porcentaje_agencia_master = $total * $porcentaje_dinamico_agencia_master / 100;
                }
            }

            $res = [
                'porcentaje_agencia_general'    => abs($porcentaje_agencia_general), //$porcentaje_agencia_general,
                'porcentaje_agencia_master'     => abs($porcentaje_agencia_master), //$porcentaje_agencia_master,
                'money'                         => $money,
                'porcent_gral'                  => abs($porcentaje_dinamico),
                'porcent_master'                => isset($porcentaje_dinamico_agencia_master) ? abs($porcentaje_dinamico_agencia_master) : 0
            ];

            return $res;
            
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
            //throw $th;
        }
    }

    public static function calculateCommissionMaster($code, $record, $porcentaje_agente)
    {
        try {

            $porcentaje_agencia_master = 0;

            $commission_tdec_agency_master = Agency::where('code', $code)->first()->commission_tdec;

            //Pago solo en USD
            if ($record->pay_amount_usd > 0 && $record->pay_amount_ves == 0) {
                // dd($record->pay_amount_usd);
                $money = 'usd';

                //Calculo del procentaje dinamico
                $porcentaje_dinamico = $porcentaje_agente - $commission_tdec_agency_master;
                Log::info('porcentaje master'.$porcentaje_dinamico);
                
                $porcentaje_agencia_master = $record->pay_amount_usd * $porcentaje_dinamico / 100;

            }

            //Pago solo en BS
            if ($record->pay_amount_usd == 0 && $record->pay_amount_ves > 0) {
                
                $money = 'ves';

                $porcentaje_dinamico = $porcentaje_agente - $commission_tdec_agency_master;
                Log::info('porcentaje master' . $porcentaje_dinamico);
                
                $ves = $record->total_amount * $record->tasa_bcv;
                $porcentaje_agencia_master = $ves * $porcentaje_dinamico / 100;
            }

            //Pago Multiple en USD y BS
            //Cuando el pago es multiple la comision se calcula en bolivares
            if ($record->pay_amount_usd > 0 && $record->pay_amount_ves > 0) {

                $money = 'ves';

                // porcentaje_dinamico = porcentaje_agente - porcentaje_agencia_general
                $porcentaje_dinamico = $porcentaje_agente - $commission_tdec_agency_master;
                Log::info('porcentaje master' . $porcentaje_dinamico);

                //conversion de dolares a bolivares del monto a apagar
                $ves = $record->pay_amount_usd * $record->tasa_bcv;
                $total = $ves + $record->pay_amount_ves;

                //Calculamos la comision en bolivares
                $porcentaje_agencia_master = $total * $porcentaje_dinamico / 100;
            }

            //Pago Multiple en USD y BS
            // if ($record->pay_amount_usd > 0 && $record->reference_payment_ves > 0) {
            //     $porcentaje_agente = ($record->pay_amount_usd + $record->reference_payment_ves) * $commission_tdec_agent / 100;
            // }

            $res = [
                'porcentaje_agencia_master' => abs($porcentaje_agencia_master),
                'money'                     => $money,
                'porcent_master'            => abs($porcentaje_dinamico)
            ];

            return $res;   
            
            
        } catch (\Throwable $th) {
            dd($th);  
            Log::error($th->getMessage());
            //throw $th;
        }
    }

    public static function calculateCommission($dataArray)
    {
        try {
            // dd($dataArray);
            /**
             * Agrupamos solo lasa agencias master
             * Son agencias donde el owner_code == TDG-100
             */
            $master = collect($dataArray)
                ->filter(fn($item) => !is_null($item['owner_code']) && $item['owner_code'] == 'TDG-100') // opcional: ignorar agentes nulos
                ->groupBy('owner_code')
                ->map(function (Collection $group, $owner_code) {
                    return [
                        'owner_code'                    => $owner_code,
                        'code_agency'                   => $group->first()['code_agency'],
                        'total_commission_master'       => $group->sum('commission_master'),
                        'commission_master'             => (float) $group->sum(fn($item) => (float) $item['commission_agency_master']),
                        'commission_agency_master_usd'  => (float) $group->sum(fn($item) => (float) $item['commission_agency_master_usd']),
                        'commission_agency_master_ves'  => (float) $group->sum(fn($item) => (float) $item['commission_agency_master_ves']),
                    ];
                })
                ->values() // Reindexa numéricamente
                ->toArray();

            $general = collect($dataArray)
                ->map(function ($item) {
                    // Filtramos solo los que cumplen la condición: owner_code != code_agency
                    if ($item['owner_code'] !== $item['code_agency'] && $item['owner_code'] !== 'TDG-100') {
                        return [
                            'owner_code'                    => $item['owner_code'],
                            'code_agency'                   => $item['code_agency'],
                            'commission'                    => (float)$item['commission_agency_general'],
                            'commission_agency_general_usd' => (float)$item['commission_agency_general_usd'],
                            'commission_agency_general_ves' => (float)$item['commission_agency_general_ves'],
                        ];
                    }

                    // Si no cumple, retornamos null para ignorarlo después
                    return null;
                })
                ->filter() // Elimina los nulls
                ->groupBy('code_agency')
                ->map(function ($group, $codeAgency) {
                    return [
                        'owner_code'                    => $group->first()['owner_code'],
                        'code_agency'                   => $codeAgency,
                        'total_commission_general'      => $group->sum('commission'),
                        'commission_agency_general_usd' => (float) $group->sum(fn($item) => (float) $item['commission_agency_general_usd']),
                        'commission_agency_general_ves' => (float) $group->sum(fn($item) => (float) $item['commission_agency_general_ves']),
                    ];
                })
                ->values()
                ->toArray();

            $agent = collect($dataArray)
                ->filter(fn($item) => !is_null($item['agent_id'])) // opcional: ignorar agentes nulos
                ->groupBy('agent_id')
                ->map(function (Collection $group, $agentId) {
                    return [
                        'owner_code'           => $group->first()['owner_code'],
                        'code_agency'          => $group->first()['code_agency'],
                        'agent_id'             => $agentId,
                        'commission_agent'     => (float) $group->sum(fn($item) => (float) $item['commission_agent']),
                        'commission_agent_usd' => (float) $group->sum(fn($item) => (float) $item['commission_agent_usd']),
                        'commission_agent_ves' => (float) $group->sum(fn($item) => (float) $item['commission_agent_ves']),

                    ];
                })
                ->values() // Reindexa numéricamente
                ->toArray();

            $final_array = [
                'master'  => $master,
                'general' => $general,
                'agent'   => $agent
            ];

            // dd($final_array);

            /** Creamos el asiento en la tabla de commission_payrolls */

            /**Informacion general de la tabla */
            $first_array = collect($dataArray);
            // dd(DB::table('agencies')->select('name_corporative')->where('code', 'TDG-101')->first()->name_corporative);
            $code       = 'TDEC-RC-' . date('mY') . '-' . rand(11111, 99999);
            $code_pcc   = $first_array->first()['code'];
            $date_ini   = $first_array->first()['date_ini'];
            $date_end   = $first_array->first()['date_end'];

            /** 1.- Creamos el asiento para las agencias master */
            for ($index = 0; $index < count($final_array['master']); $index++) {

                $data_master = Agency::where('code', $final_array['master'][$index]['code_agency'])->where('owner_code', 'TDG-100')->first();

                $commission_payrolls = new CommissionPayroll();
                $commission_payrolls->code                                  = $code;
                $commission_payrolls->code_pcc                              = $code_pcc;
                $commission_payrolls->date_ini                              = $date_ini;
                $commission_payrolls->date_end                              = $date_end;
                $commission_payrolls->type                                  = 'MASTER';
                $commission_payrolls->owner_name                            = isset($data_master->name_corporative) ? strtoupper($data_master->name_corporative) : 'N/A';

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name                = $data_master->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif              = $data_master->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number      = $data_master->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank        = $data_master->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type        = $data_master->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm            = $data_master->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name                = $data_master->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif              = $data_master->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number      = $data_master->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank        = $data_master->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type        = $data_master->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle               = $data_master->extra_beneficiary_zelle;

                $commission_payrolls->owner_code                            = $final_array['master'][$index]['owner_code'];
                $commission_payrolls->code_agency                           = $final_array['master'][$index]['code_agency'];
                $commission_payrolls->amount_commission_master_agency       = $final_array['master'][$index]['commission_master'];
                $commission_payrolls->amount_commission_master_agency_usd   = $final_array['master'][$index]['commission_agency_master_usd'];
                $commission_payrolls->amount_commission_master_agency_ves   = $final_array['master'][$index]['commission_agency_master_ves'];
                $commission_payrolls->created_by                            = Auth::user()->name;
                $commission_payrolls->total_commission                      = $final_array['master'][$index]['commission_master'];
                $commission_payrolls->save();
            }

            /** 2.- Creamos el asiento para las agencias generales */
            for ($index = 0; $index < count($final_array['general']); $index++) {

                $data_general = Agency::where('code', $final_array['general'][$index]['code_agency'])->where('owner_code', $final_array['general'][$index]['owner_code'])->first();

                $commission_payrolls = new CommissionPayroll();
                $commission_payrolls->code                                  = $code;
                $commission_payrolls->code_pcc                              = $code_pcc;
                $commission_payrolls->date_ini                              = $date_ini;
                $commission_payrolls->date_end                              = $date_end;
                $commission_payrolls->type                                  = 'GENERAL';
                $commission_payrolls->owner_name                            = isset($data_general->name_corporative) ? strtoupper($data_general->name_corporative) : 'N/A';

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name                = $data_general->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif              = $data_general->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number      = $data_general->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank        = $data_general->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type        = $data_general->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm            = $data_general->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name                = $data_general->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif              = $data_general->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number      = $data_general->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank        = $data_general->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type        = $data_general->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle               = $data_general->extra_beneficiary_zelle;

                $commission_payrolls->owner_code                            = $final_array['general'][$index]['owner_code'];
                $commission_payrolls->code_agency                           = $final_array['general'][$index]['code_agency'];
                $commission_payrolls->amount_commission_general_agency      = $final_array['general'][$index]['total_commission_general'];
                $commission_payrolls->amount_commission_general_agency_usd  = $final_array['general'][$index]['commission_agency_general_usd'];
                $commission_payrolls->amount_commission_general_agency_ves  = $final_array['general'][$index]['commission_agency_general_ves'];
                $commission_payrolls->created_by                            = Auth::user()->name;
                $commission_payrolls->total_commission                      = $final_array['general'][$index]['total_commission_general'];
                $commission_payrolls->save();
            }

            /** 3.- Creamos el asiento para las agentes */
            for ($index = 0; $index < count($final_array['agent']); $index++) {

                $data_agent = Agent::where('id', $final_array['agent'][$index]['agent_id'])->first();

                $commission_payrolls = new CommissionPayroll();
                $commission_payrolls->code                           = $code;
                $commission_payrolls->code_pcc                       = $code_pcc;
                $commission_payrolls->date_ini                       = $date_ini;
                $commission_payrolls->date_end                       = $date_end;
                $commission_payrolls->type                           = 'AGENTE';
                $commission_payrolls->owner_name                     = $data_agent->name == null ? 'N/A' : strtoupper($data_agent->name);

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name            = $data_agent->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif          = $data_agent->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number  = $data_agent->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank    = $data_agent->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type    = $data_agent->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm        = $data_agent->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name            = $data_agent->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif          = $data_agent->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number  = $data_agent->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank    = $data_agent->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type    = $data_agent->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle           = $data_agent->extra_beneficiary_zelle;

                $commission_payrolls->owner_code                     = $final_array['agent'][$index]['owner_code'];
                $commission_payrolls->code_agency                    = $final_array['agent'][$index]['code_agency'];
                $commission_payrolls->agent_id                       = $final_array['agent'][$index]['agent_id'];
                $commission_payrolls->amount_commission_agent        = $final_array['agent'][$index]['commission_agent'];
                $commission_payrolls->amount_commission_agent_usd    = $final_array['agent'][$index]['commission_agent_usd'];
                $commission_payrolls->amount_commission_agent_ves    = $final_array['agent'][$index]['commission_agent_ves'];
                $commission_payrolls->created_by                     = Auth::user()->name;
                $commission_payrolls->total_commission               = $final_array['agent'][$index]['commission_agent'];
                $commission_payrolls->save();
            }

            return true;
            
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
            return false;
            //throw $th;
        }
    }
}