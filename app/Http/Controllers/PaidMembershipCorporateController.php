<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Collection;
use Illuminate\Http\Request;
use App\Jobs\SendAvisoDePago;
use App\Jobs\CreateAvisoDeCobro;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;

class PaidMembershipCorporateController extends Controller
{
    public static function approvePayment($record, $data)
    {
        // dd($data, $record);
        try {

            if (!isset($data['collections'])) {

                $record->status = 'APROBADO';
                $record->save();

                /**
                 *  Notificacion
                 *  ----------------------------------------------------------------------------------------------------
                 */

                /**
                 * Actualizamos el registro en la tabla de afiliaciones
                 * cambiamos el estatus y cargamos la fecha de aceptacion
                 * ----------------------------------------------------------------------------------------------------
                 */
                if ($record->affiliation_corporate->activated_at == null) {
                    $record->affiliation_corporate->activated_at = now()->format('d-m-Y');
                    $record->affiliation_corporate->status = 'ACTIVA';
                    $record->affiliation_corporate->save();
                }

                /**
                 * Creamos el registro en la tabla de sales
                 * ----------------------------------------------------------------------------------------------------
                 */

                $sales = new Sale();
                $sales->date                    = $record->affiliation_corporate->activated_at;
                $sales->owner_code              = $record->affiliation_corporate->owner_code;
                $sales->code_agency             = $record->affiliation_corporate->code_agency;
                $sales->plan_id                 = $record->affiliation_corporate->plan_id;
                $sales->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                $sales->agent_id                = $record->affiliation_corporate->agent_id;
                $sales->invoice_number          = date('m-') . rand(11111, 99999);
                $sales->affiliation_code        = $record->affiliation_corporate->code;
                $sales->affiliate_full_name     = $record->affiliation_corporate->full_name_ti;
                $sales->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                $sales->affiliate_ci_rif        = $record->affiliation_corporate->nro_identificacion_ti;
                $sales->affiliate_phone         = $record->affiliation_corporate->phone_ti;
                $sales->affiliate_email         = $record->affiliation_corporate->email_ti;
                $sales->service                 = 'servicio';
                $sales->persons                 = $record->affiliation_corporate->corporate_members;
                $sales->total_amount            = $record->total_amount;
                $sales->type                    = 'AFILIACIÓN CORPORATIVA';
                $sales->payment_method          = $record->payment_method;
                $sales->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                $sales->created_by              = Auth::user()->name;

                $sales->pay_amount_usd          = $record->pay_amount_usd;
                $sales->pay_amount_ves          = $record->pay_amount_ves;
                $sales->payment_method_usd      = $record->payment_method_usd;
                $sales->payment_method_ves      = $record->payment_method_ves;
                $sales->bank_usd                = $record->bank_usd;
                $sales->bank_ves                = $record->bank_ves;
                $sales->type_roll               = $record->type_roll;
                $sales->payment_date            = $record->payment_date;

                $sales->save();

                /**
                 * Creamos el registro en la tabla de cobros
                 * ----------------------------------------------------------------------------------------------------
                 */
                if ($record->affiliation_corporate->payment_frequency == 'ANUAL') {
                    $collections = new Collection();
                    $collections->sale_id                 = $sales->id;
                    $collections->include_date            = $record->affiliation_corporate->activated_at;
                    $collections->owner_code              = $record->affiliation_corporate->owner_code;
                    $collections->code_agency             = $record->affiliation_corporate->code_agency;
                    $collections->plan_id                 = $record->affiliation_corporate->plan_id;
                    $collections->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                    $collections->agent_id                = $record->affiliation_corporate->agent_id;
                    $collections->collection_invoice_number     = date('m-') . rand(11111, 99999);
                    $collections->quote_number                  = $record->affiliation_corporate->corporate_quote->code;
                    $collections->affiliation_code        = $record->affiliation_corporate->code;
                    $collections->affiliate_full_name     = $record->affiliation_corporate->full_name_con;
                    $collections->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                    $collections->affiliate_ci_rif        = $record->affiliation_corporate->rif;
                    $collections->affiliate_phone         = $record->affiliation_corporate->phone_con;
                    $collections->affiliate_email         = $record->affiliation_corporate->email_con;
                    $collections->affiliate_status        = $record->affiliation_corporate->status;
                    $collections->type                    = 'AFILIACIÓN CORPORATIVA';
                    $collections->service                 = 'servicio';
                    $collections->persons                 = $record->affiliation_corporate->corporate_members;
                    $collections->total_amount            = $record->total_amount;
                    $collections->payment_method          = $record->payment_method;

                    $collections->pay_amount_usd          = 0.00;
                    $collections->pay_amount_ves          = 0.00;
                    $collections->bank_usd                = 'N/A';
                    $collections->bank_ves                = 'N/A';


                    $collections->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                    $collections->reference               = $record->reference_payment;
                    $collections->created_by              = Auth::user()->name;
                    $collections->next_payment_date       = $record->prox_payment_date;
                    $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days'));
                    $collections->created_by              = Auth::user()->name;
                    $collections->save();

                    /**Ejecutamos el Job para crea el aviso de cobro */
                    $array_data = [
                        'invoice_number'    => $collections->collection_invoice_number,
                        'emission_date'     => $record->prox_payment_date,
                        'full_name_ti'      => $sales->affiliate_full_name,
                        'ci_rif_ti'         => $sales->affiliate_ci_rif,
                        'address_ti'        => $record->affiliation_corporate->adress_con,
                        'phone_ti'          => $sales->affiliate_phone,
                        'email_ti'          => $sales->affiliate_email,
                        'total_amount'      => $record->total_amount,
                        'plan'              => $record->plan->description,
                        'coverage'          => $record->coverage->price ?? null,
                        'frequency'         => $record->affiliation_corporate->payment_frequency,
                    ];

                    dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                }

                if ($record->affiliation_corporate->payment_frequency == 'TRIMESTRAL') {
                    $trimestral = 3;
                    for ($i = 0; $i < $trimestral; $i++) {
                        /**Seleccion de fecha para calculo*/
                        $prox_date = Collection::select('id', 'include_date', 'next_payment_date')->where('affiliation_code', $record->affiliation_corporate->code)->orderBy('id', 'desc')->first();
                        if ($prox_date == null) {
                            $prox_date = $record->affiliation_corporate->activated_at;
                        } else {
                            $prox_date = $prox_date->next_payment_date;
                        }
                        $collections = new Collection();
                        $collections->sale_id                 = $sales->id;
                        $collections->include_date            = $record->affiliation_corporate->activated_at;
                        $collections->owner_code              = $record->affiliation_corporate->owner_code;
                        $collections->code_agency             = $record->affiliation_corporate->code_agency;
                        $collections->plan_id                 = $record->affiliation_corporate->plan_id;
                        $collections->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                        $collections->agent_id                = $record->affiliation_corporate->agent_id;
                        $collections->collection_invoice_number     = date('m-') . rand(11111, 99999);
                        $collections->quote_number                  = $record->affiliation_corporate->corporate_quote->code;
                        $collections->affiliation_code        = $record->affiliation_corporate->code;
                        $collections->affiliate_full_name     = $record->affiliation_corporate->full_name_con;
                        $collections->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                        $collections->affiliate_ci_rif        = $record->affiliation_corporate->rif;
                        $collections->affiliate_phone         = $record->affiliation_corporate->phone_con;
                        $collections->affiliate_email         = $record->affiliation_corporate->email_con;
                        $collections->affiliate_status        = $record->affiliation_corporate->status;
                        $collections->type                    = 'AFILIACIÓN CORPORATIVA';
                        $collections->service                 = 'servicio';
                        $collections->persons                 = $record->affiliation_corporate->corporate_members;
                        $collections->total_amount            = $record->total_amount;
                        $collections->payment_method          = $record->payment_method;

                        $collections->pay_amount_usd          = 0.00;
                        $collections->pay_amount_ves          = 0.00;
                        $collections->bank_usd                = 'N/A';
                        $collections->bank_ves                = 'N/A';


                        $collections->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                        $collections->reference               = $record->reference_payment;
                        $collections->created_by              = Auth::user()->name;
                        $collections->next_payment_date       = $record->prox_payment_date;
                        $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days'));
                        $collections->created_by              = Auth::user()->name;
                        $collections->save();

                        /**Ejecutamos el Job para crea el aviso de cobro */
                        $array_data = [
                            'invoice_number'    => $collections->collection_invoice_number,
                            'emission_date'     => $collections->next_payment_date,
                            'full_name_ti'      => $sales->affiliate_full_name,
                            'ci_rif_ti'         => $sales->affiliate_ci_rif,
                            'address_ti'        => $record->affiliation_corporate->adress_con,
                            'phone_ti'          => $sales->affiliate_phone,
                            'email_ti'          => $sales->affiliate_email,
                            'total_amount'      => $record->total_amount,
                            'plan'              => $record->plan->description,
                            'coverage'          => $record->coverage->price ?? null,
                            'frequency'         => $record->affiliation_corporate->payment_frequency,
                        ];

                        /** Ejecutamos el job */
                        dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                    }
                }

                if ($record->affiliation_corporate->payment_frequency == 'SEMESTRAL') {
                    $collections = new Collection();
                    $collections->sale_id                 = $sales->id;
                    $collections->include_date            = $record->affiliation_corporate->activated_at;
                    $collections->owner_code              = $record->affiliation_corporate->owner_code;
                    $collections->code_agency             = $record->affiliation_corporate->code_agency;
                    $collections->plan_id                 = $record->affiliation_corporate->plan_id;
                    $collections->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                    $collections->agent_id                = $record->affiliation_corporate->agent_id;
                    $collections->collection_invoice_number     = date('m-') . rand(11111, 99999);
                    $collections->quote_number                  = $record->affiliation_corporate->corporate_quote->code;
                    $collections->affiliation_code        = $record->affiliation_corporate->code;
                    $collections->affiliate_full_name     = $record->affiliation_corporate->full_name_con;
                    $collections->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                    $collections->affiliate_ci_rif        = $record->affiliation_corporate->rif;
                    $collections->affiliate_phone         = $record->affiliation_corporate->phone_con;
                    $collections->affiliate_email         = $record->affiliation_corporate->email_con;
                    $collections->affiliate_status        = $record->affiliation_corporate->status;
                    $collections->type                    = 'AFILIACIÓN CORPORATIVA';
                    $collections->service                 = 'servicio';
                    $collections->persons                 = $record->affiliation_corporate->corporate_members;
                    $collections->total_amount            = $record->total_amount;
                    $collections->payment_method          = $record->payment_method;

                    $collections->pay_amount_usd          = 0.00;
                    $collections->pay_amount_ves          = 0.00;
                    $collections->bank_usd                = 'N/A';
                    $collections->bank_ves                = 'N/A';


                    $collections->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                    $collections->reference               = $record->reference_payment;
                    $collections->created_by              = Auth::user()->name;
                    $collections->next_payment_date       = $record->prox_payment_date;
                    $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days'));
                    $collections->created_by              = Auth::user()->name;
                    $collections->save();

                    /**Ejecutamos el Job para crea el aviso de cobro */
                    $array_data = [
                        'invoice_number'    => $collections->collection_invoice_number,
                        'emission_date'     => $collections->next_payment_date,
                        'full_name_ti'      => $sales->affiliate_full_name,
                        'ci_rif_ti'         => $sales->affiliate_ci_rif,
                        'address_ti'        => $record->affiliation_corporate->adress_con,
                        'phone_ti'          => $sales->affiliate_phone,
                        'email_ti'          => $sales->affiliate_email,
                        'total_amount'      => $record->total_amount,
                        'plan'              => $record->plan->description,
                        'coverage'          => $record->coverage->price ?? null,
                        'frequency'         => $record->affiliation_corporate->payment_frequency,
                    ];

                    /** Ejecutamos el job */
                    dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                }

                if ($record->affiliation_corporate->payment_frequency == 'MENSUAL') {
                    $mensual = 11;
                    for ($i = 0; $i < $mensual; $i++) {
                        $collections = new Collection();
                        $collections->sale_id                 = $sales->id;
                        $collections->include_date            = $record->affiliation_corporate->activated_at;
                        $collections->owner_code              = $record->affiliation_corporate->owner_code;
                        $collections->code_agency             = $record->affiliation_corporate->code_agency;
                        $collections->plan_id                 = $record->affiliation_corporate->plan_id;
                        $collections->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                        $collections->agent_id                = $record->affiliation_corporate->agent_id;
                        $collections->collection_invoice_number     = date('m-') . rand(11111, 99999);
                        $collections->quote_number                  = $record->affiliation_corporate->corporate_quote->code;
                        $collections->affiliation_code        = $record->affiliation_corporate->code;
                        $collections->affiliate_full_name     = $record->affiliation_corporate->full_name_con;
                        $collections->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                        $collections->affiliate_ci_rif        = $record->affiliation_corporate->rif;
                        $collections->affiliate_phone         = $record->affiliation_corporate->phone_con;
                        $collections->affiliate_email         = $record->affiliation_corporate->email_con;
                        $collections->affiliate_status        = $record->affiliation_corporate->status;
                        $collections->type                    = 'AFILIACIÓN CORPORATIVA';
                        $collections->service                 = 'servicio';
                        $collections->persons                 = $record->affiliation_corporate->corporate_members;
                        $collections->total_amount            = $record->total_amount;
                        $collections->payment_method          = $record->payment_method;

                        $collections->pay_amount_usd          = 0.00;
                        $collections->pay_amount_ves          = 0.00;
                        $collections->bank_usd                = 'N/A';
                        $collections->bank_ves                = 'N/A';


                        $collections->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                        $collections->reference               = $record->reference_payment;
                        $collections->created_by              = Auth::user()->name;
                        $collections->next_payment_date       = $record->prox_payment_date;
                        $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days'));
                        $collections->created_by              = Auth::user()->name;
                        $collections->save();

                        /**Ejecutamos el Job para crea el aviso de cobro */
                        $array_data = [
                            'invoice_number'    => $collections->collection_invoice_number,
                            'emission_date'     => $record->prox_payment_date,
                            'full_name_ti'      => $sales->affiliate_full_name,
                            'ci_rif_ti'         => $sales->affiliate_ci_rif,
                            'address_ti'        => $record->affiliation_corporate->adress_con,
                            'phone_ti'          => $sales->affiliate_phone,
                            'email_ti'          => $sales->affiliate_email,
                            'total_amount'      => $record->total_amount,
                            'plan'              => $record->plan->description,
                            'coverage'          => $record->coverage->price ?? null,
                            'frequency'         => $record->affiliation_corporate->payment_frequency,
                        ];

                        /** Ejecutamos el job */
                        dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                    }
                }

                /**Ejecutamos el Job para enviar el reporte de pago */
                $array_data = [
                    'invoice_number'    => $sales->invoice_number,
                    'emission_date'     => $sales->date,
                    'payment_method'    => $sales->payment_method,
                    'reference'         => $record->reference_payment,
                    'full_name_ti'      => $sales->affiliate_full_name,
                    'ci_rif_ti'         => $sales->affiliate_ci_rif,
                    'address_ti'        => $record->affiliation_corporate->adress_con,
                    'phone_ti'          => $sales->affiliate_phone,
                    'email_ti'          => $sales->affiliate_email,
                    'total_amount'      => $record->total_amount,
                    'currency'          => $record->currency,
                    'plan'              => $record->plan->description,
                    'coverage'          => $record->coverage->price ?? null,
                    'frequency'         => $record->affiliation_corporate->payment_frequency,
                ];

                dispatch(new SendAvisoDePago($array_data));

                return [
                    'firstRegister' => true
                ];
            }

            if (isset($data['collections']) && count($data['collections']) > 0) {

                /**ACTUALIZO EL ESTATUS DEL COMPROBANTE */
                $record->status = 'APROBADO';
                $record->save();

                /**
                 * Creamos el registro en la tabla de sales
                 * ----------------------------------------------------------------------------------------------------
                 */

                $sales = new Sale();
                $sales->date                    = $record->affiliation_corporate->activated_at;
                $sales->owner_code              = $record->affiliation_corporate->owner_code;
                $sales->code_agency             = $record->affiliation_corporate->code_agency;
                $sales->plan_id                 = $record->affiliation_corporate->plan_id;
                $sales->coverage_id             = $record->affiliation_corporate->coverage_id ?? null;
                $sales->agent_id                = $record->affiliation_corporate->agent_id;
                $sales->invoice_number          = date('m-') . rand(11111, 99999);
                $sales->affiliation_code        = $record->affiliation_corporate->code;
                $sales->affiliate_full_name     = $record->affiliation_corporate->full_name_con;
                $sales->affiliate_contact       = $record->affiliation_corporate->full_name_con;
                $sales->affiliate_ci_rif        = $record->affiliation_corporate->rif;
                $sales->affiliate_phone         = $record->affiliation_corporate->phone_con;
                $sales->affiliate_email         = $record->affiliation_corporate->email_con;
                $sales->service                 = 'servicio';
                $sales->persons                 = $record->affiliation_corporate->corporate_members;
                $sales->total_amount            = $record->total_amount;
                $sales->type                    = 'AFILIACION CORPORATIVA';
                $sales->payment_method          = $record->payment_method;
                $sales->payment_frequency       = $record->affiliation_corporate->payment_frequency;
                $sales->created_by              = Auth::user()->name;

                $sales->pay_amount_usd          = $record->pay_amount_usd;
                $sales->pay_amount_ves          = $record->pay_amount_ves;
                $sales->payment_method_usd      = $record->payment_method_usd;
                $sales->payment_method_ves      = $record->payment_method_ves;
                $sales->bank_usd                = $record->bank_usd;
                $sales->bank_ves                = $record->bank_ves;
                $sales->type_roll               = $record->type_roll;
                $sales->payment_date            = $record->payment_date;
                $sales->save();

                /**ACTUALIZO EL ESTATUS DE LOS AVISOS DE COBROS */
                for ($i = 0; $i < count($data['collections']); $i++) {
                    $collection = Collection::find($data['collections'][$i]);
                    $collection->sale_id =  $sales->id;
                    $collection->status  = 'PAGADO';
                    $collection->save();
                }



                /**Ejecutamos el Job para enviar el reporte de pago */
                $array_data = [
                    'invoice_number'    => $sales->invoice_number,
                    'emission_date'     => $sales->date,
                    'payment_method'    => $sales->payment_method,
                    'reference'         => $record->reference_payment,
                    'full_name_ti'      => $sales->affiliate_full_name,
                    'ci_rif_ti'         => $sales->affiliate_ci_rif,
                    'address_ti'        => $record->affiliation_corporate->adress_con,
                    'phone_ti'          => $sales->affiliate_phone,
                    'email_ti'          => $sales->affiliate_email,
                    'total_amount'      => $record->total_amount,
                    'currency'          => $record->currency,
                    'plan'              => $record->plan->description,
                    'coverage'          => $record->coverage->price ?? null,
                    'frequency'         => $record->affiliation_corporate->payment_frequency,
                ];

                SendAvisoDePago::dispatch($array_data);

                return [
                    'nextRegister' => true
                ];
            }
            
        } catch (\Throwable $th) {
            dd($th);
            Notification::make()
                ->title('EXCEPCION')
                ->body($th->getMessage())
                ->warning()
                ->send();
        }
    }
}