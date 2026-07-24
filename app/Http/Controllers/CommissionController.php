<?php

namespace App\Http\Controllers;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\CommissionPayroll;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CommissionController extends Controller
{
    public static function calculateCommissionSubAgente($agent_id, $record)
    {
        try {
            $subAgent = Agent::query()->findOrFail($agent_id);
            $commissionTdecSubAgent = (float) $subAgent->commission_tdec;

            $agentSuperior = Agent::query()->findOrFail($subAgent->owner_agent);
            $commissionTdecAgentSuperior = (float) $agentSuperior->commission_tdec;

            $agencySuperior = Agency::query()
                ->where('code', $agentSuperior->owner_code)
                ->firstOrFail();

            $agencyMasterCommissionTdec = null;
            if ((int) $agencySuperior->agency_type_id === 3 && $agencySuperior->owner_code !== 'TDG-100') {
                $agencyMasterCommissionTdec = (float) Agency::query()
                    ->where('code', $agencySuperior->owner_code)
                    ->firstOrFail()
                    ->commission_tdec;
            }

            return self::buildSubAgentCommissionsFromTotalAmount(
                totalAmount: (float) $record->total_amount,
                commissionTdecSubAgent: $commissionTdecSubAgent,
                commissionTdecAgentSuperior: $commissionTdecAgentSuperior,
                agencySuperiorCommissionTdec: (float) $agencySuperior->commission_tdec,
                agencySuperiorTypeId: (int) $agencySuperior->agency_type_id,
                agencySuperiorOwnerCode: (string) $agencySuperior->owner_code,
                agencyMasterCommissionTdec: $agencyMasterCommissionTdec,
            );
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    /**
     * Calcula la jerarquía dinámica de comisiones de sub-agente aplicando
     * cada porcentaje únicamente sobre total_amount.
     *
     * @return array{
     *     porcentaje_sub_agente_usd: float,
     *     porcentaje_sub_agente_ves: float,
     *     porcentaje_agente_superior_usd: float,
     *     porcentaje_agente_superior_ves: float,
     *     porcentaje_agencia_general_usd: float,
     *     porcentaje_agencia_general_ves: float,
     *     porcentaje_agencia_master_usd: float,
     *     porcentaje_agencia_master_ves: float,
     *     money: string,
     *     porcent_sub_agente: float,
     *     porcent_agente_superior: float,
     *     porcent_agencia_general: float,
     *     porcent_agencia_master: float,
     *     total_amount: float
     * }
     */
    public static function buildSubAgentCommissionsFromTotalAmount(
        float $totalAmount,
        float $commissionTdecSubAgent,
        float $commissionTdecAgentSuperior,
        float $agencySuperiorCommissionTdec,
        int $agencySuperiorTypeId,
        string $agencySuperiorOwnerCode,
        ?float $agencyMasterCommissionTdec = null,
    ): array {
        // Jerarquía dinámica: cada nivel cobra la diferencia vs el nivel inferior
        $porcentSubAgente = $commissionTdecSubAgent;
        $porcentAgenteSuperior = $commissionTdecAgentSuperior - $commissionTdecSubAgent;
        $porcentAgenciaGeneral = 0.0;
        $porcentAgenciaMaster = 0.0;

        if ($agencySuperiorTypeId === 3) {
            $porcentAgenciaGeneral = $agencySuperiorCommissionTdec - $commissionTdecAgentSuperior;

            if ($agencySuperiorOwnerCode !== 'TDG-100' && $agencyMasterCommissionTdec !== null) {
                $porcentAgenciaMaster = $agencyMasterCommissionTdec - $agencySuperiorCommissionTdec;
            }
        }

        if ($agencySuperiorTypeId === 1 && $agencySuperiorOwnerCode !== 'TDG-100') {
            $porcentAgenciaMaster = $agencySuperiorCommissionTdec - $commissionTdecAgentSuperior;
        }

        return [
            'porcentaje_sub_agente_usd' => $totalAmount * $porcentSubAgente / 100,
            'porcentaje_sub_agente_ves' => 0.0,
            'porcentaje_agente_superior_usd' => $totalAmount * $porcentAgenteSuperior / 100,
            'porcentaje_agente_superior_ves' => 0.0,
            'porcentaje_agencia_general_usd' => $totalAmount * $porcentAgenciaGeneral / 100,
            'porcentaje_agencia_general_ves' => 0.0,
            'porcentaje_agencia_master_usd' => $totalAmount * $porcentAgenciaMaster / 100,
            'porcentaje_agencia_master_ves' => 0.0,
            'money' => 'usd',
            'porcent_sub_agente' => $porcentSubAgente,
            'porcent_agente_superior' => $porcentAgenteSuperior,
            'porcent_agencia_general' => $porcentAgenciaGeneral,
            'porcent_agencia_master' => $porcentAgenciaMaster,
            'total_amount' => $totalAmount,
        ];
    }

    public static function calculateCommissionAgente($agent_id, $record)
    {
        try {
            $commissionTdecAgent = (float) Agent::query()->findOrFail($agent_id)->commission_tdec;

            return self::buildAgentCommissionFromTotalAmount(
                totalAmount: (float) $record->total_amount,
                commissionTdecAgent: $commissionTdecAgent,
            );
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * @return array{porcentaje_agente: float, money: string, porcent_agent: float, total_amount: float}
     */
    public static function buildAgentCommissionFromTotalAmount(
        float $totalAmount,
        float $commissionTdecAgent,
    ): array {
        return [
            'porcentaje_agente' => $totalAmount * $commissionTdecAgent / 100,
            'money' => 'usd',
            'porcent_agent' => $commissionTdecAgent,
            'total_amount' => $totalAmount,
        ];
    }

    public static function calculateCommissionGeneral($code, $record, $porcentaje_agente)
    {
        try {
            $dataAgencyGeneral = Agency::query()->where('code', $code)->firstOrFail();

            $agencyMasterCommissionTdec = null;
            if ($dataAgencyGeneral->owner_code !== 'TDG-100') {
                $agencyMasterCommissionTdec = (float) Agency::query()
                    ->where('code', $dataAgencyGeneral->owner_code)
                    ->firstOrFail()
                    ->commission_tdec;
            }

            return self::buildGeneralAgencyCommissionsFromTotalAmount(
                totalAmount: (float) $record->total_amount,
                agentCommissionPercent: (float) $porcentaje_agente,
                agencyGeneralCommissionTdec: (float) $dataAgencyGeneral->commission_tdec,
                agencyGeneralOwnerCode: (string) $dataAgencyGeneral->owner_code,
                agencyMasterCommissionTdec: $agencyMasterCommissionTdec,
            );
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * @return array{
     *     porcentaje_agencia_general: float,
     *     porcentaje_agencia_master: float,
     *     money: string,
     *     porcent_gral: float,
     *     porcent_master: float,
     *     total_amount: float
     * }
     */
    public static function buildGeneralAgencyCommissionsFromTotalAmount(
        float $totalAmount,
        float $agentCommissionPercent,
        float $agencyGeneralCommissionTdec,
        string $agencyGeneralOwnerCode,
        ?float $agencyMasterCommissionTdec = null,
    ): array {
        // General cobra la diferencia vs el agente; master (si aplica) vs la general
        $porcentGral = $agencyGeneralCommissionTdec - $agentCommissionPercent;
        $porcentMaster = 0.0;

        if ($agencyGeneralOwnerCode !== 'TDG-100' && $agencyMasterCommissionTdec !== null) {
            $porcentMaster = $agencyMasterCommissionTdec - $agencyGeneralCommissionTdec;
        }

        return [
            'porcentaje_agencia_general' => abs($totalAmount * $porcentGral / 100),
            'porcentaje_agencia_master' => abs($totalAmount * $porcentMaster / 100),
            'money' => 'usd',
            'porcent_gral' => abs($porcentGral),
            'porcent_master' => abs($porcentMaster),
            'total_amount' => $totalAmount,
        ];
    }

    public static function calculateCommissionMaster($code, $record, $porcentaje_agente)
    {
        try {
            $commissionTdecAgencyMaster = (float) Agency::query()
                ->where('code', $code)
                ->firstOrFail()
                ->commission_tdec;

            return self::buildMasterAgencyCommissionFromTotalAmount(
                totalAmount: (float) $record->total_amount,
                agentCommissionPercent: (float) $porcentaje_agente,
                agencyMasterCommissionTdec: $commissionTdecAgencyMaster,
            );
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
        }
    }

    /**
     * @return array{porcentaje_agencia_master: float, money: string, porcent_master: float, total_amount: float}
     */
    public static function buildMasterAgencyCommissionFromTotalAmount(
        float $totalAmount,
        float $agentCommissionPercent,
        float $agencyMasterCommissionTdec,
    ): array {
        // Master cobra la diferencia vs el agente (o 0 si la venta es directa de la master)
        $porcentMaster = $agencyMasterCommissionTdec - $agentCommissionPercent;

        return [
            'porcentaje_agencia_master' => abs($totalAmount * $porcentMaster / 100),
            'money' => 'usd',
            'porcent_master' => abs($porcentMaster),
            'total_amount' => $totalAmount,
        ];
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
                ->filter(fn ($item) => ! is_null($item['owner_code']) && $item['owner_code'] == 'TDG-100') // opcional: ignorar agentes nulos
                ->groupBy('owner_code')
                ->map(function (Collection $group, $owner_code) {
                    return [
                        'owner_code' => $owner_code,
                        'code_agency' => $group->first()['code_agency'],
                        'total_commission_master' => $group->sum('commission_master'),
                        'commission_master' => (float) $group->sum(fn ($item) => (float) $item['commission_agency_master']),
                        'commission_agency_master_usd' => (float) $group->sum(fn ($item) => (float) $item['commission_agency_master_usd']),
                        'commission_agency_master_ves' => (float) $group->sum(fn ($item) => (float) $item['commission_agency_master_ves']),
                    ];
                })
                ->values() // Reindexa numéricamente
                ->toArray();

            $general = collect($dataArray)
                ->map(function ($item) {
                    // Filtramos solo los que cumplen la condición: owner_code != code_agency
                    if ($item['owner_code'] !== $item['code_agency'] && $item['owner_code'] !== 'TDG-100') {
                        return [
                            'owner_code' => $item['owner_code'],
                            'code_agency' => $item['code_agency'],
                            'commission' => (float) $item['commission_agency_general'],
                            'commission_agency_general_usd' => (float) $item['commission_agency_general_usd'],
                            'commission_agency_general_ves' => (float) $item['commission_agency_general_ves'],
                        ];
                    }

                    // Si no cumple, retornamos null para ignorarlo después
                    return null;
                })
                ->filter() // Elimina los nulls
                ->groupBy('code_agency')
                ->map(function ($group, $codeAgency) {
                    return [
                        'owner_code' => $group->first()['owner_code'],
                        'code_agency' => $codeAgency,
                        'total_commission_general' => $group->sum('commission'),
                        'commission_agency_general_usd' => (float) $group->sum(fn ($item) => (float) $item['commission_agency_general_usd']),
                        'commission_agency_general_ves' => (float) $group->sum(fn ($item) => (float) $item['commission_agency_general_ves']),
                    ];
                })
                ->values()
                ->toArray();

            $agent = collect($dataArray)
                ->filter(fn ($item) => ! is_null($item['agent_id'])) // opcional: ignorar agentes nulos
                ->groupBy('agent_id')
                ->map(function (Collection $group, $agentId) {
                    return [
                        'owner_code' => $group->first()['owner_code'],
                        'code_agency' => $group->first()['code_agency'],
                        'agent_id' => $agentId,
                        'commission_agent' => (float) $group->sum(fn ($item) => (float) $item['commission_agent']),
                        'commission_agent_usd' => (float) $group->sum(fn ($item) => (float) $item['commission_agent_usd']),
                        'commission_agent_ves' => (float) $group->sum(fn ($item) => (float) $item['commission_agent_ves']),

                    ];
                })
                ->values() // Reindexa numéricamente
                ->toArray();

            $final_array = [
                'master' => $master,
                'general' => $general,
                'agent' => $agent,
            ];

            // dd($final_array);

            /** Creamos el asiento en la tabla de commission_payrolls */

            /**Informacion general de la tabla */
            $first_array = collect($dataArray);
            // dd(DB::table('agencies')->select('name_corporative')->where('code', 'TDG-101')->first()->name_corporative);
            $code = 'TDEC-RC-'.date('mY').'-'.rand(11111, 99999);
            $code_pcc = $first_array->first()['code'];
            $date_ini = $first_array->first()['date_ini'];
            $date_end = $first_array->first()['date_end'];

            /** 1.- Creamos el asiento para las agencias master */
            for ($index = 0; $index < count($final_array['master']); $index++) {

                $data_master = Agency::where('code', $final_array['master'][$index]['code_agency'])->where('owner_code', 'TDG-100')->first();

                $commission_payrolls = new CommissionPayroll;
                $commission_payrolls->code = $code;
                $commission_payrolls->code_pcc = $code_pcc;
                $commission_payrolls->date_ini = $date_ini;
                $commission_payrolls->date_end = $date_end;
                $commission_payrolls->type = 'MASTER';
                $commission_payrolls->owner_name = isset($data_master->name_corporative) ? strtoupper($data_master->name_corporative) : 'N/A';

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name = $data_master->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif = $data_master->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number = $data_master->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank = $data_master->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type = $data_master->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm = $data_master->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name = $data_master->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif = $data_master->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number = $data_master->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank = $data_master->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type = $data_master->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle = $data_master->extra_beneficiary_zelle;

                $commission_payrolls->owner_code = $final_array['master'][$index]['owner_code'];
                $commission_payrolls->code_agency = $final_array['master'][$index]['code_agency'];
                $commission_payrolls->amount_commission_master_agency = $final_array['master'][$index]['commission_master'];
                $commission_payrolls->amount_commission_master_agency_usd = $final_array['master'][$index]['commission_agency_master_usd'];
                $commission_payrolls->amount_commission_master_agency_ves = $final_array['master'][$index]['commission_agency_master_ves'];
                $commission_payrolls->created_by = Auth::user()->name;
                $commission_payrolls->total_commission = $final_array['master'][$index]['commission_master'];
                $commission_payrolls->save();
            }

            /** 2.- Creamos el asiento para las agencias generales */
            for ($index = 0; $index < count($final_array['general']); $index++) {

                $data_general = Agency::where('code', $final_array['general'][$index]['code_agency'])->where('owner_code', $final_array['general'][$index]['owner_code'])->first();

                $commission_payrolls = new CommissionPayroll;
                $commission_payrolls->code = $code;
                $commission_payrolls->code_pcc = $code_pcc;
                $commission_payrolls->date_ini = $date_ini;
                $commission_payrolls->date_end = $date_end;
                $commission_payrolls->type = 'GENERAL';
                $commission_payrolls->owner_name = isset($data_general->name_corporative) ? strtoupper($data_general->name_corporative) : 'N/A';

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name = $data_general->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif = $data_general->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number = $data_general->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank = $data_general->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type = $data_general->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm = $data_general->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name = $data_general->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif = $data_general->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number = $data_general->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank = $data_general->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type = $data_general->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle = $data_general->extra_beneficiary_zelle;

                $commission_payrolls->owner_code = $final_array['general'][$index]['owner_code'];
                $commission_payrolls->code_agency = $final_array['general'][$index]['code_agency'];
                $commission_payrolls->amount_commission_general_agency = $final_array['general'][$index]['total_commission_general'];
                $commission_payrolls->amount_commission_general_agency_usd = $final_array['general'][$index]['commission_agency_general_usd'];
                $commission_payrolls->amount_commission_general_agency_ves = $final_array['general'][$index]['commission_agency_general_ves'];
                $commission_payrolls->created_by = Auth::user()->name;
                $commission_payrolls->total_commission = $final_array['general'][$index]['total_commission_general'];
                $commission_payrolls->save();
            }

            /** 3.- Creamos el asiento para las agentes */
            for ($index = 0; $index < count($final_array['agent']); $index++) {

                $data_agent = Agent::where('id', $final_array['agent'][$index]['agent_id'])->first();

                $commission_payrolls = new CommissionPayroll;
                $commission_payrolls->code = $code;
                $commission_payrolls->code_pcc = $code_pcc;
                $commission_payrolls->date_ini = $date_ini;
                $commission_payrolls->date_end = $date_end;
                $commission_payrolls->type = 'AGENTE';
                $commission_payrolls->owner_name = $data_agent->name == null ? 'N/A' : strtoupper($data_agent->name);

                /**Informacion Bancaria Local */
                $commission_payrolls->local_beneficiary_name = $data_agent->local_beneficiary_name;
                $commission_payrolls->local_beneficiary_ci_rif = $data_agent->local_beneficiary_rif;
                $commission_payrolls->local_beneficiary_account_number = $data_agent->local_beneficiary_account_number;
                $commission_payrolls->local_beneficiary_account_bank = $data_agent->local_beneficiary_account_bank;
                $commission_payrolls->local_beneficiary_account_type = $data_agent->local_beneficiary_account_type;
                $commission_payrolls->local_beneficiary_phone_pm = $data_agent->local_beneficiary_phone_pm;

                /**Informacion Bancaria Extranjera */
                $commission_payrolls->extra_beneficiary_name = $data_agent->extra_beneficiary_name;
                $commission_payrolls->extra_beneficiary_ci_rif = $data_agent->extra_beneficiary_ci_rif;
                $commission_payrolls->extra_beneficiary_account_number = $data_agent->extra_beneficiary_account_number;
                $commission_payrolls->extra_beneficiary_account_bank = $data_agent->extra_beneficiary_account_bank;
                $commission_payrolls->extra_beneficiary_account_type = $data_agent->extra_beneficiary_account_type;
                $commission_payrolls->extra_beneficiary_zelle = $data_agent->extra_beneficiary_zelle;

                $commission_payrolls->owner_code = $final_array['agent'][$index]['owner_code'];
                $commission_payrolls->code_agency = $final_array['agent'][$index]['code_agency'];
                $commission_payrolls->agent_id = $final_array['agent'][$index]['agent_id'];
                $commission_payrolls->amount_commission_agent = $final_array['agent'][$index]['commission_agent'];
                $commission_payrolls->amount_commission_agent_usd = $final_array['agent'][$index]['commission_agent_usd'];
                $commission_payrolls->amount_commission_agent_ves = $final_array['agent'][$index]['commission_agent_ves'];
                $commission_payrolls->created_by = Auth::user()->name;
                $commission_payrolls->total_commission = $final_array['agent'][$index]['commission_agent'];
                $commission_payrolls->save();
            }

            return true;

        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());

            return false;
            // throw $th;
        }
    }
}
