<?php

namespace App\Http\Controllers;

use PgSql\Lob;
use App\Models\Log;
use App\Models\Agent;
use App\Models\Agency;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\CommissionPayroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class CommissionController extends Controller
{
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