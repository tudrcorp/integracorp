<?php

namespace App\Http\Controllers;

use App\Models\Fee;
use App\Models\AgeRange;
use Illuminate\Http\Request;
use App\Models\CorporateQuote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Notifications\Notification;
use App\Models\CorporateQuoteRequestData;
use App\Models\DetailsCorporateQuoteRequest;

class UtilsController extends Controller
{
    public static function createCorporateQuote($livewire, $data)
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

            $corporate_quote = CorporateQuote::where('corporate_quote_request_id', $livewire->ownerRecord->id)->first();
            // dd($corporate_quote->plan);

            //Cambiamos el estatus de la solicitud a aprobada
            // $corporate_quote_request = CorporateQuoteRequest::find($livewire->id);
            // $corporate_quote_request->status = 'APROBADA';
            // $corporate_quote_request->save();

            // dd($corporate_quote);

            /**
             * Array para el detalle de la solicutud
             * Con ente array obtenemos los planes asociados a la solicitud
             * 
             * En este paso tambien actualizamos la solicitud de cotizacion
             */
            $details = CorporateQuoteRequest::find($livewire->ownerRecord->id);
            $details_plan = $details->details->toArray();

            //Poblacion
            $poblacion = CorporateQuoteRequestData::where('corporate_quote_request_id', $livewire->ownerRecord->id)->get()->toArray();

            $array = [];

            for ($i = 0; $i < count($details_plan); $i++) {
                //Rabgo de edades segun el plan
                $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_plan[$i]['plan_id'])->orderBy('range')->get();
                foreach ($poblacion as $persona) {
                    $edad = (int) $persona['age'];
                    foreach ($rangos as $rango) {
                        if ($edad >= $rango->age_init && $edad <= $rango->age_end) {
                            array_push($array, [
                                'id' => $persona['id'],
                                'age' => $persona['age'],
                                'plan_id' => $details_plan[$i]['plan_id'],
                                'age_range_id' => $rango->id,
                                'range' => $rango->range,
                            ]);
                            break;
                        }
                    }
                }
            }

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

            DetailCorporateQuote::where('corporate_quote_request_id', $livewire->ownerRecord->id)->delete();
                
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

    public static function createCorporateQuoteWithoutPersons($livewire, $data)
    {
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
}