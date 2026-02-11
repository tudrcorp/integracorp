<?php

namespace App\Http\Controllers;

use App\Jobs\CreateAvisoDeCobro;
use App\Jobs\ReciboDePagoIndividual;
use App\Jobs\SendAvisoDePago;
use App\Mail\SendMailKitBienvenida;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Collection;
use App\Models\Commission;
use App\Models\Sale;
use App\Models\User;
use Carbon\Carbon;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Termwind\Components\Li;

class PaidMembershipController extends Controller
{
    public static function approvePayment($record, $data)
    {
        try {

            if($record->payment_method == 'MULTIPLE'){
                $reference_payment = $record->reference_payment_ves .'-'. $record->reference_payment_usd;
                
            }else{
                if($record->reference_payment_ves != 'N/A'){
                    $reference_payment = $record->reference_payment_ves;
                }
                if ($record->reference_payment_usd != 'N/A') {
                    $reference_payment = $record->reference_payment_usd;
                }

            }

            /**
             * LOGICA PARA LA CARGA Y GESTION DEL PRIMER PAGO O LA PRIMERA CUOTA
             * @version 2.0
             */
            if (!isset($data['collections'])) {
                /**
                 * Actualizamos el registro en la tabla de afiliaciones
                 * cambiamos el estatus y cargamos la fecha de aceptacion
                 * ----------------------------------------------------------------------------------------------------
                 */
                if ($record->affiliation->activated_at == null) {
                    $record->affiliation->activated_at = now()->format('d/m/Y');
                    $record->affiliation->effective_date = Carbon::createFromFormat('d/m/Y', now()->format('d/m/Y'))->addYear()->format('d/m/Y');
                    $record->affiliation->status = 'ACTIVA';
                    $record->affiliation->save();
                }

                /**
                 * Creamos el registro en la tabla de sales
                 * ----------------------------------------------------------------------------------------------------
                 */

                //Pregunto cual es el ultimo numero de factura
                $lastInvoiceNumber = Sale::latest()->first();

                //Registro de la venta en la tabla de sales (Ventas)
                $sales = new Sale();
                $sales->date_activation         = $record->affiliation->activated_at;
                $sales->owner_code              = $record->affiliation->owner_code;
                $sales->code_agency             = $record->affiliation->code_agency;
                $sales->plan_id                 = $record->affiliation->plan_id;
                $sales->coverage_id             = $record->affiliation->coverage_id ?? null;
                $sales->agent_id                = $record->affiliation->agent_id;
                $sales->invoice_number          = UtilsController::generateCorrelativeSale($lastInvoiceNumber->invoice_number);
                $sales->affiliation_code        = $record->affiliation->code;
                $sales->affiliate_full_name     = $record->affiliation->full_name_ti;
                $sales->affiliate_contact       = $record->affiliation->full_name_con;
                $sales->affiliate_ci_rif        = $record->affiliation->nro_identificacion_ti;
                $sales->affiliate_phone         = $record->affiliation->phone_ti;
                $sales->affiliate_email         = $record->affiliation->email_ti;
                $sales->service                 = 'servicio';
                $sales->persons                 = $record->affiliation->family_members;
                $sales->total_amount            = $record->total_amount;
                $sales->type                    = 'AFILIACION INDIVIDUAL';
                $sales->payment_method          = $record->payment_method;
                $sales->payment_frequency       = $record->affiliation->payment_frequency;
                $sales->created_by              = Auth::user()->name;
                $sales->pay_amount_usd          = $record->pay_amount_usd;
                $sales->pay_amount_ves          = $record->pay_amount_ves;
                $sales->payment_method_usd      = $record->payment_method_usd;
                $sales->payment_method_ves      = $record->payment_method_ves;
                $sales->bank_usd                = $record->bank_usd;
                $sales->bank_ves                = $record->bank_ves;
                $sales->type_roll               = $record->type_roll;
                $sales->payment_date            = $record->payment_date;
                $sales->reference_payment       = isset($reference_payment) ? $reference_payment : null;
                $sales->save();

                /**
                 * Actualizacion el registro de pago y le agregamos el nuemro de la factura generada
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->invoice_number = $sales->invoice_number;
                $record->save();

                /**
                 * Creamos el registro en la tabla de cobros
                 * ----------------------------------------------------------------------------------------------------
                 */
                if ($record->affiliation->payment_frequency == 'ANUAL') {

                    //Pregunto cual es el ultimo numero de factura
                    $lastInvoiceNumberCollection = Collection::where('id', Collection::max('id'))->get()->toArray();
                    
                    $collections = new Collection();
                    $collections->sale_id                       = $sales->id;
                    $collections->include_date                  = $record->affiliation->activated_at;
                    $collections->owner_code                    = $record->affiliation->owner_code;
                    $collections->code_agency                   = $record->affiliation->code_agency;
                    $collections->plan_id                       = $record->affiliation->plan_id;
                    $collections->coverage_id                   = $record->affiliation->coverage_id ?? null;
                    $collections->agent_id                      = $record->affiliation->agent_id;
                    $collections->collection_invoice_number     = UtilsController::generateCorrelativeCollection($lastInvoiceNumberCollection[0]['collection_invoice_number']);
                    $collections->quote_number                  = $record->affiliation->individual_quote->code;
                    $collections->affiliation_code              = $record->affiliation->code;
                    $collections->affiliate_full_name           = $record->affiliation->full_name_ti;
                    $collections->affiliate_contact             = $record->affiliation->full_name_con;
                    $collections->affiliate_ci_rif              = $record->affiliation->nro_identificacion_ti;
                    $collections->affiliate_phone               = $record->affiliation->phone_ti;
                    $collections->affiliate_email               = $record->affiliation->email_ti;
                    $collections->affiliate_status              = $record->affiliation->status;
                    $collections->type                          = 'AFILIACION INDIVIDUAL';
                    $collections->service                       = 'servicio';
                    $collections->persons                       = $record->affiliation->family_members;
                    $collections->total_amount                  = $record->total_amount;
                    $collections->payment_method                = $record->payment_method;
                    $collections->pay_amount_usd                = 0.00;
                    $collections->pay_amount_ves                = 0.00;
                    $collections->bank_usd                      = 'N/A';
                    $collections->bank_ves                      = 'N/A';
                    $collections->payment_frequency             = $record->affiliation->payment_frequency;
                    $collections->reference                     = $reference_payment;
                    $collections->created_by                    = Auth::user()->name;
                    $collections->next_payment_date             = Carbon::createFromFormat('d-m-Y', $record->prox_payment_date)->format('d/m/Y'); //Carbon::createFromFormat('d/m/Y', $prox_date)->addMonth(3)->format('d/m/Y');

                    //... -> Agregado para filtrar por fecha de vencimiento (proxima fecha de pago)
                    $collections->filter_next_payment_date      = Carbon::createFromFormat('d/m/Y', $collections->next_payment_date)->format('Y-m-d');

                    $collections->expiration_date               = date($collections->next_payment_date, strtotime('+5 days'));
                    $collections->created_by                    = Auth::user()->name;
                    $collections->save();

                    /**Ejecutamos el Job para crea el aviso de cobro */
                    $array_data = [
                        'invoice_number'    => $collections->collection_invoice_number,
                        'emission_date'     => $record->prox_payment_date,
                        'full_name_ti'      => $sales->affiliate_full_name,
                        'ci_rif_ti'         => $sales->affiliate_ci_rif,
                        'address_ti'        => $record->affiliation->adress_ti,
                        'phone_ti'          => $sales->affiliate_phone,
                        'email_ti'          => $sales->affiliate_email,
                        'total_amount'      => $record->total_amount,
                        'plan'              => $record->plan->description,
                        'coverage'          => $record->coverage->price ?? null,
                        'frequency'         => $record->affiliation->payment_frequency,
                    ];

                    dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                }

                if ($record->affiliation->payment_frequency == 'TRIMESTRAL') {
                    
                    $trimestral = 3;
                    for ($i = 0; $i < $trimestral; $i++) {
                        /**Seleccion de fecha para calculo*/
                        $prox_date = Collection::select('id', 'include_date', 'next_payment_date')->where('affiliation_code', $record->affiliation->code)->orderBy('id', 'desc')->first();
                        
                        if ($prox_date == null) {
                            $prox_date = $record->affiliation->activated_at;
                        } else {
                            $prox_date = $prox_date->next_payment_date;
                        }
                        //Pregunto cual es el ultimo numero de factura
                        $lastInvoiceNumberCollection = Collection::where('id', Collection::max('id'))->get()->toArray();

                        $collections = new Collection();
                        $collections->sale_id                 = $sales->id;
                        $collections->include_date            = $record->affiliation->activated_at;
                        $collections->owner_code              = $record->affiliation->owner_code;
                        $collections->code_agency             = $record->affiliation->code_agency;
                        $collections->plan_id                 = $record->affiliation->plan_id;
                        $collections->coverage_id             = $record->affiliation->coverage_id ?? null;
                        $collections->agent_id                = $record->affiliation->agent_id;
                        $collections->collection_invoice_number     = UtilsController::generateCorrelativeCollection($lastInvoiceNumberCollection[0]['collection_invoice_number']);
                        $collections->quote_number                  = $record->affiliation->individual_quote->code;
                        $collections->affiliation_code        = $record->affiliation->code;
                        $collections->affiliate_full_name     = $record->affiliation->full_name_ti;
                        $collections->affiliate_contact       = $record->affiliation->full_name_con;
                        $collections->affiliate_ci_rif        = $record->affiliation->nro_identificacion_ti;
                        $collections->affiliate_phone         = $record->affiliation->phone_ti;
                        $collections->affiliate_email         = $record->affiliation->email_ti;
                        $collections->affiliate_status        = $record->affiliation->status;
                        $collections->type                    = 'AFILIACION INDIVIDUAL';
                        $collections->service                 = 'servicio';
                        $collections->persons                 = $record->affiliation->family_members;
                        $collections->total_amount            = $record->total_amount;
                        $collections->payment_method          = $record->payment_method;

                        $collections->pay_amount_usd          = 0.00;
                        $collections->pay_amount_ves          = 0.00;
                        $collections->bank_usd                = 'N/A';
                        $collections->bank_ves                = 'N/A';


                        $collections->payment_frequency       = $record->affiliation->payment_frequency;
                        $collections->reference               = $record->reference_payment;
                        $collections->created_by              = Auth::user()->name;
                        $collections->next_payment_date       = Carbon::createFromFormat('d/m/Y', $prox_date)->addMonth(3)->format('d/m/Y');
                        
                        //... -> Agregado para filtrar por fecha de vencimiento (proxima fecha de pago)
                        $collections->filter_next_payment_date      = Carbon::createFromFormat('d/m/Y', $collections->next_payment_date)->format('Y-m-d');

                        $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days'));
                        $collections->created_by              = Auth::user()->name;
                        // dd($collections);
                        $collections->save();

                        /**Ejecutamos el Job para crea el aviso de cobro */
                        $array_data = [
                            'invoice_number'    => $collections->collection_invoice_number,
                            'emission_date'     => $collections->next_payment_date,
                            'full_name_ti'      => $sales->affiliate_full_name,
                            'ci_rif_ti'         => $sales->affiliate_ci_rif,
                            'address_ti'        => $record->affiliation->adress_ti,
                            'phone_ti'          => $sales->affiliate_phone,
                            'email_ti'          => $sales->affiliate_email,
                            'total_amount'      => $record->total_amount,
                            'plan'              => $record->plan->description,
                            'coverage'          => $record->coverage->price ?? null,
                            'frequency'         => $record->affiliation->payment_frequency,
                        ];

                        Log::info($array_data);

                        /** Ejecutamos el job */
                        dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                    }
                }

                if ($record->affiliation->payment_frequency == 'SEMESTRAL') {

                    //Pregunto cual es el ultimo numero de factura
                    $lastInvoiceNumberCollection = Collection::where('id', Collection::max('id'))->get()->toArray();
                    
                    $collections = new Collection();
                    $collections->sale_id                 = $sales->id;
                    $collections->include_date            = $record->affiliation->activated_at;
                    $collections->owner_code              = $record->affiliation->owner_code;
                    $collections->code_agency             = $record->affiliation->code_agency;
                    $collections->plan_id                 = $record->affiliation->plan_id;
                    $collections->coverage_id             = $record->affiliation->coverage_id ?? null;
                    $collections->agent_id                = $record->affiliation->agent_id;
                    $collections->collection_invoice_number     = UtilsController::generateCorrelativeCollection($lastInvoiceNumberCollection[0]['collection_invoice_number']);
                    $collections->quote_number                  = $record->affiliation->individual_quote->code;
                    $collections->affiliation_code        = $record->affiliation->code;
                    $collections->affiliate_full_name     = $record->affiliation->full_name_ti;
                    $collections->affiliate_contact       = $record->affiliation->full_name_con;
                    $collections->affiliate_ci_rif        = $record->affiliation->nro_identificacion_ti;
                    $collections->affiliate_phone         = $record->affiliation->phone_ti;
                    $collections->affiliate_email         = $record->affiliation->email_ti;
                    $collections->affiliate_status        = $record->affiliation->status;
                    $collections->type                    = 'AFILIACION INDIVIDUAL';
                    $collections->service                 = 'servicio';
                    $collections->persons                 = $record->affiliation->family_members;
                    $collections->total_amount            = $record->total_amount;
                    $collections->payment_method          = $record->payment_method;

                    $collections->pay_amount_usd          = 0.00;
                    $collections->pay_amount_ves          = 0.00;
                    $collections->bank_usd                = 'N/A';
                    $collections->bank_ves                = 'N/A';


                    $collections->payment_frequency       = $record->affiliation->payment_frequency;
                    $collections->reference               = $reference_payment;
                    $collections->created_by              = Auth::user()->name;
                    $collections->next_payment_date       = $record->prox_payment_date;
                    
                    //... -> Agregado para filtrar por fecha de vencimiento (proxima fecha de pago)
                    $collections->filter_next_payment_date      = Carbon::createFromFormat('d/m/Y', $collections->next_payment_date)->format('Y-m-d');
                    
                    $collections->expiration_date         = date($collections->next_payment_date, strtotime('+5 days')); //Carbon::createFromFormat('d/m/Y', $prox_date)->addMonth(3)->format('d/m/Y');
                    $collections->created_by              = Auth::user()->name;
                    $collections->save();

                    /**Ejecutamos el Job para crea el aviso de cobro */
                    $array_data = [
                        'invoice_number'    => $collections->collection_invoice_number,
                        'emission_date'     => $collections->next_payment_date,
                        'full_name_ti'      => $sales->affiliate_full_name,
                        'ci_rif_ti'         => $sales->affiliate_ci_rif,
                        'address_ti'        => $record->affiliation->adress_ti,
                        'phone_ti'          => $sales->affiliate_phone,
                        'email_ti'          => $sales->affiliate_email,
                        'total_amount'      => $record->total_amount,
                        'plan'              => $record->plan->description,
                        'coverage'          => $record->coverage->price ?? null,
                        'frequency'         => $record->affiliation->payment_frequency,
                    ];

                    /** Ejecutamos el job */
                    dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                }

                if ($record->affiliation->payment_frequency == 'MENSUAL') {

                    //Pregunto cual es el ultimo numero de factura
                    $lastInvoiceNumberCollection = Collection::latest()->first();
                    
                    $mensual = 11;
                    for ($i = 0; $i < $mensual; $i++) {
                        $collections = new Collection();
                        $collections->sale_id                 = $sales->id;
                        $collections->include_date            = $record->affiliation->activated_at;
                        $collections->owner_code              = $record->affiliation->owner_code;
                        $collections->code_agency             = $record->affiliation->code_agency;
                        $collections->plan_id                 = $record->affiliation->plan_id;
                        $collections->coverage_id             = $record->affiliation->coverage_id ?? null;
                        $collections->agent_id                = $record->affiliation->agent_id;
                        $collections->collection_invoice_number     = UtilsController::generateCorrelativeCollection($lastInvoiceNumber->invoice_number);
                        $collections->quote_number                  = $record->affiliation->individual_quote->code;
                        $collections->affiliation_code        = $record->affiliation->code;
                        $collections->affiliate_full_name     = $record->affiliation->full_name_ti;
                        $collections->affiliate_contact       = $record->affiliation->full_name_con;
                        $collections->affiliate_ci_rif        = $record->affiliation->nro_identificacion_ti;
                        $collections->affiliate_phone         = $record->affiliation->phone_ti;
                        $collections->affiliate_email         = $record->affiliation->email_ti;
                        $collections->affiliate_status        = $record->affiliation->status;
                        $collections->type                    = 'AFILIACION INDIVIDUAL';
                        $collections->service                 = 'servicio';
                        $collections->persons                 = $record->affiliation->family_members;
                        $collections->total_amount            = $record->total_amount;
                        $collections->payment_method          = $record->payment_method;

                        $collections->pay_amount_usd          = 0.00;
                        $collections->pay_amount_ves          = 0.00;
                        $collections->bank_usd                = 'N/A';
                        $collections->bank_ves                = 'N/A';


                        $collections->payment_frequency       = $record->affiliation->payment_frequency;
                        $collections->reference               = $reference_payment;
                        $collections->created_by              = Auth::user()->name;
                        $collections->next_payment_date       = $record->prox_payment_date;
                        
                        //... -> Agregado para filtrar por fecha de vencimiento (proxima fecha de pago)
                        $collections->filter_next_payment_date      = Carbon::createFromFormat('d/m/Y', $collections->next_payment_date)->format('Y-m-d');
                        
                        $collections->expiration_date         = date($collections->next_payment_date, strtotime('+30 days')); //Carbon::createFromFormat('d/m/Y', $prox_date)->addMonth(3)->format('d/m/Y');
                        $collections->created_by              = Auth::user()->name;
                        $collections->save();

                        /**Ejecutamos el Job para crea el aviso de cobro */
                        $array_data = [
                            'invoice_number'    => $collections->collection_invoice_number,
                            'emission_date'     => $record->prox_payment_date,
                            'full_name_ti'      => $sales->affiliate_full_name,
                            'ci_rif_ti'         => $sales->affiliate_ci_rif,
                            'address_ti'        => $record->affiliation->adress_ti,
                            'phone_ti'          => $sales->affiliate_phone,
                            'email_ti'          => $sales->affiliate_email,
                            'total_amount'      => $record->total_amount,
                            'plan'              => $record->plan->description,
                            'coverage'          => $record->coverage->price ?? null,
                            'frequency'         => $record->affiliation->payment_frequency,
                        ];

                        /** Ejecutamos el job */
                        dispatch(new CreateAvisoDeCobro($array_data, Auth::user()));
                    }
                }

                /**Ejecutamos el Job para enviar el reporte de pago */
                $array_data = [
                    'invoice_number'    => $sales->invoice_number,
                    'emission_date'     => now()->format('d/m/Y'),
                    'payment_method'    => $sales->payment_method,
                    'reference'         => $reference_payment,
                    'full_name_ti'      => $sales->affiliate_full_name,
                    'ci_rif_ti'         => $sales->affiliate_ci_rif,
                    'address_ti'        => $record->affiliation->adress_ti,
                    'phone_ti'          => $sales->affiliate_phone,
                    'email_ti'          => $sales->affiliate_email,
                    'total_amount'      => $record->total_amount,
                    'currency'          => $record->currency,
                    'plan'              => $record->plan->description,
                    'coverage'          => $record->coverage->price ?? null,
                    'frequency'         => $record->affiliation->payment_frequency,
                ];

                /**ACTUALIZO EL ESTATUS DEL COMPROBANTE */
                $record->status = 'APROBADO';
                $record->aproved_by = Auth::user()->name;
                $record->save();

                dispatch(new ReciboDePagoIndividual($array_data));

                /**
                 * CALCULO DE LA COMISION DIRECTA POR LA VENTA
                 * @version 2.0
                 * $var $data_afiliaciones Array con los datos de la afiliacion y con la informacion del agente
                 */
                $data_afiliaciones = $record->affiliation->toArray();

                $comisionAgent = 0;
                $comisionAgencyMaster = 0;
                $comisionAgencyGeneral = 0;


                //1.- Validamos que la venta sea hecha por un agente
                if ($data_afiliaciones['agent_id'] != null) {

                    $comisionAgent = CommissionController::calculateCommissionAgente($data_afiliaciones['agent_id'], $record);
                    Log::info('venta de agente');
                    $comision_agente = $comisionAgent['porcentaje_agente'];
                    Log::info("total a pagar: {$comisionAgent['porcentaje_agente']}");
                    Log::info("porcentaje: {$comisionAgent['porcent_agent']}");

                    //Si el codgio de la agencia es diferente a TDG-100 es directo, es decir, el agente pertenece a nosotros
                    if ($data_afiliaciones['code_agency'] != 'TDG-100') {
                        //2.- Validamos el tipo de agencia
                        $tipo_agencia = Agency::select('code', 'agency_type_id')->where('code', $data_afiliaciones['code_agency'])->first();

                        if ($tipo_agencia->agency_type_id == 3) {
                            //Agencia tipo GENERAL
                            $comisionAgencyGeneral = CommissionController::calculateCommissionGeneral($data_afiliaciones['code_agency'], $record, $comisionAgent['porcent_agent']);
                            Log::info('comisiona agencia general');
                            Log::info("total a pagar: {$comisionAgencyGeneral['porcentaje_agencia_general']}");
                            Log::info("porcentaje: {$comisionAgencyGeneral['porcent_gral']}");
                        }

                        if ($tipo_agencia->agency_type_id == 1) {
                            //Agencia tipo MASTER
                            //Calculo de la comision restando la comision del agente de la comision total de la agencia master
                            $comisionAgencyMaster = CommissionController::calculateCommissionMaster($data_afiliaciones['code_agency'], $record, $comisionAgent['porcent_agent']);
                            Log::info('comisiona agencia master');
                            Log::info("total a pagar: {$comisionAgencyMaster['porcentaje_agencia_master']}");
                            Log::info("porcentaje: {$comisionAgencyMaster['porcent_master']}");
                        }

                        // CommissionController::calculateDirectCommissionAgency($data_afiliacion, $sales);
                    }

                    //Guardamos el calculo en la tabla de comisiones
                    $commission = new Commission();
                    /**Datos principales de la tabla commission */
                    $commission->code                   = $sales->invoice_number;
                    $commission->sale_id                = $sales->id;
                    $commission->plan_id                = $record->plan_id;
                    $commission->coverage_id            = $record->coverage_id;
                    $commission->agent_id               = $record->agent_id;
                    $commission->code_agency            = $record->code_agency;
                    $commission->payment_frequency      = $record->payment_frequency;
                    $commission->affiliate_full_name    = $record->affiliation->full_name_ti;
                    $commission->pay_amount_usd         = $record->pay_amount_usd;
                    $commission->pay_amount_ves         = $record->pay_amount_ves;
                    $commission->amount                 = $record->total_amount;
                    $commission->commission_agent_usd   = isset($comisionAgent['money']) && $comisionAgent['money'] == 'usd' ? $comision_agente : 0.00;
                    $commission->commission_agent_ves   = isset($comisionAgent['money']) && $comisionAgent['money'] == 'ves' ? $comision_agente : 0.00;
                    

                    if (isset($tipo_agencia)) {
                        if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'usd') {
                            $commission->commission_agency_master_usd = $comisionAgencyMaster['porcentaje_agencia_master'];
                            $commission->porcent_agency_master   = $comisionAgencyMaster['porcent_master'];
                        }
                        if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'ves') {
                            $commission->commission_agency_master_ves = $comisionAgencyMaster['porcentaje_agencia_master'];
                            $commission->porcent_agency_master   = $comisionAgencyMaster['porcent_master'];
                        }

                        if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'usd') {
                            $commission->commission_agency_general_usd = $comisionAgencyGeneral['porcentaje_agencia_general'];
                            $commission->commission_agency_master_usd = $comisionAgencyGeneral['porcentaje_agencia_master'];
                            $commission->porcent_agency_general  = $comisionAgencyGeneral['porcent_gral'];
                            $commission->porcent_agency_master   = $comisionAgencyGeneral['porcent_master'];
                        }
                        if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'ves') {
                            $commission->commission_agency_general_ves = $comisionAgencyGeneral['porcentaje_agencia_general'];
                            $commission->commission_agency_master_ves = $comisionAgencyGeneral['porcentaje_agencia_master'];
                            $commission->porcent_agency_general  = $comisionAgencyGeneral['porcent_gral'];
                            $commission->porcent_agency_master   = $comisionAgencyGeneral['porcent_master'];
                        }
                    } else {
                        $commission->commission_agency_master_usd = 0.00;
                        $commission->commission_agency_master_ves = 0.00;
                        $commission->commission_agency_general_usd = 0.00;
                        $commission->commission_agency_general_ves = 0.00;
                    }

