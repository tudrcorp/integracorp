<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Commission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class SaleController extends Controller
{
    public static function preCalculateCommission($records, $desde, $hasta)
    {
        try {
            // dd($records);
            $data = $records->toArray();
            // dd($data, $desde, $hasta);

            $code = 'TDEC-PCC-' . date('mY') . '-' . rand(11111, 99999);

            for ($i = 0; $i < count($data); $i++) {

                $commission = new Commission();
                /**Datos principales de la tabla commission */
                $commission->code                   = $code;
                $commission->date_payment_affiliate = $data[$i]['date'];
                $commission->sale_id                = $data[$i]['id'];
                $commission->plan_id                = $data[$i]['plan_id'];
                $commission->coverage_id            = $data[$i]['coverage_id'] ?? null;
                $commission->agent_id               = $data[$i]['agent_id'] ?? null;
                $commission->agency_id              = $data[$i]['agency_id'] ?? null;
                $commission->code_agency            = $data[$i]['code_agency'] ?? null;
                $commission->owner_code             = $data[$i]['owner_code'] ?? null;
                $commission->amount                 = $data[$i]['total_amount'];
                $commission->payment_frequency      = $data[$i]['payment_frequency'];
                $commission->payment_method         = $data[$i]['payment_method'];
                $commission->invoice_number         = $data[$i]['invoice_number'];
                $commission->affiliate_full_name    = $data[$i]['affiliate_full_name'];
                $commission->pay_amount_usd         = $data[$i]['pay_amount_usd'];
                $commission->pay_amount_ves         = $data[$i]['pay_amount_ves'];
                $commission->payment_method_usd     = $data[$i]['payment_method_usd'];
                $commission->payment_method_ves     = $data[$i]['payment_method_ves'];
                $commission->date_ini               = $desde;
                $commission->date_end               = $hasta;


                /** 1-. Preguntamos si el pago esta a nombre de un agente */
                if ($data[$i]['agent'] != null) {

                    $commission->commission_agent       = ($data[$i]['total_amount'] * $data[$i]['agent']['commission_tdec']) / 100; //Calculo de la comision del agente
                    $commission->commission_agent_tdec  = $data[$i]['agent']['commission_tdec'];
                    
                    /**Calculo de la comision segun el tipo de pago */
                    $commission->commission_agent_usd       = $data[$i]['pay_amount_usd'] > 0 ? ($data[$i]['pay_amount_usd'] * $data[$i]['agent']['commission_tdec']) / 100 : 0;
                    $commission->commission_agent_ves       = $data[$i]['pay_amount_ves'] > 0 ? ($data[$i]['pay_amount_ves'] * $data[$i]['agent']['commission_tdec']) / 100 : 0;
                    

                    /** 2.- Pregunta a que tipo de agencia pertenece */
                    if ($data[$i]['agency']['agency_type_id'] == 1) {

                        /** Pertenece a una agencia MASTER */
                        $commission->commission_agency_master       = ($data[$i]['total_amount'] * $data[$i]['agency']['commission_tdec']) / 100; //Calculo de la comision de la agencia master
                        $commission->commission_agency_master_tdec  = $data[$i]['agency']['commission_tdec'];

                        /**Calculo de la comision segun el tipo de pago */
                        $commission->commission_agency_master_usd       = $data[$i]['pay_amount_usd'] > 0 ? ($data[$i]['pay_amount_usd'] * $data[$i]['agency']['commission_tdec']) / 100 : 0;
                        $commission->commission_agency_master_ves       = $data[$i]['pay_amount_ves'] > 0 ? ($data[$i]['pay_amount_ves'] * $data[$i]['agency']['commission_tdec']) / 100 : 0;
                    }

                    if ($data[$i]['agency']['agency_type_id'] == 3) {

                        /** Pertenece a una agencia GENERAL */
                        $commission->commission_agency_general  = ($data[$i]['total_amount'] * $data[$i]['agency']['commission_tdec']) / 100; //Calculo de la comision de la agencia general
                        $commission->commission_agency_master   = ($data[$i]['total_amount'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec) / 100; //Calculo de la comision de la agencia general

                        $commission->commission_agency_general_tdec = $data[$i]['agency']['commission_tdec'];
                        $commission->commission_agency_master_tdec  = DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec;
                        
                        /**Calculo de la comision segun el tipo de pago */
                        $commission->commission_agency_general_usd  = $data[$i]['pay_amount_usd'] > 0 ? ($data[$i]['pay_amount_usd'] * $data[$i]['agency']['commission_tdec']) / 100 : 0.00;
                        $commission->commission_agency_general_ves  = $data[$i]['pay_amount_ves'] > 0 ? ($data[$i]['pay_amount_ves'] * $data[$i]['agency']['commission_tdec']) / 100 : 0.00;

                        $commission->commission_agency_master_usd   = $data[$i]['pay_amount_usd'] > 0 ? $data[$i]['pay_amount_usd'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec / 100 : 0.00;
                        $commission->commission_agency_master_ves   = $data[$i]['pay_amount_ves'] > 0 ? $data[$i]['pay_amount_ves'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec / 100 : 0.00;
                        
                    }
                }

                /** 3-. Preguntamos si el pago esta a nombre de un agencia */
                if ($data[$i]['agent'] == null) {

                    /** 4.- Pregunta a que tipo de agencia pertenece */
                    if ($data[$i]['agency']['agency_type_id'] == 1) {

                        /** Pertenece a una agencia MASTER */
                        $commission->commission_agency_master       = ($data[$i]['total_amount'] * $data[$i]['agency']['commission_tdec']) / 100; //Calculo de la comision de la agencia master
                        $commission->commission_agency_master_tdec  = $data[$i]['agency']['commission_tdec'];

                        /**Calculo de la comision segun el tipo de pago */
                        $commission->commission_agency_master_usd       = $data[$i]['pay_amount_usd'] > 0 ? ($data[$i]['pay_amount_usd'] * $data[$i]['agency']['commission_tdec']) / 100 : 0;
                        $commission->commission_agency_master_ves       = $data[$i]['pay_amount_ves'] > 0 ? ($data[$i]['pay_amount_ves'] * $data[$i]['agency']['commission_tdec']) / 100 : 0;
                    }

                    if ($data[$i]['agency']['agency_type_id'] == 3) {

                        /** Pertenece a una agencia GENERAL */
                        $commission->commission_agency_general  = ($data[$i]['total_amount'] * $data[$i]['agency']['commission_tdec']) / 100; //Calculo de la comision de la agencia general
                        $commission->commission_agency_master   = ($data[$i]['total_amount'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec) / 100; //Calculo de la comision de la agencia general

                        $commission->commission_agency_general_tdec = $data[$i]['agency']['commission_tdec'];
                        $commission->commission_agency_master_tdec  = DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec;

                        /**Calculo de la comision segun el tipo de pago */
                        $commission->commission_agency_general_usd  = $data[$i]['pay_amount_usd'] > 0 ? ($data[$i]['pay_amount_usd'] * $data[$i]['agency']['commission_tdec']) / 100 : 0.00;
                        $commission->commission_agency_general_ves  = $data[$i]['pay_amount_ves'] > 0 ? ($data[$i]['pay_amount_ves'] * $data[$i]['agency']['commission_tdec']) / 100 : 0.00;

                        $commission->commission_agency_master_usd   = $data[$i]['pay_amount_usd'] > 0 ? $data[$i]['pay_amount_usd'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec / 100 : 0.00;
                        $commission->commission_agency_master_ves   = $data[$i]['pay_amount_ves'] > 0 ? $data[$i]['pay_amount_ves'] * DB::table('agencies')->select('commission_tdec')->where('code', $data[$i]['code_agency'])->where('owner_code', $data[$i]['owner_code'])->first()->commission_tdec / 100 : 0.00;
                    }
                }

                $commission->total_payment_commission   = $commission->commission_agent + $commission->commission_agency_master + $commission->commission_agency_general;
                $commission->date_payment_commission    = Carbon::now()->format('d/m/Y');
                $commission->created_by                 = Auth::user()->name;
                $commission->save();
            }

            /**4-. Actualizo el estatus de mi venta */
            foreach ($records as $record) {
                $record->status_payment_commission = 'COMISION PAGADA';
                $record->save();
            }

            return true;
            
        } catch (\Throwable $th) {
            Log::error($th->getMessage());
            Notification::make()
                ->title('EXCEPCION')
                ->body($th->getMessage())
                ->icon('heroicon-s-x-circle')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}