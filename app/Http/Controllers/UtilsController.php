<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\City;
use App\Models\Plan;
use App\Models\State;
use App\Models\Region;
use App\Models\AgeRange;
use Illuminate\Http\Request;
use App\Models\CorporateQuote;
use App\Models\IndividualQuote;
use App\Models\CorporateQuoteData;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use App\Models\CorporateQuoteRequestData;
use App\Models\DetailsCorporateQuoteRequest;

class UtilsController extends Controller
{
    /**
     * Crea una nueva cotización corporativa con población
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function createCorporateQuote($livewire)
    {
        try {

            /**
             * Caso Unico
             * La los select de agencia y agente bien vasios
             * ya que el usuario no selecciono ningun agente ni agencia
             * ----------------------------------------------------------------------------------------------------
             */
            // if (isset($data['corporate_quote_request_id'])) {

            //     /**Si la cotizacion fue generada por una solicitud */
            //     $data_agent_or_agency = CorporateQuoteRequest::select('agent_id', 'code_agency', 'owner_code', 'id')
            //         ->where('id', $data['corporate_quote_request_id'])
            //         ->first();

            //     $data['owner_code']     = $data_agent_or_agency->owner_code;
            //     $data['agent_id']       = $data_agent_or_agency->owner_code != null ? $data_agent_or_agency->agent_id : null;
            //     $data['code_agency']    =  $data_agent_or_agency->code_agency;
            // } elseif ($data['code_agency'] == null) {
            //     $data['owner_code']     = 'TDG-100';
            //     $data['code_agency']    = 'TDG-100';
            //     $data['agent_id']       = null;
            // } else {
            //     $data['owner_code']     = $data['code_agency'];
            //     $data['code_agency']    = $data['code_agency'];
            //     $data['agent_id']       = $data['agent_id'];
            // }

            // $corporate_quote = new CorporateQuote($data);
            // $corporate_quote->save();

            $corporate_quote = CorporateQuote::find($livewire->ownerRecord->id);
            // dd($corporate_quote);
            // dd($corporate_quote->plan);

            //Cambiamos el estatus de la solicitud a aprobada
            // $corporate_quote_request = CorporateQuote::find($livewire->id);
            // $corporate_quote_request->status = 'APROBADA';
            // $corporate_quote_request->save();

            // dd($corporate_quote);

            /**
             * Array para el detalle de la solicutud
             * Con ente array obtenemos los planes asociados a la solicitud
             * 
             * En este paso tambien actualizamos la solicitud de cotizacion
             */
            $details_plans_corporate_quote = DetailCorporateQuote::select('plan_id')->where('corporate_quote_id', $livewire->ownerRecord->id)->groupBy('plan_id')->get()->toArray();
            // dd($details_plans_corporate_quote);

            //Poblacion
            $poblacion = CorporateQuoteData::where('corporate_quote_id', $livewire->ownerRecord->id)->get()->toArray();

            $array = [];