                    // dd($commission);
                    $commission->payment_method         = $sales->payment_method;
                    $commission->porcent_agente         = $comisionAgent['porcent_agent'];
                    $commission->affiliation_code       = $sales->affiliation_code;
                    $commission->created_by             = Auth::user()->name;
                    $commission->save();
                }

                //1.- Validamos que la venta sea hecha por una agencia general o una agencia master
                if ($data_afiliaciones['agent_id'] == null) {

                    //Si el codgio de la agencia es diferente a TDG-100 es directo, es decir, el agente pertenece a nosotros
                    if ($data_afiliaciones['code_agency'] != 'TDG-100') {
                        //2.- Validamos el tipo de agencia
                        $tipo_agencia = Agency::select('code', 'agency_type_id')->where('code', $data_afiliaciones['code_agency'])->first();

                        if ($tipo_agencia->agency_type_id == 1) {
                            //Agencia tipo MASTER
                            $comisionAgencyMaster = CommissionController::calculateCommissionMaster($data_afiliaciones['code_agency'], $record, 0);
                            Log::info(' master ');
                            Log::info($comisionAgencyMaster);
                        }

                        if ($tipo_agencia->agency_type_id == 3) {
                            //Agencia tipo GENERAL
                            $comisionAgencyGeneral = CommissionController::calculateCommissionGeneral($data_afiliaciones['code_agency'], $record, 0);
                            Log::info(' general ');
                            Log::info($comisionAgencyGeneral);
                        }

                        // CommissionController::calculateDirectCommissionAgency($data_afiliacion, $sales);
                    }

                    //Guardamos el calculo en la tabla de comisiones
                    $commission = new Commission();
                    /**Datos principales de la tabla commission */
                    $commission->code                   = $sales->invoice_number;
                    $commission->sale_id                = $sales->id;
                    $commission->plan_id                = $record->plan_id;
                    $commission->coverage_id            = $record->coverage_id;
                    $commission->agent_id               = $record->agent_id;
                    $commission->code_agency            = $record->code_agency;
                    $commission->payment_frequency      = $record->payment_frequency;
                    $commission->affiliate_full_name    = $record->affiliation->full_name_ti;
                    $commission->pay_amount_usd         = $record->pay_amount_usd;
                    $commission->pay_amount_ves         = $record->pay_amount_ves;
                    $commission->amount                 = $record->total_amount;
                    $commission->commission_agent_usd   = 0.00;
                    $commission->commission_agent_ves   = 0.00;

                    if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'usd') {
                        
                        $commission->commission_agency_master_usd   = $comisionAgencyMaster['porcentaje_agencia_master'];
                        $commission->porcent_agency_master          = $comisionAgencyMaster['porcent_master'];
                    }
                    if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'ves') {
                        
                        $commission->commission_agency_master_ves   = $comisionAgencyMaster['porcentaje_agencia_master'];
                        $commission->porcent_agency_master          = $comisionAgencyMaster['porcent_master'];
                    }

                    if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'usd') {
                        
                        $commission->commission_agency_general_usd  = $comisionAgencyGeneral['porcentaje_agencia_general'];
                        $commission->commission_agency_master_usd   = $comisionAgencyGeneral['porcentaje_agencia_master'];
                        $commission->porcent_agency_general         = $comisionAgencyGeneral['porcent_gral'];
                        $commission->porcent_agency_master          = $comisionAgencyGeneral['porcent_master'];
                    }
                    if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'ves') {
                        
                        $commission->commission_agency_general_ves  = $comisionAgencyGeneral['porcentaje_agencia_general'];
                        $commission->commission_agency_master_ves   = $comisionAgencyGeneral['porcentaje_agencia_master'];
                        $commission->porcent_agency_general         = $comisionAgencyGeneral['porcent_gral'];
                        $commission->porcent_agency_master          = $comisionAgencyGeneral['porcent_master'];
                    }

                    // dd($commission);
                    $commission->payment_method     = $sales->payment_method;
                    $commission->affiliation_code   = $sales->affiliation_code;
                    $commission->created_by         = Auth::user()->name;
                    $commission->save();
                    
                }

                //Enviamos el correo con el kit de bienvenida al agente responsable de la afiliación
                //...
                $data_tarjeta_afiliado = [
                    'name'      => $record->affiliation->full_name_ti,
                    'ci'        => $record->affiliation->nro_identificacion_ti,
                    'code'      => $record->affiliation->code,
                    'plan'      => $record->affiliation->plan->description,
                    'frecuencia'=> $record->payment_frequency,
                    'cobertura' => $record->affiliation->coverage->price ?? '',
                    'desde'     => Carbon::now()->format('d/m/Y'),
                    'hasta'     => Carbon::now()->addYear()->format('d/m/Y'),
                ];

                if($record->affiliation->plan_id == 1){
                    $condicionado = 'CondicionesINICIAL.pdf';
                }
                if($record->affiliation->plan_id == 2){
                    $condicionado = 'CondicionesIDEAL.pdf';
                }
                if($record->affiliation->plan_id == 3){
                    $condicionado = 'CondicionesESPECIAL.pdf';
                }

                //Regeneramos el certificado del afiliado para actualizar las fechas
                AffiliationController::generateCertificateIndividual($record->affiliation, $record->affiliation->affiliates, Auth::id());

                //Creamos la tarjeta del afiliado
                TarjetaAfiliacionController::generateTarjetaAfiliacion($data_tarjeta_afiliado);

                $array_correos = [
                    'agente'       => $data_afiliaciones['agent_id'] != null ? Agent::where('id', $data_afiliaciones['agent_id'])->first()->email : Agency::where('code', $data_afiliaciones['code_agency'])->first()->email,
                    'afiliaciones' => 'afiliaciones@tudrencasa.com',
                ];

                $code = [
                    'code' => $data_afiliaciones['code']
                ];

                /**Se apaga el envio del Kit por ahora */
                // Mail::to($array_correos['agente'])->cc($array_correos['afiliaciones'])->send(new SendMailKitBienvenida($code, $condicionado));

                Log::info("ENVIO COMPLETADO: Kit enviado correctamente.", [
                    'to' => $array_correos['agente'],
                    'Cc' => $array_correos['afiliaciones'],
                    'user' => $data_afiliaciones['full_name_payer'],
                ]);

                return [
                    'firstRegister' => true
                ];

            }

            //Si ya se han cargado pagos
            if (isset($data['collections']) && count($data['collections']) > 0) {

                /**
                 * Creamos el registro en la tabla de sales
                 * ----------------------------------------------------------------------------------------------------
                 */

                //Pregunto cual es el ultimo numero de factura
                $lastInvoiceNumber = Sale::latest()->first();
                // dd($lastInvoiceNumber);

                $sales = new Sale();
                $sales->date_activation         = $record->affiliation->activated_at;
                $sales->owner_code              = $record->affiliation->owner_code;
                $sales->code_agency             = $record->affiliation->code_agency;
                $sales->plan_id                 = $record->affiliation->plan_id;
                $sales->coverage_id             = $record->affiliation->coverage_id ?? null;
                $sales->agent_id                = $record->affiliation->agent_id;
                $sales->invoice_number          = UtilsController::generateCorrelativeSale($lastInvoiceNumber->invoice_number);
                $sales->affiliation_code        = $record->affiliation->code;
                $sales->affiliate_full_name     = $record->affiliation->full_name_ti;
                $sales->affiliate_contact       = $record->affiliation->full_name_con;
                $sales->affiliate_ci_rif        = $record->affiliation->nro_identificacion_ti;
                $sales->affiliate_phone         = $record->affiliation->phone_ti;
                $sales->affiliate_email         = $record->affiliation->email_ti;
                $sales->service                 = 'servicio';
                $sales->persons                 = $record->affiliation->family_members;
                $sales->total_amount            = $record->total_amount;
                $sales->type                    = 'AFILIACION INDIVIDUAL';
                $sales->payment_method          = $record->payment_method;
                $sales->payment_frequency       = $record->affiliation->payment_frequency;
                $sales->created_by              = Auth::user()->name;

                $sales->pay_amount_usd          = $record->pay_amount_usd;
                $sales->pay_amount_ves          = $record->pay_amount_ves;
                $sales->payment_method_usd      = $record->payment_method_usd;
                $sales->payment_method_ves      = $record->payment_method_ves;
                $sales->bank_usd                = $record->bank_usd;
                $sales->bank_ves                = $record->bank_ves;
                $sales->type_roll               = $record->type_roll;
                $sales->payment_date            = $record->payment_date;
                $sales->reference_payment       = isset($reference_payment) ? $reference_payment : null;
                $sales->save();

                /**
                 * Actualizacion el registro de pago y le agregamos el nuemro de la factura generada
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->invoice_number = $sales->invoice_number;
                $record->save();

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
                    'emission_date'     => now()->format('d/m/Y'),
                    'payment_method'    => $sales->payment_method,
                    'reference'         => isset($reference_payment) ? $reference_payment : null,
                    'full_name_ti'      => $sales->affiliate_full_name,
                    'ci_rif_ti'         => $sales->affiliate_ci_rif,
                    'address_ti'        => $record->affiliation->adress_ti,
                    'phone_ti'          => $sales->affiliate_phone,
                    'email_ti'          => $sales->affiliate_email,
                    'total_amount'      => $record->total_amount,
                    'currency'          => $record->currency,
                    'plan'              => $record->plan->description,
                    'coverage'          => $record->coverage->price ?? null,
                    'frequency'         => $record->affiliation->payment_frequency,
                ];

                /**ACTUALIZO EL ESTATUS DEL COMPROBANTE */
                $record->status = 'APROBADO';
                $record->aproved_by = Auth::user()->name;
                $record->save();

                ReciboDePagoIndividual::dispatch($array_data);

                /**
                 * CALCULO DE LA COMISION DIRECTA POR LA VENTA
                 */
                $data_afiliaciones = $record->affiliation->toArray();

                $comisionAgent = 0;
                $comisionAgencyMaster = 0;
                $comisionAgencyGeneral = 0;

                //1.- Validamos que la venta sea hecha por un agente
                if ($data_afiliaciones['agent_id'] != null) {

                    $comisionAgent = CommissionController::calculateCommissionAgente($data_afiliaciones['agent_id'], $record);
                    Log::info('venta de agente');
                    $comision_agente = $comisionAgent['porcentaje_agente'];
                    Log::info("total a pagar: {$comisionAgent['porcentaje_agente']}");
                    Log::info("porcentaje: {$comisionAgent['porcent_agent']}");

                    //Si el codgio de la agencia es diferente a TDG-100 es directo, es decir, el agente pertenece a nosotros
                    if ($data_afiliaciones['code_agency'] != 'TDG-100') {
                        //2.- Validamos el tipo de agencia
                        $tipo_agencia = Agency::select('code', 'agency_type_id')->where('code', $data_afiliaciones['code_agency'])->first();

                        if ($tipo_agencia->agency_type_id == 3) {
                            //Agencia tipo GENERAL
                            $comisionAgencyGeneral = CommissionController::calculateCommissionGeneral($data_afiliaciones['code_agency'], $record, $comisionAgent['porcent_agent']);
                            Log::info('comisiona agencia general');
                            Log::info("total a pagar: {$comisionAgencyGeneral['porcentaje_agencia_general']}");
                            Log::info("porcentaje: {$comisionAgencyGeneral['porcent_gral']}");
                        }

                        if ($tipo_agencia->agency_type_id == 1) {
                            //Agencia tipo MASTER
                            //Calculo de la comision restando la comision del agente de la comision total de la agencia master
                            $comisionAgencyMaster = CommissionController::calculateCommissionMaster($data_afiliaciones['code_agency'], $record, $comisionAgent['porcent_agent']);
                            Log::info('comisiona agencia master');
                            Log::info("total a pagar: {$comisionAgencyMaster['porcentaje_agencia_master']}");
                            Log::info("porcentaje: {$comisionAgencyMaster['porcent_master']}");
                        }

                        // CommissionController::calculateDirectCommissionAgency($data_afiliacion, $sales);
                    }

                    //Guardamos el calculo en la tabla de comisiones
                    $commission = new Commission();
                    /**Datos principales de la tabla commission */
                    $commission->code                   = $sales->invoice_number;
                    $commission->sale_id                = $sales->id;
                    $commission->plan_id                = $record->plan_id;
                    $commission->coverage_id            = $record->coverage_id;
                    $commission->agent_id               = $record->agent_id;
                    $commission->code_agency            = $record->code_agency;
                    $commission->payment_frequency      = $record->payment_frequency;
                    $commission->affiliate_full_name    = $record->affiliation->full_name_ti;
                    $commission->pay_amount_usd         = $record->pay_amount_usd;
                    $commission->pay_amount_ves         = $record->pay_amount_ves;
                    $commission->amount                 = $record->total_amount;
                    $commission->commission_agent_usd   = isset($comisionAgent['money']) && $comisionAgent['money'] == 'usd' ? $comision_agente : 0.00;
                    $commission->commission_agent_ves   = isset($comisionAgent['money']) && $comisionAgent['money'] == 'ves' ? $comision_agente : 0.00;


                    if (isset($tipo_agencia)) {
                        if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'usd') {
                            $commission->commission_agency_master_usd = $comisionAgencyMaster['porcentaje_agencia_master'];
                            $commission->porcent_agency_master   = $comisionAgencyMaster['porcent_master'];
                        }
                        if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'ves') {
                            $commission->commission_agency_master_ves = $comisionAgencyMaster['porcentaje_agencia_master'];
                            $commission->porcent_agency_master   = $comisionAgencyMaster['porcent_master'];
                        }

                        if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'usd') {
                            $commission->commission_agency_general_usd = $comisionAgencyGeneral['porcentaje_agencia_general'];
                            $commission->commission_agency_master_usd = $comisionAgencyGeneral['porcentaje_agencia_master'];
                            $commission->porcent_agency_general  = $comisionAgencyGeneral['porcent_gral'];
                            $commission->porcent_agency_master   = $comisionAgencyGeneral['porcent_master'];
                        }
                        if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'ves') {
                            $commission->commission_agency_general_ves = $comisionAgencyGeneral['porcentaje_agencia_general'];
                            $commission->commission_agency_master_ves = $comisionAgencyGeneral['porcentaje_agencia_master'];
                            $commission->porcent_agency_general  = $comisionAgencyGeneral['porcent_gral'];
                            $commission->porcent_agency_master   = $comisionAgencyGeneral['porcent_master'];
                        }
                    } else {
                        $commission->commission_agency_master_usd = 0.00;
                        $commission->commission_agency_master_ves = 0.00;
                        $commission->commission_agency_general_usd = 0.00;
                        $commission->commission_agency_general_ves = 0.00;
                    }

                    // dd($commission);
                    $commission->payment_method     = $sales->payment_method;
                    $commission->porcent_agente     = $comisionAgent['porcent_agent'];
                    $commission->affiliation_code   = $sales->affiliation_code;
                    $commission->created_by         = Auth::user()->name;
                    $commission->save();
                }

                //1.- Validamos que la venta sea hecha por una agencia general o una agencia master
                if ($data_afiliaciones['agent_id'] == null) {

                    //Si el codgio de la agencia es diferente a TDG-100 es directo, es decir, el agente pertenece a nosotros
                    if ($data_afiliaciones['code_agency'] != 'TDG-100') {
                        //2.- Validamos el tipo de agencia
                        $tipo_agencia = Agency::select('code', 'agency_type_id')->where('code', $data_afiliaciones['code_agency'])->first();

                        if ($tipo_agencia->agency_type_id == 1) {
                            //Agencia tipo MASTER
                            $comisionAgencyMaster = CommissionController::calculateCommissionMaster($data_afiliaciones['code_agency'], $record, 0);
                            Log::info(' master ');
                            Log::info($comisionAgencyMaster);
                        }

                        if ($tipo_agencia->agency_type_id == 3) {
                            //Agencia tipo GENERAL
                            $comisionAgencyGeneral = CommissionController::calculateCommissionGeneral($data_afiliaciones['code_agency'], $record, 0);
                            Log::info(' general ');
                            Log::info($comisionAgencyGeneral);
                        }

                        // CommissionController::calculateDirectCommissionAgency($data_afiliacion, $sales);
                    }

                    //Guardamos el calculo en la tabla de comisiones
                    $commission = new Commission();
                    /**Datos principales de la tabla commission */
                    $commission->code                   = $sales->invoice_number;
                    $commission->sale_id                = $sales->id;
                    $commission->plan_id                = $record->plan_id;
                    $commission->coverage_id            = $record->coverage_id;
                    $commission->agent_id               = $record->agent_id;
                    $commission->code_agency            = $record->code_agency;
                    $commission->payment_frequency      = $record->payment_frequency;
                    $commission->affiliate_full_name    = $record->affiliation->full_name_ti;
                    $commission->pay_amount_usd         = $record->pay_amount_usd;
                    $commission->pay_amount_ves         = $record->pay_amount_ves;
                    $commission->amount                 = $record->total_amount;
                    $commission->commission_agent_usd   = 0.00;
                    $commission->commission_agent_ves   = 0.00;

                    if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'usd') {

                        $commission->commission_agency_master_usd   = $comisionAgencyMaster['porcentaje_agencia_master'];
                        $commission->porcent_agency_master          = $comisionAgencyMaster['porcent_master'];
                    }
                    if ($tipo_agencia->agency_type_id == 1 && isset($comisionAgencyMaster['money']) && $comisionAgencyMaster['money'] == 'ves') {

                        $commission->commission_agency_master_ves   = $comisionAgencyMaster['porcentaje_agencia_master'];
                        $commission->porcent_agency_master          = $comisionAgencyMaster['porcent_master'];
                    }

                    if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'usd') {

                        $commission->commission_agency_general_usd  = $comisionAgencyGeneral['porcentaje_agencia_general'];
                        $commission->commission_agency_master_usd   = $comisionAgencyGeneral['porcentaje_agencia_master'];
                        $commission->porcent_agency_general         = $comisionAgencyGeneral['porcent_gral'];
                        $commission->porcent_agency_master          = $comisionAgencyGeneral['porcent_master'];
                    }
                    if ($tipo_agencia->agency_type_id == 3 && isset($comisionAgencyGeneral['money']) && $comisionAgencyGeneral['money'] == 'ves') {

                        $commission->commission_agency_general_ves  = $comisionAgencyGeneral['porcentaje_agencia_general'];
                        $commission->commission_agency_master_ves   = $comisionAgencyGeneral['porcentaje_agencia_master'];
                        $commission->porcent_agency_general         = $comisionAgencyGeneral['porcent_gral'];
                        $commission->porcent_agency_master          = $comisionAgencyGeneral['porcent_master'];
                    }

                    // dd($commission);
                    $commission->payment_method     = $sales->payment_method;
                    $commission->affiliation_code   = $sales->affiliation_code;
                    $commission->created_by         = Auth::user()->name;
                    $commission->save();
                }

                return [
                    'nextRegister' => true
                ];
                
            }

            
        } catch (\Throwable $th) {   
            dd($th);
            Notification::make()
                ->title('EXCEPCION')
                ->body($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile())
                ->icon('heroicon-m-tag')
                ->danger()
                ->send();
        }
    }
}