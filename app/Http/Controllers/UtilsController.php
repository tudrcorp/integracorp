<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateCertificateCorporate;
use App\Models\Agency;
use App\Models\AgeRange;
use App\Models\BusinessLine;
use App\Models\City;
use App\Models\CorporateQuote;
use App\Models\CorporateQuoteData;
use App\Models\DataNotification;
use App\Models\DetailCorporateQuote;
use App\Models\DetailIndividualQuote;
use App\Models\Fee;
use App\Models\IndividualQuote;
use App\Models\NotificationFailed;
use App\Models\Plan;
use App\Models\Region;
use App\Models\State;
use App\Models\TelemedicineCase;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UtilsController extends Controller
{
    /**
     * Obtener los paises
     *
     * @author TuDrGroup
     *
     * @version 1.0
     */
    public static function getCountries(): array
    {
        return [
            '+1' => '🇺🇸 +1 (Estados Unidos)',
            '+44' => '🇬🇧 +44 (Reino Unido)',
            '+49' => '🇩🇪 +49 (Alemania)',
            '+33' => '🇫🇷 +33 (Francia)',
            '+34' => '🇪🇸 +34 (España)',
            '+39' => '🇮🇹 +39 (Italia)',
            '+7' => '🇷🇺 +7 (Rusia)',
            '+55' => '🇧🇷 +55 (Brasil)',
            '+91' => '🇮🇳 +91 (India)',
            '+86' => '🇨🇳 +86 (China)',
            '+81' => '🇯🇵 +81 (Japón)',
            '+82' => '🇰🇷 +82 (Corea del Sur)',
            '+52' => '🇲🇽 +52 (México)',
            '+58' => '🇻🇪 +58 (Venezuela)',
            '+57' => '🇨🇴 +57 (Colombia)',
            '+54' => '🇦🇷 +54 (Argentina)',
            '+56' => '🇨🇱 +56 (Chile)',
            '+51' => '🇵🇪 +51 (Perú)',
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
            '+20' => '🇪🇬 +20 (Egipto)',
            '+27' => '🇿🇦 +27 (Sudáfrica)',
            '+234' => '🇳🇬 +234 (Nigeria)',
            '+212' => '🇲🇦 +212 (Marruecos)',
            '+971' => '🇦🇪 +971 (Emiratos Árabes)',
            '+92' => '🇵🇰 +92 (Pakistán)',
            '+880' => '🇧🇩 +880 (Bangladesh)',
            '+62' => '🇮🇩 +62 (Indonesia)',
            '+63' => '🇵🇭 +63 (Filipinas)',
            '+66' => '🇹🇭 +66 (Tailandia)',
            '+60' => '🇲🇾 +60 (Malasia)',
            '+65' => '🇸🇬 +65 (Singapur)',
            '+61' => '🇦🇺 +61 (Australia)',
            '+64' => '🇳🇿 +64 (Nueva Zelanda)',
            '+90' => '🇹🇷 +90 (Turquía)',
            '+375' => '🇧🇾 +375 (Bielorrusia)',
            '+372' => '🇪🇪 +372 (Estonia)',
            '+371' => '🇱🇻 +371 (Letonia)',
            '+370' => '🇱🇹 +370 (Lituania)',
            '+48' => '🇵🇱 +48 (Polonia)',
            '+40' => '🇷🇴 +40 (Rumania)',
            '+46' => '🇸🇪 +46 (Suecia)',
            '+47' => '🇳🇴 +47 (Noruega)',
            '+45' => '🇩🇰 +45 (Dinamarca)',
            '+41' => '🇨🇭 +41 (Suiza)',
            '+43' => '🇦🇹 +43 (Austria)',
            '+31' => '🇳🇱 +31 (Países Bajos)',
            '+32' => '🇧🇪 +32 (Bélgica)',
            '+353' => '🇮🇪 +353 (Irlanda)',
            '+375' => '🇧🇾 +375 (Bielorrusia)',
            '+380' => '🇺🇦 +380 (Ucrania)',
            '+994' => '🇦🇿 +994 (Azerbaiyán)',
            '+995' => '🇬🇪 +995 (Georgia)',
            '+976' => '🇲🇳 +976 (Mongolia)',
            '+998' => '🇺🇿 +998 (Uzbekistán)',
            '+84' => '🇻🇳 +84 (Vietnam)',
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
     * @author TuDrGroup
     *
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
     * @author TuDrGroup
     *
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
     * @author TuDrGroup
     *
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
     * @author TuDrGroup
     *
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
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function notificacionToAdmin($data)
    {

        $accion = $data['action'];
        $objeto = $data['objeto'];
        $mensaje = $data['message'];
        $fecha = $data['created_at'];
        $icon = $data['icon'];

        if ($icon == 'success') {
            $icon = '✅';
        }

        if ($icon == 'error') {
            $icon = '❌';
        }

        try {

            $body = <<<HTML

            NOTIFICACION!{$icon}

            *Accion*: {$accion}
            *Objeto*: {$objeto}
            *Fecha*: {$fecha}
            
            *Mensaje*: {$mensaje}
 
            HTML;

            $params = [
                'token' => config('parameters.TOKEN'),
                'to' => '+584127018390',
                'body' => $body,
            ];
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => config('parameters.CURLOPT_URL'),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYHOST => 0,
                CURLOPT_SSL_VERIFYPEER => 0,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($params),
                CURLOPT_HTTPHEADER => [
                    'content-type: application/x-www-form-urlencoded',
                ],
            ]);

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
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function getClient($id): string
    {
        $id = Crypt::decryptString($id);

        return IndividualQuote::where('id', $id)->first()->full_name;
    }

    /**
     * Obtiene el cliente
     * Para la cotizacion CORPORATIVA interactiva
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function getClientCor($id): string
    {
        $id = Crypt::decryptString($id);

        return CorporateQuote::where('id', $id)->first()->full_name;
    }

    /**
     * Normaliza el teléfono venezolano
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function normalizeVenezuelanPhone($phone): ?string
    {
        // Si está vacío o no es un string, devolvemos null
        if (empty($phone) || ! is_string($phone)) {
            return null;
        }

        // Eliminar todos los caracteres no numéricos
        $clean = preg_replace('/\D/', '', $phone);

        // Quitar ceros iniciales si el número es muy largo
        $clean = ltrim($clean, '0');

        // Validar que empiece por un código de área válido de Venezuela
        // Áreas comunes: 412, 414, 416, 424, 426
        if (! preg_match('/^(412|414|416|424|426)(\d{7})$/', $clean, $matches)) {
            return null; // No es un número venezolano válido
        }

        $areaCode = $matches[1];
        $number = $matches[2];

        // Formato E.164: +58 + código de área (sin cero) + número
        return '+58'.$areaCode.$number;
    }

    /**
     * Crear cotizacion corporativa General
     * El sistema genera la cotizaciones sin datos de población
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function createCorporateQuoteGeneral($id, $details)
    {
        try {

            $corporate_quote = CorporateQuote::find($id);
            // dd($corporate_quote);

            // $details = DetailCorporateQuote::where('corporate_quote_id', $id)->get()->toArray();

            // Rango de edades
            $array = [];

            for ($i = 0; $i < count($details); $i++) {
                $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details[$i]['plan_id'])->orderBy('range')->get();
                // dd($rangos);
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
                // dd($corporate_quote->plan);
                $corporate_quote->save();
            }

            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($resultado); $i++) {
                // Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                $plan_ageRange = AgeRange::where('plan_id', $resultado[$i]['plan_id'])
                    ->where('id', $resultado[$i]['age_range_id'])
                    ->with('fees')
                    ->get()
                    ->toArray();
                // dd($plan_ageRange);
                for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                    $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

                    $detail_corporate_quote = new DetailCorporateQuote;
                    $detail_corporate_quote->corporate_quote_id = $corporate_quote->id;
                    // $detail_corporate_quote->corporate_quote_request_id    = $livewire->id;
                    $detail_corporate_quote->plan_id = $resultado[$i]['plan_id'];
                    $detail_corporate_quote->age_range_id = $resultado[$i]['age_range_id'];
                    $detail_corporate_quote->coverage_id = $fee->coverage_id;
                    $detail_corporate_quote->fee = $fee->price;
                    $detail_corporate_quote->total_persons = $resultado[$i]['total_persons'];
                    $detail_corporate_quote->subtotal_anual = $resultado[$i]['total_persons'] * $fee->price;
                    $detail_corporate_quote->subtotal_quarterly = ($resultado[$i]['total_persons'] * $fee->price) / 4;
                    $detail_corporate_quote->subtotal_biannual = ($resultado[$i]['total_persons'] * $fee->price) / 2;
                    $detail_corporate_quote->subtotal_monthly = ($resultado[$i]['total_persons'] * $fee->price) / 12;
                    $detail_corporate_quote->status = 'PRE-APROBADA';
                    $detail_corporate_quote->created_by = Auth::user()->name;
                    $detail_corporate_quote->save();
                }
            }

            // dd($corporate_quote);
            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             *
             * @param  $this->data  [Data del formulario]
             * @param  $record  [Data de la cotizacion guardada en la base de dastos]
             *                 ----------------------------------------------------------------------------------------------------
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
                    'data' => $detalle,
                ];

                CorporateQuoteController::generatePdfPlanIncial($details, Auth::id());
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
                    'data' => $detalle,
                ];

                CorporateQuoteController::generatePdfPlanIdeal($details, Auth::id());
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
                    'data' => $detalle,
                ];

                // dd($details);
                CorporateQuoteController::generatePdfPlanEspecial($details, Auth::id());
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
                            'data' => $detalle_1,
                        ];

                        array_push($group_details, $details_inicial);
                    }

                    // prueba
                    if ($details[$i]['plan_id'] != 1) {
                        $detalle = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $corporate_quote->id)
                            ->where('detail_corporate_quotes.plan_id', $details[$i]['plan_id'])
                            ->get()
                            ->toArray();

                        $details = [
                            'plan' => $details[$i]['plan_id'],
                            'code' => $corporate_quote->code,
                            'name' => $corporate_quote->full_name,
                            'email' => $corporate_quote->email,
                            'phone' => $corporate_quote->phone,
                            'date' => $corporate_quote->created_at->format('d-m-Y'),
                            'data' => $detalle,
                        ];
                        // dd($details_ideal);
                        array_push($group_details, $details);
                    }

                    // if ($details[$i]['plan_id'] == 2) {
                    //     $detalle_2 = DB::table('detail_corporate_quotes')
                    //         ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    //         ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    //         ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    //         ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    //         ->where('corporate_quote_id', $corporate_quote->id)
                    //         ->where('detail_corporate_quotes.plan_id', 2)
                    //         ->get()
                    //         ->toArray();

                    //     $details_ideal = [
                    //         'plan' => 2,
                    //         'code' => $corporate_quote->code,
                    //         'name' => $corporate_quote->full_name,
                    //         'email' => $corporate_quote->email,
                    //         'phone' => $corporate_quote->phone,
                    //         'date' => $corporate_quote->created_at->format('d-m-Y'),
                    //         'data' => $detalle_2
                    //     ];
                    //     // dd($details_ideal);
                    //     array_push($group_details, $details_ideal);
                    // }
                    // if ($details[$i]['plan_id'] == 3) {
                    //     $detalle_3 = DB::table('detail_corporate_quotes')
                    //         ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    //         ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    //         ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    //         ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    //         ->where('corporate_quote_id', $corporate_quote->id)
                    //         ->where('detail_corporate_quotes.plan_id', 3)
                    //         ->get()
                    //         ->toArray();

                    //     $details_especial = [
                    //         'plan' => 3,
                    //         'code' => $corporate_quote->code,
                    //         'name' => $corporate_quote->full_name,
                    //         'email' => $corporate_quote->email,
                    //         'phone' => $corporate_quote->phone,
                    //         'date' => $corporate_quote->created_at->format('d-m-Y'),
                    //         'data' => $detalle_3
                    //     ];
                    //     // dd($details_especial);
                    //     array_push($group_details, $details_especial);
                    // }

                }

                usort($group_details, function ($a, $b) {
                    return $a['plan'] <=> $b['plan'];
                });

                $collect_final = [];
                for ($i = 0; $i < count($group_details); $i++) {
                    if ($group_details[$i]['plan'] == 1) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    if ($group_details[$i]['plan'] != 1) {
                        array_push($collect_final, $group_details[$i]);
                    }
                    // if ($group_details[$i]['plan'] == 2) {
                    //     array_push($collect_final, $group_details[$i]);
                    // }
                    // if ($group_details[$i]['plan'] == 3) {
                    //     array_push($collect_final, $group_details[$i]);
                    // }
                }

                CorporateQuoteController::generatePdfMultiple($collect_final, Auth::id());
            }

            // Actualizamos la solicitud de cotizacion
            // $livewire->status = 'APROBADA';
            // $livewire->save();

            return true;
        } catch (\Throwable $th) {
            Log::error('Error al calcular edades: '.$th->getMessage());

            return false;
        }
    }

    /**
     * Crear cotizacion corporativa Especifica
     * Este metodo se encarga de crear una cotizacion corporativa especifica
     * utilizando los rango de edades seleccionados por el agente y el plan seleccionado
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function createCorporateQuoteEspecific($record, $array_form, $array_details, $details_quote)
    {

        try {

            /**
             * Ordeno el array de detalles de cotizacion por id de plan de menor a mayor
             */
            usort($array_details, function ($a, $b) {
                return intval($a['plan_id']) <=> intval($b['plan_id']);
            });

            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($array_details); $i++) {
                // Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                if ($array_details[$i]['age_range_id'] != null && $array_details[$i]['total_persons'] != null) {
                    $plan_ageRange = AgeRange::where('plan_id', $array_details[$i]['plan_id'])
                        ->where('id', $array_details[$i]['age_range_id'])
                        ->with('fees')
                        ->get()
                        ->toArray();

                    for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                        $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();
                        $detail_individual_quote = new DetailCorporateQuote;
                        $detail_individual_quote->corporate_quote_id = $array_form['id'];
                        $detail_individual_quote->plan_id = $array_details[$i]['plan_id'];
                        $detail_individual_quote->age_range_id = $array_details[$i]['age_range_id'];
                        $detail_individual_quote->coverage_id = $fee->coverage_id;
                        $detail_individual_quote->fee = $fee->price;
                        $detail_individual_quote->total_persons = $array_details[$i]['total_persons'];
                        $detail_individual_quote->subtotal_anual = $array_details[$i]['total_persons'] * $fee->price;
                        $detail_individual_quote->subtotal_quarterly = ($array_details[$i]['total_persons'] * $fee->price) / 4;
                        $detail_individual_quote->subtotal_biannual = ($array_details[$i]['total_persons'] * $fee->price) / 2;
                        $detail_individual_quote->subtotal_monthly = ($array_details[$i]['total_persons'] * $fee->price) / 12;
                        $detail_individual_quote->status = 'PRE-APROBADA';
                        $detail_individual_quote->created_by = Auth::user()->name;
                        $detail_individual_quote->save();
                    }
                }
            }

            // elimino la variable de sesion para evitar sobrecargar
            session()->forget('details_quote');

            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             *
             * @param  $this->data  [Data del formulario]
             * @param  $record  [Data de la cotizacion guardada en la base de dastos]
             *                 ----------------------------------------------------------------------------------------------------
             */
            if ($record->plan == 1) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                    ->where('corporate_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 1,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                CorporateQuoteController::generatePdfPlanIncial($details, Auth::id());
            }

            if ($record->plan == 2) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // dd($details_quote[0]['plan_id']);
                $details = [
                    'plan' => 2,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                CorporateQuoteController::generatePdfPlanIdeal($details, Auth::id());
            }

            if ($record->plan == 3) {
                $detalle = DB::table('detail_corporate_quotes')
                    ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('corporate_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 3,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                // $record->sendPropuestaEconomicaPlanEspecial($details);
                CorporateQuoteController::generatePdfPlanEspecial($details, Auth::id());
            }

            /**
             * COTIZACION MULTIPLE
             * ----------------------------------------------------------------------------------------------------
             */
            if ($record->plan == 'CM') {

                // $detalle_array_plan_incial      = [];
                // $detalle_array_plan_ideal       = [];
                // $detalle_array_plan_especial    = [];

                $group_details = [];

                for ($i = 0; $i < count($array_details); $i++) {
                    if ($details_quote[$i]['plan_id'] == 1) {
                        $detalle_1 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                            ->where('corporate_quote_id', $record->id)
                            ->where('detail_corporate_quotes.plan_id', 1)
                            ->get()
                            ->toArray();

                        $details_inicial = [
                            'plan' => 1,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_1,
                        ];

                        array_push($group_details, $details_inicial);
                    }
                    if ($details_quote[$i]['plan_id'] == 2) {
                        $detalle_2 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $record->id)
                            ->where('detail_corporate_quotes.plan_id', 2)
                            ->get()
                            ->toArray();

                        $details_ideal = [
                            'plan' => 2,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_2,
                        ];

                        array_push($group_details, $details_ideal);
                    }
                    if ($details_quote[$i]['plan_id'] == 3) {
                        $detalle_3 = DB::table('detail_corporate_quotes')
                            ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('corporate_quote_id', $record->id)
                            ->where('detail_corporate_quotes.plan_id', 3)
                            ->get()
                            ->toArray();

                        $details_especial = [
                            'plan' => 3,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_3,
                        ];

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

                // $record->sendPropuestaEconomicaMultiple($collect_final);
                CorporateQuoteController::generatePdfMultiple($collect_final, Auth::id());
            }

        } catch (\Throwable $th) {
            dd($th);
            Log::error('Error al calcular edades: '.$th->getMessage());

            return false;
        }
    }

    public static function generateCodeAgency()
    {
        if (Agency::max('id') == null) {
            $parte_entera = 101;
        } else {
            $parte_entera = 101 + Agency::max('id');
        }

        return 'TDG-'.$parte_entera;
    }

    /**
     * Crea una nueva cotización corporativa con población
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    // public static function createCorporateQuote($livewire)
    // {
    //     try {

    //         $corporate_quote = CorporateQuote::find($livewire->ownerRecord->id);

    //         /**
    //          * Array para el detalle de la solicutud
    //          * Con ente array obtenemos los planes asociados a la solicitud
    //          *
    //          * En este paso tambien actualizamos la solicitud de cotizacion
    //          */
    //         $details_plans_corporate_quote = DetailCorporateQuote::select('plan_id')->where('corporate_quote_id', $livewire->ownerRecord->id)->groupBy('plan_id')->get()->toArray();

    //         //Poblacion
    //         $poblacion = CorporateQuoteData::where('corporate_quote_id', $livewire->ownerRecord->id)->get()->toArray();

    //         $array = [];

    //         for ($i = 0; $i < count($details_plans_corporate_quote); $i++) {

    //             //Rabgo de edades segun el plan
    //             $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_plans_corporate_quote[$i]['plan_id'])->orderBy('range')->get();
    //             // dd($rangos, $poblacion);
    //             foreach ($poblacion as $persona) {
    //                 // dd($persona['age']);
    //                 $edad = (int) $persona['age'];
    //                 foreach ($rangos as $rango) {
    //                     if ($edad >= $rango->age_init && $edad <= $rango->age_end) {
    //                         array_push($array, [
    //                             'id' => $persona['id'],
    //                             'age' => $persona['age'],
    //                             'plan_id' => $details_plans_corporate_quote[$i]['plan_id'],
    //                             'age_range_id' => $rango->id,
    //                             'range' => $rango->range,
    //                         ]);
    //                         break;
    //                     }
    //                 }
    //             }
    //         }
    //         // dd($array);
    //         $resultado = collect($array)
    //             ->groupBy('plan_id')
    //             ->flatMap(function ($grupoPorPlan, $planId) {
    //                 return $grupoPorPlan->groupBy('age_range_id')->map(fn($subgrupo, $rangoId) => [
    //                     'plan_id' => $planId,
    //                     'age_range_id' => $rangoId,
    //                     'total_persons' => $subgrupo->count(),
    //                 ])->values();
    //             })
    //             ->values()
    //             ->toArray();

    //         /**
    //          * Verificamos si tenemos mas de un plan
    //          * ----------------------------------------------------------------------------------------------------
    //          *
    //          * Si tenemos mas de un plan entonces la cotización es de CM
    //          * Si tenemos un plan entonces la cotización es de ese plan
    //          */
    //         $total_plans = count($resultado);
    //         if($total_plans > 1){
    //             $corporate_quote->plan = 'CM';
    //             $corporate_quote->save();
    //         }
    //         if($total_plans == 1){
    //             $corporate_quote->plan = $resultado[0]['plan_id'];
    //             $corporate_quote->save();
    //         }

    //         DetailCorporateQuote::where('corporate_quote_id', $livewire->ownerRecord->id)->delete();

    //         /**
    //          * For para realizar el guardado en la tabla de detalle de cotizacion
    //          * ----------------------------------------------------------------------------------------------------
    //          */
    //         for ($i = 0; $i < count($resultado); $i++) {
    //             //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
    //             $plan_ageRange = AgeRange::where('plan_id', $resultado[$i]['plan_id'])
    //                 ->where('id', $resultado[$i]['age_range_id'])
    //                 ->with('fees')
    //                 ->get()
    //                 ->toArray();

    //             for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

    //                 $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();

    //                 $detail_corporate_quote = new DetailCorporateQuote();
    //                 $detail_corporate_quote->corporate_quote_id    = $corporate_quote->id;
    //                 $detail_corporate_quote->corporate_quote_request_id    = $livewire->ownerRecord->id;
    //                 $detail_corporate_quote->plan_id               = $resultado[$i]['plan_id'];
    //                 $detail_corporate_quote->age_range_id          = $resultado[$i]['age_range_id'];
    //                 $detail_corporate_quote->coverage_id           = $fee->coverage_id;
    //                 $detail_corporate_quote->fee                   = $fee->price;
    //                 $detail_corporate_quote->total_persons         = $resultado[$i]['total_persons'];
    //                 $detail_corporate_quote->subtotal_anual        = $resultado[$i]['total_persons'] * $fee->price;
    //                 $detail_corporate_quote->subtotal_quarterly    = ($resultado[$i]['total_persons'] * $fee->price) / 4;
    //                 $detail_corporate_quote->subtotal_biannual     = ($resultado[$i]['total_persons'] * $fee->price) / 2;
    //                 $detail_corporate_quote->subtotal_monthly      = ($resultado[$i]['total_persons'] * $fee->price) / 12;
    //                 $detail_corporate_quote->status                = 'PRE-APROBADA';
    //                 $detail_corporate_quote->created_by            = Auth::user()->name;
    //                 $detail_corporate_quote->save();
    //             }
    //         }

    //         /**
    //          * LOgica para el envio de correo con los detalles de la cotizacion
    //          * @param $this->data [Data del formulario]
    //          * @param $record [Data de la cotizacion guardada en la base de dastos]
    //          * ----------------------------------------------------------------------------------------------------
    //          */

    //         if ($corporate_quote->plan == 1) {
    //             $detalle = DB::table('detail_corporate_quotes')
    //                 ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                 ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                 ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
    //                 ->where('corporate_quote_id', $corporate_quote->id)
    //                 ->get()
    //                 ->toArray();

    //             /**
    //              * Se envia el certificado del afiliado
    //              * ----------------------------------------------------------------------------------------------------
    //              */
    //             $details = [
    //                 'plan' => 1,
    //                 'code' => $corporate_quote->code,
    //                 'name' => $corporate_quote->full_name,
    //                 'email' => $corporate_quote->email,
    //                 'phone' => $corporate_quote->phone,
    //                 'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                 'data' => $detalle
    //             ];

    //             $corporate_quote->sendPropuestaEconomicaPlanInicial($details);
    //         }

    //         if ($corporate_quote->plan == 2) {
    //             $detalle = DB::table('detail_corporate_quotes')
    //                 ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                 ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                 ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
    //                 ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
    //                 ->where('corporate_quote_id', $corporate_quote->id)
    //                 ->get()
    //                 ->toArray();

    //             /**
    //              * Se envia el certificado del afiliado
    //              * ----------------------------------------------------------------------------------------------------
    //              */
    //             // dd($details_quote[0]['plan_id']);
    //             $details = [
    //                 'plan' => 2,
    //                 'code' => $corporate_quote->code,
    //                 'name' => $corporate_quote->full_name,
    //                 'email' => $corporate_quote->email,
    //                 'phone' => $corporate_quote->phone,
    //                 'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                 'data' => $detalle
    //             ];

    //             $corporate_quote->sendPropuestaEconomicaPlanIdeal($details);
    //         }

    //         if ($corporate_quote->plan == 3) {
    //             $detalle = DB::table('detail_corporate_quotes')
    //                 ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                 ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                 ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
    //                 ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
    //                 ->where('corporate_quote_id', $corporate_quote->id)
    //                 ->get()
    //                 ->toArray();

    //             /**
    //              * Se envia el certificado del afiliado
    //              * ----------------------------------------------------------------------------------------------------
    //              */
    //             $details = [
    //                 'plan' => 3,
    //                 'code' => $corporate_quote->code,
    //                 'name' => $corporate_quote->full_name,
    //                 'email' => $corporate_quote->email,
    //                 'phone' => $corporate_quote->phone,
    //                 'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                 'data' => $detalle
    //             ];

    //             $corporate_quote->sendPropuestaEconomicaPlanEspecial($details);
    //         }

    //         /**
    //          * COTIZACION MULTIPLE
    //          * ----------------------------------------------------------------------------------------------------
    //          */
    //         if ($corporate_quote->plan == 'CM') {

    //             // $detalle_array_plan_incial      = [];
    //             // $detalle_array_plan_ideal       = [];
    //             // $detalle_array_plan_especial    = [];

    //             $group_details = [];

    //             for ($i = 0; $i < count($resultado); $i++) {
    //                 if ($resultado[$i]['plan_id'] == 1) {
    //                     $detalle_1 = DB::table('detail_corporate_quotes')
    //                         ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                         ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                         ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
    //                         ->where('corporate_quote_id', $corporate_quote->id)
    //                         ->where('detail_corporate_quotes.plan_id', 1)
    //                         ->get()
    //                         ->toArray();

    //                     $details_inicial = [
    //                         'plan' => 1,
    //                         'code' => $corporate_quote->code,
    //                         'name' => $corporate_quote->full_name,
    //                         'email' => $corporate_quote->email,
    //                         'phone' => $corporate_quote->phone,
    //                         'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                         'data' => $detalle_1
    //                     ];

    //                     array_push($group_details, $details_inicial);
    //                 }
    //                 if ($resultado[$i]['plan_id'] == 2) {
    //                     $detalle_2 = DB::table('detail_corporate_quotes')
    //                         ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                         ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                         ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
    //                         ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
    //                         ->where('corporate_quote_id', $corporate_quote->id)
    //                         ->where('detail_corporate_quotes.plan_id', 2)
    //                         ->get()
    //                         ->toArray();

    //                     $details_ideal = [
    //                         'plan' => 2,
    //                         'code' => $corporate_quote->code,
    //                         'name' => $corporate_quote->full_name,
    //                         'email' => $corporate_quote->email,
    //                         'phone' => $corporate_quote->phone,
    //                         'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                         'data' => $detalle_2
    //                     ];
    //                     // dd($details_ideal);
    //                     array_push($group_details, $details_ideal);
    //                 }
    //                 if ($resultado[$i]['plan_id'] == 3) {
    //                     $detalle_3 = DB::table('detail_corporate_quotes')
    //                         ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
    //                         ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
    //                         ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
    //                         ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
    //                         ->where('corporate_quote_id', $corporate_quote->id)
    //                         ->where('detail_corporate_quotes.plan_id', 3)
    //                         ->get()
    //                         ->toArray();

    //                     $details_especial = [
    //                         'plan' => 3,
    //                         'code' => $corporate_quote->code,
    //                         'name' => $corporate_quote->full_name,
    //                         'email' => $corporate_quote->email,
    //                         'phone' => $corporate_quote->phone,
    //                         'date' => $corporate_quote->created_at->format('d-m-Y'),
    //                         'data' => $detalle_3
    //                     ];
    //                     // dd($details_especial);
    //                     array_push($group_details, $details_especial);
    //                 }
    //             }

    //             usort($group_details, function ($a, $b) {
    //                 return $a['plan'] <=> $b['plan'];
    //             });

    //             $collect_final = [];
    //             for ($i = 0; $i < count($group_details); $i++) {
    //                 if ($group_details[$i]['plan'] == 1) {
    //                     array_push($collect_final, $group_details[$i]);
    //                 }
    //                 if ($group_details[$i]['plan'] == 2) {
    //                     array_push($collect_final, $group_details[$i]);
    //                 }
    //                 if ($group_details[$i]['plan'] == 3) {
    //                     array_push($collect_final, $group_details[$i]);
    //                 }
    //             }
    //             // dd($collect_final);
    //             $corporate_quote->sendPropuestaEconomicaMultiple($collect_final);
    //         }

    //         //Actualizamos la solicitud de cotizacion
    //         // $livewire->status = 'APROBADA';
    //         // $livewire->save();

    //         return true;

    //     } catch (\Throwable $th) {
    //         dd($th);
    //         Log::error('Error al calcular edades: ' . $th->getMessage());
    //         return false;
    //     }
    // }

    /**
     * Crea el certificado parta la afiliacion corporativa
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @return void
     */
    public static function createCertificateCorporate($record, $afiliates)
    {
        // dd($record, $afiliates->toArray());
        try {

            $data = $record->toArray();

            $name_pdf = 'CER-'.$record->code.'.pdf';

            if (isset($record->agent)) {
                $name_agent = $record->agent->name;
            } else {
                $name_agent = $record->agency->name_corporative;
            }

            if (isset($record->coverage_id)) {
                $coverage = $record->coverage->price;
            } else {
                $coverage = 0;
            }

            /**
             * Agregamos la informacion al array principal que viaja a la vista del certificado
             * ----------------------------------------------------------------------------------------------------
             */
            $data['name_agent'] = $name_agent;

            GenerateCertificateCorporate::dispatch($data, $afiliates, Auth::user(), $name_pdf);
            // code...

        } catch (\Throwable $th) {
            dd($th);
        }

    }

    public static function formatMount($amount)
    {
        // Redondear a 0 decimales si termina en .00
        $formatted = number_format((float) $amount, 0, ',', '.');

        return '$'.$formatted;
    }

    /**
     * Crear cotizacion individual
     * Este metodo se encarga de crear una cotizacion individual
     * utilizando los rango de edades seleccionados por el agente y el plan seleccionado
     *
     * @author TuDrGroup
     *
     * @version 1.0
     *
     * @param  $record  [Data de la cotizacion guardada en la base de dastos]
     * @param  $array_form  [Data del formulario]
     * @param  $array_details  [Data del detalle de la cotizacion]
     * @return void
     */
    public static function storeDetailsIndividualQuote($record, $array_form, $array_details, $details_quote)
    {

        try {

            /**
             * Ordeno el array de detalles de cotización por id de plan de menor a mayor
             */
            usort($array_details, function ($a, $b) {
                return intval($a['plan_id']) <=> intval($b['plan_id']);
            });

            /**
             * For para realizar el guardado en la tabla de detalle de cotización
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($array_details); $i++) {
                // Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                if ($array_details[$i]['age_range_id'] != null && $array_details[$i]['total_persons'] != null) {
                    $plan_ageRange = AgeRange::where('plan_id', $array_details[$i]['plan_id'])
                        ->where('id', $array_details[$i]['age_range_id'])
                        ->with('fees')
                        ->get()
                        ->toArray();

                    for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                        $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();
                        $detail_individual_quote = new DetailIndividualQuote;
                        $detail_individual_quote->individual_quote_id = $array_form['id'];
                        $detail_individual_quote->plan_id = $array_details[$i]['plan_id'];
                        $detail_individual_quote->age_range_id = $array_details[$i]['age_range_id'];
                        $detail_individual_quote->coverage_id = $fee->coverage_id;
                        $detail_individual_quote->fee = $fee->price;
                        $detail_individual_quote->total_persons = $array_details[$i]['total_persons'];
                        $detail_individual_quote->subtotal_anual = $array_details[$i]['total_persons'] * $fee->price;
                        $detail_individual_quote->subtotal_quarterly = ($array_details[$i]['total_persons'] * $fee->price) / 4;
                        $detail_individual_quote->subtotal_biannual = ($array_details[$i]['total_persons'] * $fee->price) / 2;
                        $detail_individual_quote->subtotal_monthly = ($array_details[$i]['total_persons'] * $fee->price) / 12;
                        $detail_individual_quote->status = 'PRE-APROBADA';
                        $detail_individual_quote->created_by = Auth::user()->name;
                        $detail_individual_quote->save();
                    }
                }
            }

            // elimino la variable de sesion para evitar sobrecargar
            session()->forget('details_quote');

            if ($record->agent_id != null) {
                $agentName = $record->agent?->name ?? Auth::user()->name;
            } elseif ($record->code_agency != null && $record->code_agency !== 'TDG-100') {
                $agentName = Agency::where('code', $record->code_agency)->value('name_corporative') ?? Auth::user()->name;
            } else {
                $agentName = Auth::user()->name;
            }

            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             *
             * @param  $this->data  [Data del formulario]
             * @param  $record  [Data de la cotizacion guardada en la base de dastos]
             *                 ----------------------------------------------------------------------------------------------------
             */
            if ($record->plan == 1) {
                $detalle = DB::table('detail_individual_quotes')
                    ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                    ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                    ->where('individual_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 1,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'agent_name' => $agentName,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                IndividualQuoteController::generatePdfPlanIncial($details, Auth::id());
            }

            if ($record->plan == 2) {
                $detalle = DB::table('detail_individual_quotes')
                    ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('individual_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // dd($details_quote[0]['plan_id']);
                $details = [
                    'plan' => 2,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'agent_name' => $agentName,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                IndividualQuoteController::generatePdfPlanIdeal($details, Auth::id());
            }

            if ($record->plan == 3) {
                $detalle = DB::table('detail_individual_quotes')
                    ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                    ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                    ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                    ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                    ->where('individual_quote_id', $record->id)
                    ->get()
                    ->toArray();

                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $details = [
                    'plan' => 3,
                    'code' => $record->code,
                    'name' => $record->full_name,
                    'agent_name' => $agentName,
                    'email' => $record->email,
                    'phone' => $record->phone,
                    'date' => $record->created_at->format('d-m-Y'),
                    'data' => $detalle,
                ];

                IndividualQuoteController::generatePdfPlanEspecial($details, Auth::id());
            }

            /**
             * COTIZACION MULTIPLE
             * ----------------------------------------------------------------------------------------------------
             */
            if ($record->plan == 'CM') {

                // $detalle_array_plan_incial      = [];
                // $detalle_array_plan_ideal       = [];
                // $detalle_array_plan_especial    = [];

                $group_details = [];

                for ($i = 0; $i < count($array_details); $i++) {
                    if ($details_quote[$i]['plan_id'] == 1) {
                        $detalle_1 = DB::table('detail_individual_quotes')
                            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                            ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range')
                            ->where('individual_quote_id', $record->id)
                            ->where('detail_individual_quotes.plan_id', 1)
                            ->get()
                            ->toArray();

                        $details_inicial = [
                            'plan' => 1,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'agent_name' => $agentName,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_1,
                        ];

                        array_push($group_details, $details_inicial);
                    }
                    if ($details_quote[$i]['plan_id'] == 2) {
                        $detalle_2 = DB::table('detail_individual_quotes')
                            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('individual_quote_id', $record->id)
                            ->where('detail_individual_quotes.plan_id', 2)
                            ->get()
                            ->toArray();

                        $details_ideal = [
                            'plan' => 2,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'agent_name' => $agentName,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_2,
                        ];

                        array_push($group_details, $details_ideal);
                    }
                    if ($details_quote[$i]['plan_id'] == 3) {
                        $detalle_3 = DB::table('detail_individual_quotes')
                            ->join('plans', 'detail_individual_quotes.plan_id', '=', 'plans.id')
                            ->join('age_ranges', 'detail_individual_quotes.age_range_id', '=', 'age_ranges.id')
                            ->join('coverages', 'detail_individual_quotes.coverage_id', '=', 'coverages.id')
                            ->select('detail_individual_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                            ->where('individual_quote_id', $record->id)
                            ->where('detail_individual_quotes.plan_id', 3)
                            ->get()
                            ->toArray();

                        $details_especial = [
                            'plan' => 3,
                            'code' => $record->code,
                            'name' => $record->full_name,
                            'agent_name' => $agentName,
                            'email' => $record->email,
                            'phone' => $record->phone,
                            'date' => $record->created_at->format('d-m-Y'),
                            'data' => $detalle_3,
                        ];

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

                // $record->sendPropuestaEconomicaMultiple($collect_final);

                IndividualQuoteController::generatePdfMultiple($collect_final, Auth::id());
            }

            return true;

            // code...
        } catch (\Throwable $th) {
            Log::error('Error al calcular edades: '.$th->getMessage());

            return false;
        }

    }

    /**PRUEBA DE NOTIFICACION MASIVA */
    public static function send($record)
    {

        try {

            set_time_limit(0);

            $infoArray = $record->toArray();

            $array = DataNotification::where('mass_notification_id', $record->id)->get()->toArray();
            Log::info($array);

            for ($i = 0; $i < count($array); $i++) {

                if ($infoArray['header_title'] != null) {

                    $record->heading = $infoArray['header_title'].' '.$array[$i]['fullName'];
                    $body = <<<HTML
    
                    *{$record->heading}* 
    
                    {$record->content}
    
                    HTML;

                    $params = [
                        'token' => config('parameters.TOKEN'),
                        'to' => $array[$i]['phone'],
                        'image' => config('parameters.PUBLIC_URL').'/'.$infoArray['file'],
                        'caption' => $body,
                    ];
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => 'https://api.ultramsg.com/instance117518/messages/image',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => http_build_query($params),
                        CURLOPT_HTTPHEADER => [
                            'content-type: application/x-www-form-urlencoded',
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                } else {

                    $body = <<<HTML
    
                    {$record->content}
    
                    HTML;

                    $params = [
                        'token' => 'yuvh9eq5kn8bt666',
                        'to' => $array[$i],
                        'image' => 'https://tudrgroup.com/images/01K535GEERHFKCBC108PTWCCG4.png',
                        'caption' => $body,
                    ];
                    $curl = curl_init();
                    curl_setopt_array($curl, [
                        CURLOPT_URL => 'https://api.ultramsg.com/instance117518/messages/image',
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_ENCODING => '',
                        CURLOPT_MAXREDIRS => 10,
                        CURLOPT_TIMEOUT => 30,
                        CURLOPT_SSL_VERIFYHOST => 0,
                        CURLOPT_SSL_VERIFYPEER => 0,
                        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                        CURLOPT_CUSTOMREQUEST => 'POST',
                        CURLOPT_POSTFIELDS => http_build_query($params),
                        CURLOPT_HTTPHEADER => [
                            'content-type: application/x-www-form-urlencoded',
                        ],
                    ]);

                    $response = curl_exec($curl);
                    $err = curl_error($curl);

                    Log::info($response);
                    Log::error($err);

                    curl_close($curl);
                }
            }

            return true;
        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    /**
     * Genera un nuevo correlativo para los recibos de cobro
     * del mudulo de administracion
     *
     * @author TuDrGroup
     */
    public static function generateCorrelativeSale($correlativo): string
    {

        // Obtenemos el mes y el número
        preg_match('/^(\d{2})-00(\d+)$/', $correlativo, $matches);

        if (empty($matches)) {
            // Obtener el mes actual con 2 dígitos (01, 02, ..., 12)
            $mes = now()->format('m');

            // Generar número aleatorio de 3 dígitos (100-999)
            $aleatorio = rand(100, 999);

            // Formato final: MM-00XXX
            return "{$mes}-00{$aleatorio}";
        }

        $numero = (int) $matches[2]; // Ej: 100
        $nuevoNumero = $numero + 1;   // Sumamos 1

        // Formateamos el nuevo número con al menos 3 dígitos (por si llega a 1000, etc.)
        return sprintf('%s-00%d', date('m'), $nuevoNumero);
    }

    /**
     * Genera un nuevo correlativo para los recibos de cobro
     * del mudulo de administracion
     *
     * @author TuDrGroup
     */
    public static function generateCorrelativeCollection($correlativo): string
    {

        // Obtenemos el mes y el número
        preg_match('/^(\d{2})-00(\d+)$/', $correlativo, $matches);

        if (empty($matches)) {
            // Obtener el mes actual con 2 dígitos (01, 02, ..., 12)
            $mes = now()->format('m');

            // Generar número aleatorio de 3 dígitos (100-999)
            $aleatorio = rand(100, 999);

            // Formato final: MM-00XXX
            return "{$mes}-00{$aleatorio}";
        }

        $numero = (int) $matches[2]; // Ej: 100

        $nuevoNumero = $numero + 1;   // Sumamos 1

        // Formateamos el nuevo número con al menos 3 dígitos (por si llega a 1000, etc.)
        return sprintf('%s-00%d', date('m'), $nuevoNumero);
    }

    public static function converterDate($date): string
    {
        $fecha = Carbon::createFromFormat('d/m/Y', $date);
        $soloDiaMes = $fecha->format('d/m');

        return $soloDiaMes;

    }

    public static function generateCaseCode()
    {

        try {

            if (TelemedicineCase::max('id') == null) {
                $parteEntera = 1;
            } else {
                $parteEntera = TelemedicineCase::max('id') + 1;
            }

            return random_int(11111, 99999).'-0'.$parteEntera;

        } catch (\Throwable $th) {
            Log::error($th);
        }
    }

    public static function getBusinessUnitId($plan_id)
    {
        $businessUnitId = Plan::where('id', $plan_id)
            ->with('businessUnit')
            ->first()
            ->toArray();

        return $businessUnitId['business_unit_id'];
    }

    public static function getBusinessLineId($plan_id)
    {
        $businessLineId = BusinessLine::where('business_unit_id', self::getBusinessUnitId($plan_id))->first()->id;

        return $businessLineId;
    }

    /**
     * Determina si hoy es el cumpleaños del cliente.
     *
     * * @param string $birthDateString Formato "27/04/1972"
     */
    public static function isBirthdayToday(string $birthDateString): bool
    {
        try {
            // Parseamos la fecha usando el formato específico que tienes
            $birthDate = Carbon::createFromFormat('d/m/Y', $birthDateString);
            $today = Carbon::today();

            // Comparamos mes y día únicamente
            return $birthDate->isBirthday($today);

            /* Nota: isBirthday() es un método de Carbon que internamente
               hace: $birthDate->format('m-d') === $today->format('m-d')
            */
        } catch (\Exception $e) {
            // Si el formato es inválido, retornamos false
            return false;
        }
    }

    /**
     * Obtiene la edad actual del cliente.
     */
    public static function getAge(string $birthDateString): int
    {
        return Carbon::createFromFormat('d/m/Y', $birthDateString)->age;
    }

    /**
     * Cierra la sesión de un usuario específico de forma manual.
     *
     * * @param User $user El modelo del usuario al que se le cerrará la sesión.
     * @return bool Retorna verdadero si se procesó la solicitud.
     */
    public static function logoutUser(User $user): bool
    {
        try {
            // Opción 1: Si usas el driver de base de datos para sesiones (Recomendado para control manual)
            // Esto elimina los registros de la tabla 'sessions' vinculados al ID del usuario.
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->delete();

            Log::info("Sesión cerrada para el usuario: {$user->name}");

            return true;

        } catch (\Exception $e) {
            Log::error("Error al cerrar sesión del usuario {$user->id}: ".$e->getMessage());

            return false;
        }
    }

    /**
     * Valida si una fecha tiene el formato dd/mm/yyyy.
     */
    public static function validateDateFormat(string $date): bool
    {
        // Verifica el formato básico con Regex (dd/mm/yyyy)
        if (! preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return false;
        }

        // Verifica si la fecha es válida (ej. que no sea 31/02/2024)
        try {
            Carbon::createFromFormat('d/m/Y', $date);

            return true;

        } catch (\Exception $e) {
            return false;
        }
    }

    public static function notificationFailed($type, $name, $email, $phone, $message, $group): void
    {
        try {
            NotificationFailed::create([
                'type' => $type,
                'name' => $name,
                'email' => $email ?? null,
                'phone' => $phone ?? null,
                'message' => $message,
                'group' => $group,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al crear notificación fallida: '.$e->getMessage());
        }
    }

    public static function apiWhatsAppStatus(): bool
    {
        try {

            $params = [
                'token' => config('parameters.TOKEN'),
            ];
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => 'https://api.ultramsg.com/instance117518/instance/status?'.http_build_query($params),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => [
                    'content-type: application/x-www-form-urlencoded',
                ],
            ]);

            $response = curl_exec($curl);
            $err = curl_error($curl);

            curl_close($curl);

            if ($err) {
                Log::error('INTEGRACORP: Error al obtener el estado de la API de WhatsApp: '.$err);

                return false;
            } else {
                $data = json_decode($response);
                if ($data->status->accountStatus->status == 'authenticated') {
                    Log::info('INTEGRACORP: La API de WhatsApp se encuentra autenticada');

                    return true;
                } else {
                    return false;
                }
            }
            // code...
        } catch (\Throwable $th) {
            Log::error('INTEGRACORP: Error al obtener el estado de la API de WhatsApp: '.$th->getMessage());

            return false;
        }

    }

    public static function notificacionSesionDuplicada($user_id)
    {
        try {

            $user = User::findOrFail($user_id);

            if ($user->phone == null) {
                $phone = '04127018390';
                $body = 'Se detecto un inicio de sesión en otro dispositivo para el usuario: '.$user->name.', por razones de seguridad fueron cerradas ambas sesiones. Si estaba ejecutando alguna accion dentro del sistema no te preocupes, solo debe volver a Loguearse. Si esto persiste por favor comunicarse con el administrador del Sistema.';
            } else {
                $phone = $user->phone;
                $body = 'Se detecto un inicio de sesión en otro dispositivo para el usuario: '.$user->name.', por razones de seguridad fueron cerradas ambas sesiones. Si estaba ejecutando alguna accion dentro del sistema no te preocupes, solo debe volver a Loguearse. Si esto persiste por favor comunicarse con el administrador del Sistema.';
            }

            if (self::apiWhatsAppStatus()) {
                $params = [
                    'token' => config('parameters.TOKEN'),
                    'to' => $phone,
                    'body' => $body,
                ];
                $curl = curl_init();
                curl_setopt_array($curl, [
                    CURLOPT_URL => config('parameters.CURLOPT_URL'),
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_SSL_VERIFYPEER => 0,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'POST',
                    CURLOPT_POSTFIELDS => http_build_query($params),
                    CURLOPT_HTTPHEADER => [
                        'content-type: application/x-www-form-urlencoded',
                    ],
                ]);

                $response = curl_exec($curl);
                $err = curl_error($curl);
                Log::info('INTEGRACORP: '.$response);

                curl_close($curl);
            } else {
                Log::warning('INTEGRACORP: Falla presentada en la autenticacion de la Instancia de WhatsApp');
            }

        } catch (\Throwable $th) {
            Log::error('INTEGRACORP: '.$th->getMessage());
        }
    }

    /**
     * Divide un nombre completo en dos partes (Nombres y Apellidos)
     * intentando equilibrar la carga de palabras.
     * * Ejemplo: "GUSTAVO ALBERTO CAMACHO PEREIRA"
     * Parte 1: GUSTAVO ALBERTO
     * Parte 2: CAMACHO PEREIRA
     *
     * @return array{first_part: string, second_part: string}
     */
    public static function splitName(?string $fullName): array
    {
        // dd($fullName);
        if (empty($fullName)) {
            return ['first_part' => '', 'second_part' => ''];
        }

        // Limpiar espacios extra y normalizar
        $fullName = trim(preg_replace('/\s+/', ' ', $fullName));
        $words = explode(' ', $fullName);
        $numWords = count($words);

        // Si solo hay una palabra, se pone en la primera parte
        if ($numWords === 1) {
            return [
                'first_part' => $words[0],
                'second_part' => '',
            ];
        }

        // Determinamos el punto de división.
        // Para 4 palabras (como el ejemplo), dividimos 2 y 2.
        // Para 3 palabras, dividimos 1 y 2 (Nombre y Apellidos).
        $middle = (int) ceil($numWords / 2);

        // Si son 3 palabras, preferimos dejar el primer nombre solo (1 y 2)
        if ($numWords === 3) {
            $middle = 1;
        }

        $firstPart = array_slice($words, 0, $middle);
        $secondPart = array_slice($words, $middle);

        // dd($firstPart, $secondPart);

        return [
            'first_part' => implode(' ', $firstPart),
            'second_part' => implode(' ', $secondPart),
        ];
    }

    /**
     * Muestra la vista de pago con validación extrema.
     *
     * @return \Illuminate\View\View|\Illuminate\Http\RedirectResponse
     */
    public function show(string $transaction_id, Request $request)
    {
        dd($transaction_id, $request->all(), $request->hasValidSignature());
        try {
            // 1. Buscamos la transacción por UUID v7 (Recomendación Laravel 12)
            // Solo permitimos transacciones que estén en estado 'pendiente'
            $transaction = Transaction::where('uuid', $transaction_id)
                ->where('status', 'pendiente')
                ->firstOrFail();

            // 2. Verificamos si el link ha expirado lógicamente (opcional a la firma)
            if ($transaction->expires_at && $transaction->expires_at->isPast()) {
                return redirect()->route('payment.error')
                    ->with('error', 'El link de pago ha expirado.');
            }

            return view('link-debito-inmediato', [
                'transaction' => $transaction,
                'amount' => $transaction->amount,
                'plan_name' => $transaction->plan_name,
            ]);

        } catch (ModelNotFoundException $e) {
            // Error 404: La transacción no existe o ya fue pagada/cancelada
            Log::warning("Intento de acceso a link de pago inválido o usado: ID {$transaction_id}");

            return redirect()->route('payment.error')
                ->with('error', 'El enlace de pago ya no es válido o ya fue procesado.');
        } catch (Throwable $e) {
            // Error Crítico: Fallo de base de datos o sistema
            Log::critical('Error crítico en CheckoutController: '.$e->getMessage(), [
                'id' => $transaction_id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->view('errors.500', [], 500);
        }
    }
}