            for ($i = 0; $i < count($details_plans_corporate_quote); $i++) {

                //Rabgo de edades segun el plan
                $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_plans_corporate_quote[$i]['plan_id'])->orderBy('range')->get();
                // dd($rangos, $poblacion);
                foreach ($poblacion as $persona) {
                    // dd($persona['age']);
                    $edad = (int) $persona['age'];
                    foreach ($rangos as $rango) {
                        if ($edad >= $rango->age_init && $edad <= $rango->age_end) {
                            array_push($array, [
                                'id' => $persona['id'],
                                'age' => $persona['age'],
                                'plan_id' => $details_plans_corporate_quote[$i]['plan_id'],
                                'age_range_id' => $rango->id,
                                'range' => $rango->range,
                            ]);
                            break;
                        }
                    }
                }
            }
            // dd($array);
            $resultado = collect($array)
                ->groupBy('plan_id')
                ->flatMap(function ($grupoPorPlan, $planId) {
                    return $grupoPorPlan->groupBy('age_range_id')->map(fn($subgrupo, $rangoId) => [
                        'plan_id' => $planId,
                        'age_range_id' => $rangoId,
                        'total_persons' => $subgrupo->count(),
                    ])->values();
                })
                ->values()
                ->toArray();
            // dd($resultado);
            /**
             * Verificamos si tenemos mas de un plan
             * ----------------------------------------------------------------------------------------------------
             * 
             * Si tenemos mas de un plan entonces la cotización es de CM
             * Si tenemos un plan entonces la cotización es de ese plan
             */
            $total_plans = count($resultado);
            if($total_plans > 1){
                $corporate_quote->plan = 'CM';
                $corporate_quote->save();
            }
            if($total_plans == 1){
                $corporate_quote->plan = $resultado[0]['plan_id'];
                $corporate_quote->save();
            }

            DetailCorporateQuote::where('corporate_quote_id', $livewire->ownerRecord->id)->delete();
                
            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($resultado); $i++) {
                //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                $plan_ageRange = AgeRange::where('plan_id', $resultado[$i]['plan_id'])
                    ->where('id', $resultado[$i]['age_range_id'])
                    ->with('fees')
                    ->get()
                    ->toArray();

                for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                    $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

                    $detail_corporate_quote = new DetailCorporateQuote();
                    $detail_corporate_quote->corporate_quote_id    = $corporate_quote->id;
                    $detail_corporate_quote->corporate_quote_request_id    = $livewire->ownerRecord->id;
                    $detail_corporate_quote->plan_id               = $resultado[$i]['plan_id'];
                    $detail_corporate_quote->age_range_id          = $resultado[$i]['age_range_id'];
                    $detail_corporate_quote->coverage_id           = $fee->coverage_id;
                    $detail_corporate_quote->fee                   = $fee->price;
                    $detail_corporate_quote->total_persons         = $resultado[$i]['total_persons'];
                    $detail_corporate_quote->subtotal_anual        = $resultado[$i]['total_persons'] * $fee->price;
                    $detail_corporate_quote->subtotal_quarterly    = ($resultado[$i]['total_persons'] * $fee->price) / 4;
                    $detail_corporate_quote->subtotal_biannual     = ($resultado[$i]['total_persons'] * $fee->price) / 2;
                    $detail_corporate_quote->subtotal_monthly      = ($resultado[$i]['total_persons'] * $fee->price) / 12;
                    $detail_corporate_quote->status                = 'PRE-APROBADA';
                    $detail_corporate_quote->created_by            = Auth::user()->name;
                    $detail_corporate_quote->save();
                }
            }

            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             * @param $this->data [Data del formulario]
             * @param $record [Data de la cotizacion guardada en la base de dastos]
             * ----------------------------------------------------------------------------------------------------
             */

            if ($corporate_quote->plan == 1) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 1,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                $corporate_quote->sendPropuestaEconomicaPlanInicial($details);
            }

            if ($corporate_quote->plan == 2) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // dd($details_quote[0]['plan_id']);
                $details = [
                    'plan' => 2,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                $corporate_quote->sendPropuestaEconomicaPlanIdeal($details);
            }

            if ($corporate_quote->plan == 3) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 3,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                $corporate_quote->sendPropuestaEconomicaPlanEspecial($details);
            }

            /**
             * COTIZACION MULTIPLE
             * ----------------------------------------------------------------------------------------------------
             */
            if ($corporate_quote->plan == 'CM') {

                // $detalle_array_plan_incial      = [];
                // $detalle_array_plan_ideal       = [];
                // $detalle_array_plan_especial    = [];

                $group_details = [];

                for ($i = 0; $i < count($resultado); $i++) {
                    if ($resultado[$i]['plan_id'] == 1) {
                        $detalle_1 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 1)
                            ->get()
                            ->toArray();

                        $details_inicial = [
                            'plan' => 1,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_1
                        ];

                        array_push($group_details, $details_inicial);
                    }
                    if ($resultado[$i]['plan_id'] == 2) {
                        $detalle_2 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 2)
                            ->get()
                            ->toArray();

                        $details_ideal = [
                            'plan' => 2,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_2
                        ];
                        // dd($details_ideal);
                        array_push($group_details, $details_ideal);
                    }
                    if ($resultado[$i]['plan_id'] == 3) {
                        $detalle_3 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 3)
                            ->get()
                            ->toArray();

                        $details_especial = [
                            'plan' => 3,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_3
                        ];
                        // dd($details_especial);
                        array_push($group_details, $details_especial);
                    }
                }

                usort($group_details, function ($a, $b) {
                    return $a['plan'] <=> $b['plan'];
                });

                $collect_final = [];
                for ($i = 0; $i < count($group_details); $i++) {
                    if ($group_details[$i]['plan'] == 1) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] == 2) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] == 3) {
                        array_push($collect_final, $group_details[$i]);
                    }
                }
                // dd($collect_final);
                $corporate_quote->sendPropuestaEconomicaMultiple($collect_final);
            }

            //Actualizamos la solicitud de cotizacion
            // $livewire->status = 'APROBADA';
            // $livewire->save();

            return true;
            
        } catch (\Throwable $th) {
            dd($th);
            Log::error('Error al calcular edades: ' . $th->getMessage());
            return false;
        }
    }

    /**
     * Crear cotizacion corporativa sin población
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function createCorporateQuoteWithoutPersons($livewire, $data)
    {
        // dd($data, $livewire);
        try {
            
            /**
             * Caso Unico
             * los select de agencia y agente esta vasios
             * ya que el usuario no selecciono ningun agente ni agencia
             * ----------------------------------------------------------------------------------------------------
             */
            if (isset($data['corporate_quote_request_id'])) {

                /**Si la cotizacion fue generada por una solicitud */
                $data_agent_or_agency = CorporateQuoteRequest::select('agent_id', 'code_agency', 'owner_code', 'id')
                    ->where('id', $data['corporate_quote_request_id'])
                    ->first();
                $data['owner_code']     = $data_agent_or_agency->owner_code;
                $data['agent_id']       = $data_agent_or_agency->owner_code != null ? $data_agent_or_agency->agent_id : null;
                $data['code_agency']    =  $data_agent_or_agency->code_agency;
            } elseif ($data['code_agency'] == null) {
                $data['owner_code']     = 'TDG-100';
                $data['code_agency']    = 'TDG-100';
                $data['agent_id']       = null;
            } else {
                $data['owner_code']     = $data['code_agency'];
                $data['code_agency']    = $data['code_agency'];
                $data['agent_id']       = $data['agent_id'];
            }

            $corporate_quote = new CorporateQuote($data);
            $corporate_quote->save();

            $details = DetailsCorporateQuoteRequest::where('corporate_quote_request_id', $livewire->id)->get()->toArray();
            // dd($details);

            //Rango de edades
            $array = [];

            for ($i = 0; $i < count($details); $i++) {
                $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details[$i]['plan_id'])->orderBy('range')->get();
                
                $total_persons = $details[$i]['total_persons'];
                $resultado = $rangos->map(function ($rango, $index) use ($total_persons) {
                    return [
                        'plan_id' => (string) $rango->plan_id,
                        'range' => (string) $rango->range,
                        'age_range_id' => (string) $rango->id, // o $rango->age_range_id si ya existe
                        'total_persons' => $index === 0 ? $total_persons : '1',
                    ];
                })->toArray();
                $array = array_merge($array, $resultado);
            }

            $resultado = $array;
            // dd($resultado);
            /**
             * Verificamos si tenemos mas de un plan
             * ----------------------------------------------------------------------------------------------------
             * 
             * Si tenemos mas de un plan entonces la cotización es de CM
             * Si tenemos un plan entonces la cotización es de ese plan
             */
            $total_plans = count($details);
            if ($total_plans > 1) {
                $corporate_quote->plan = 'CM';
                $corporate_quote->save();
            }
            if ($total_plans == 1) {
                // dd('aqui solo un plan');
                $corporate_quote->plan = $details[0]['plan_id'];
                $corporate_quote->save();
            }

            
            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($resultado); $i++) {
                //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                $plan_ageRange = AgeRange::where('plan_id', $resultado[$i]['plan_id'])
                    ->where('id', $resultado[$i]['age_range_id'])
                    ->with('fees')
                    ->get()
                    ->toArray();
                // dd($plan_ageRange);
                for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                    $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

                    $detail_corporate_quote = new DetailCorporateQuote();
                    $detail_corporate_quote->corporate_quote_id            = $corporate_quote->id;
                    $detail_corporate_quote->corporate_quote_request_id    = $livewire->id;
                    $detail_corporate_quote->plan_id               = $resultado[$i]['plan_id'];
                    $detail_corporate_quote->age_range_id          = $resultado[$i]['age_range_id'];
                    $detail_corporate_quote->coverage_id           = $fee->coverage_id;
                    $detail_corporate_quote->fee                   = $fee->price;
                    $detail_corporate_quote->total_persons         = $resultado[$i]['total_persons'];
                    $detail_corporate_quote->subtotal_anual        = $resultado[$i]['total_persons'] * $fee->price;
                    $detail_corporate_quote->subtotal_quarterly    = ($resultado[$i]['total_persons'] * $fee->price) / 4;
                    $detail_corporate_quote->subtotal_biannual     = ($resultado[$i]['total_persons'] * $fee->price) / 2;
                    $detail_corporate_quote->subtotal_monthly      = ($resultado[$i]['total_persons'] * $fee->price) / 12;
                    $detail_corporate_quote->status                = 'PRE-APROBADA';
                    $detail_corporate_quote->created_by            = Auth::user()->name;
                    // $detail_corporate_quote->save();
                }
            }

            // dd($corporate_quote);
            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             * @param $this->data [Data del formulario]
             * @param $record [Data de la cotizacion guardada en la base de dastos]
             * ----------------------------------------------------------------------------------------------------
             */

            if ($corporate_quote->plan == 1) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 1,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];


                $corporate_quote->sendPropuestaEconomicaPlanInicial($details);
            }

            if ($corporate_quote->plan == 2) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // dd($details_quote[0]['plan_id']);
                $details = [
                    'plan' => 2,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];


                $corporate_quote->sendPropuestaEconomicaPlanIdeal($details);
            }

            if ($corporate_quote->plan == 3) {
                // dd('aqui plan 3');
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $corporate_quote->id)
                    ->get()
                    ->toArray();
                // dd($detalle, $corporate_quote->id);
                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 3,
                    'code' => $corporate_quote->code,
                    'name' => $corporate_quote->full_name,
                    'email' => $corporate_quote->email,
                    'phone' => $corporate_quote->phone,
                    'date' => $corporate_quote->created_at->format('d-m-Y'),
                    'data' => $detalle
                ];

                // dd($details);
                $corporate_quote->sendPropuestaEconomicaPlanEspecial($details);
            }

            /**
             * COTIZACION MULTIPLE
             * ----------------------------------------------------------------------------------------------------
             */
            if ($corporate_quote->plan == 'CM') {

                // $detalle_array_plan_incial      = [];
                // $detalle_array_plan_ideal       = [];
                // $detalle_array_plan_especial    = [];

                $group_details = [];

                for ($i = 0; $i < count($details); $i++) {
                    if ($details[$i]['plan_id'] == 1) {
                        $detalle_1 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 1)
                            ->get()
                            ->toArray();

                        $details_inicial = [
                            'plan' => 1,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_1
                        ];

                        array_push($group_details, $details_inicial);
                    }
                    if ($details[$i]['plan_id'] == 2) {
                        $detalle_2 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 2)
                            ->get()
                            ->toArray();

                        $details_ideal = [
                            'plan' => 2,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_2
                        ];
                        // dd($details_ideal);
                        array_push($group_details, $details_ideal);
                    }
                    if ($details[$i]['plan_id'] == 3) {
                        $detalle_3 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', 3)
                            ->get()
                            ->toArray();

                        $details_especial = [
                            'plan' => 3,
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle_3
                        ];
                        // dd($details_especial);
                        array_push($group_details, $details_especial);
                    }
                }

                usort($group_details, function ($a, $b) {
                    return $a['plan'] <=> $b['plan'];
                });

                $collect_final = [];
                for ($i = 0; $i < count($group_details); $i++) {
                    if ($group_details[$i]['plan'] == 1) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] == 2) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] == 3) {
                        array_push($collect_final, $group_details[$i]);
                    }
                }
                // dd($collect_final);
                $corporate_quote->sendPropuestaEconomicaMultiple($collect_final);
            }

            //Actualizamos la solicitud de cotizacion
            $livewire->status = 'APROBADA';
            $livewire->save();

            return true;
            
        } catch (\Throwable $th) {
            Log::error('Error al calcular edades: ' . $th->getMessage());
            return false;
        }

    }

    /**
     * Obtener los paises
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return array
     * 
     */
    public static function getCountries(): array
    {
        return [
            '+1'   => '🇺🇸 +1 (Estados Unidos)',
            '+44'  => '🇬🇧 +44 (Reino Unido)',
            '+49'  => '🇩🇪 +49 (Alemania)',
            '+33'  => '🇫🇷 +33 (Francia)',
            '+34'  => '🇪🇸 +34 (España)',
            '+39'  => '🇮🇹 +39 (Italia)',
            '+7'   => '🇷🇺 +7 (Rusia)',
            '+55'  => '🇧🇷 +55 (Brasil)',
            '+91'  => '🇮🇳 +91 (India)',
            '+86'  => '🇨🇳 +86 (China)',
            '+81'  => '🇯🇵 +81 (Japón)',
            '+82'  => '🇰🇷 +82 (Corea del Sur)',
            '+52'  => '🇲🇽 +52 (México)',
            '+58'  => '🇻🇪 +58 (Venezuela)',
            '+57'  => '🇨🇴 +57 (Colombia)',
            '+54'  => '🇦🇷 +54 (Argentina)',
            '+56'  => '🇨🇱 +56 (Chile)',
            '+51'  => '🇵🇪 +51 (Perú)',
            '+502' => '🇬🇹 +502 (Guatemala)',
            '+503' => '🇸🇻 +503 (El Salvador)',
            '+504' => '🇭🇳 +504 (Honduras)',
            '+505' => '🇳🇮 +505 (Nicaragua)',
            '+506' => '🇨🇷 +506 (Costa Rica)',
            '+507' => '🇵🇦 +507 (Panamá)',
            '+593' => '🇪🇨 +593 (Ecuador)',
            '+592' => '🇬🇾 +592 (Guyana)',
            '+591' => '🇧🇴 +591 (Bolivia)',
            '+598' => '🇺🇾 +598 (Uruguay)',
            '+20'  => '🇪🇬 +20 (Egipto)',
            '+27'  => '🇿🇦 +27 (Sudáfrica)',
            '+234' => '🇳🇬 +234 (Nigeria)',
            '+212' => '🇲🇦 +212 (Marruecos)',
            '+971' => '🇦🇪 +971 (Emiratos Árabes)',
            '+92'  => '🇵🇰 +92 (Pakistán)',
            '+880' => '🇧🇩 +880 (Bangladesh)',
            '+62'  => '🇮🇩 +62 (Indonesia)',
            '+63'  => '🇵🇭 +63 (Filipinas)',
            '+66'  => '🇹🇭 +66 (Tailandia)',
            '+60'  => '🇲🇾 +60 (Malasia)',
            '+65'  => '🇸🇬 +65 (Singapur)',
            '+61'  => '🇦🇺 +61 (Australia)',
            '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
            '+90'  => '🇹🇷 +90 (Turquía)',
            '+375' => '🇧🇾 +375 (Bielorrusia)',
            '+372' => '🇪🇪 +372 (Estonia)',
            '+371' => '🇱🇻 +371 (Letonia)',
            '+370' => '🇱🇹 +370 (Lituania)',
            '+48'  => '🇵🇱 +48 (Polonia)',
            '+40'  => '🇷🇴 +40 (Rumania)',
            '+46'  => '🇸🇪 +46 (Suecia)',
            '+47'  => '🇳🇴 +47 (Noruega)',
            '+45'  => '🇩🇰 +45 (Dinamarca)',
            '+41'  => '🇨🇭 +41 (Suiza)',
            '+43'  => '🇦🇹 +43 (Austria)',
            '+31'  => '🇳🇱 +31 (Países Bajos)',
            '+32'  => '🇧🇪 +32 (Bélgica)',
            '+353' => '🇮🇪 +353 (Irlanda)',
            '+375' => '🇧🇾 +375 (Bielorrusia)',
            '+380' => '🇺🇦 +380 (Ucrania)',
            '+994' => '🇦🇿 +994 (Azerbaiyán)',
            '+995' => '🇬🇪 +995 (Georgia)',
            '+976' => '🇲🇳 +976 (Mongolia)',
            '+998' => '🇺🇿 +998 (Uzbekistán)',
            '+84'  => '🇻🇳 +84 (Vietnam)',
            '+856' => '🇱🇦 +856 (Laos)',
            '+374' => '🇦🇲 +374 (Armenia)',
            '+965' => '🇰🇼 +965 (Kuwait)',
            '+966' => '🇸🇦 +966 (Arabia Saudita)',
            '+972' => '🇮🇱 +972 (Israel)',
            '+963' => '🇸🇾 +963 (Siria)',
            '+961' => '🇱🇧 +961 (Líbano)',
            '+960' => '🇲🇻 +960 (Maldivas)',
            '+992' => '🇹🇯 +992 (Tayikistán)',
        ];
    }


    /**
     * Obtiene los planes
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function getPlans()
    {
        return Plan::where('type', 'BASICO')->get();
    }

    /**
     * Obtiene la ciudad
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function getCity($city): int
    {
        return City::where('definition', $city)->first()->id;
    }

    /**
     * Obtiene el estado
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function getState($state): int
    {
        return State::where('definition', $state)->first()->id;
    }

    /**
     * Obtiene el Region
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function getRegion($state): string
    {
        $region_id = State::where('definition', $state)->first()->region_id;

        return Region::where('id', $region_id)->first()->definition;
    }

    /**
     * Notificacion al Administrador
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    static function notificacionToAdmin($data)
    {

        $accion = $data['action'];
        $objeto   = $data['objeto'];
        $mensaje  = $data['message'];
        $fecha    = $data['created_at'];
        
        try {

            $body = <<<HTML

            ERROR!👋

            *Accion*: {$accion}
            
            *Objeto*: {$objeto}
            
            *Mensaje*: {$mensaje}
            
            *Fecha*: {$fecha}
 
            HTML;

            $params = array(
                'token' => config('parameters.TOKEN'),
                'to' => '+584127018390',
                'body' => $body
            );
            $curl = curl_init();
            curl_setopt_array($curl, array(
                CURLOPT_URL =>  config('parameters.CURLOPT_URL'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => "",
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => "POST",
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => array(
                    "content-type: application/x-www-form-urlencoded"
                ),
            ));

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);
            
        } catch (\Throwable $th) {
            dd($th);
            Log::error($th->getMessage());
        }
    }

    /**
     * Obtiene el cliente
     * Para la cotizacion individual interactiva    
     * 
     * @author TuDrEnCasa
     * @version 1.0
     * 
     * @return void
     */
    public static function getClient($id): string
    {
        $id = Crypt::decryptString($id);
        return IndividualQuote::where('id', $id)->first()->full_name;
    }

    public static function getClientCor($id): string
    {
        $id = Crypt::decryptString($id);
        return CorporateQuote::where('id', $id)->first()->full_name;
    }

      
    
}