<?php

namespace App\Filament\Agents\Resources\CorporateQuotes\Pages;

use App\Models\Fee;
use App\Models\User;
use App\Models\AgeRange;
use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Illuminate\Support\Facades\DB;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Agents\Resources\CorporateQuotes\CorporateQuoteResource;

class CreateCorporateQuote extends CreateRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Formulario de Cotización Corporativa';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(CorporateQuoteResource::getUrl('index')),
        ];
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    //mutateFormDataBeforeSave()
    protected function mutateFormDataBeforeCreate(array $data): array
    {

        if ($data['plan'] == 1) {
            //guardar en la variable de sesion los detalles de la cotizacion
            session()->put('details_quote', $data['details_quote_plan_inicial']);
        }
        if ($data['plan'] == 2) {
            //guardar en la variable de sesion los detalles de la cotizacion
            session()->put('details_quote', $data['details_quote_plan_ideal']);
        }
        if ($data['plan'] == 3) {
            //guardar en la variable de sesion los detalles de la cotizacion
            session()->put('details_quote', $data['details_quote_plan_especial']);
        }
        if ($data['plan'] == 'CM') {
            //guardar en la variable de sesion los detalles de la cotizacion
            session()->put('details_quote', $data['details_quote']);
        }

        return $data;
    }

    //Calculo de la cotizacion corporativa para el agente, forma de christopher
    //---------------------------------------------------------------------------
    // protected function afterCreate(): void
    // {
    //     try {

    //         /**
    //          * Recupero la variable de sesion con los detalles de la cotizacion
    //          */
    //         $details_quote = session()->get('details_corporate_quote');


    //         $record = $this->getRecord()->toArray();

    //         //Rango de edades
    //         $array = [];

    //         for ($i = 0; $i < count($details_quote); $i++) {
    //             $rangos = DB::table('age_ranges')->select('id', 'range', 'plan_id', 'age_init', 'age_end')->where('plan_id', $details_quote[$i]['plan_id'])->orderBy('range')->get();

    //             $total_persons = $details_quote[$i]['total_persons'];
    //             $resultado = $rangos->map(function ($rango, $index) use ($total_persons) {
    //                 dd($rango, $index, $total_persons);
    //                 return [
    //                     'plan_id' => (string) $rango->plan_id,
    //                     'range' => (string) $rango->range,
    //                     'age_range_id' => (string) $rango->id, // o $rango->age_range_id si ya existe
    //                     'total_persons' => $index === 0 ? $total_persons : '1',
    //                 ];
    //             })->toArray();
    //             $array = array_merge($array, $resultado);
    //         }

    //         $resultado = $array;

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
    //                 $detail_corporate_quote->corporate_quote_id    = $record['id'];
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
    //          *  Elimino la variable de sesion para evitar sobrecargar
    //          * ----------------------------------------------------------------------------------------------------
    //          */
    //         session()->forget('details_corporate_quote');

    //     } catch (\Throwable $th) {
    //         dd($th);
    //         Notification::make()
    //             ->title('ERROR')
    //             ->body($th->getMessage())
    //             ->icon('heroicon-m-tag')
    //             ->iconColor('danger')
    //             ->danger()
    //             ->send();
    //     }
    // }

    protected function afterCreate(): void
    {
        try {

            //recupero la varaiable de sesion con los detalles de la cotizacion
            $details_quote = session()->get('details_quote');
            // dd($details_quote);

            // dd($details_quote);

            if ($details_quote[0]['plan_id'] == null) {
                return;
            }

            $record = $this->getRecord();
            // dd($record);

            /**
             * Actualizo el dato owner_agent con el id del agente que creo la cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            // $user = Auth::user()->id;
            // $record->owner_agent = $user;
            // $record->save();



            $array_form = $record->toArray();

            $array_details = $details_quote;
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
                //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                if ($array_details[$i]['age_range_id'] != null && $array_details[$i]['total_persons'] != null) {
                    $plan_ageRange = AgeRange::where('plan_id', $array_details[$i]['plan_id'])
                        ->where('id', $array_details[$i]['age_range_id'])
                        ->with('fees')
                        ->get()
                        ->toArray();

                    for ($j = 0; $j < count($plan_ageRange[0]['fees']); $j++) {

                        $fee = Fee::where('id', $plan_ageRange[0]['fees'][$j]['id'])->first();
                        $detail_individual_quote = new DetailCorporateQuote();
                        $detail_individual_quote->corporate_quote_id   = $array_form['id'];
                        $detail_individual_quote->plan_id               = $array_details[$i]['plan_id'];
                        $detail_individual_quote->age_range_id          = $array_details[$i]['age_range_id'];
                        $detail_individual_quote->coverage_id           = $fee->coverage_id;
                        $detail_individual_quote->fee                   = $fee->price;
                        $detail_individual_quote->total_persons         = $array_details[$i]['total_persons'];
                        $detail_individual_quote->subtotal_anual        = $array_details[$i]['total_persons'] * $fee->price;
                        $detail_individual_quote->subtotal_quarterly    = ($array_details[$i]['total_persons'] * $fee->price) / 4;
                        $detail_individual_quote->subtotal_biannual     = ($array_details[$i]['total_persons'] * $fee->price) / 2;
                        $detail_individual_quote->subtotal_monthly      = ($array_details[$i]['total_persons'] * $fee->price) / 12;
                        $detail_individual_quote->status                = 'PRE-APROBADA';
                        $detail_individual_quote->created_by            = Auth::user()->name;
                        $detail_individual_quote->save();
                    }
                }
            }

            //elimino la variable de sesion para evitar sobrecargar
            session()->forget('details_quote');


            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             * @param $this->data [Data del formulario]
             * @param $record [Data de la cotizacion guardada en la base de dastos]
             * ----------------------------------------------------------------------------------------------------
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
                    'data' => $detalle
                ];

                $this->getRecord()->sendPropuestaEconomicaPlanInicial($details);
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
                    'data' => $detalle
                ];

                $this->getRecord()->sendPropuestaEconomicaPlanIdeal($details);
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
                    'data' => $detalle
                ];

                $this->getRecord()->sendPropuestaEconomicaPlanEspecial($details);
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
                            'data' => $detalle_1
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
                            'data' => $detalle_2
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
                            'data' => $detalle_3
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

                $this->getRecord()->sendPropuestaEconomicaMultiple($collect_final);
            }

            /**
             * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
             * ----------------------------------------------------------------------------------------------------
             * $record [Data de la cotizacion guardada en la base de dastos]
             */
            $recipient = User::where('is_admin', 1)->get();
            foreach ($recipient as $user) {
                $recipient_for_user = User::find($user->id);
                Notification::make()
                    ->title('NUEVA COTIZACION INDIVUDUAL')
                    ->body('Se ha registrado una nueva cotizacion individual de forma exitosa. Codigo: ' . $record->code)
                    ->icon('heroicon-m-tag')
                    ->iconColor('success')
                    ->success()
                    ->actions([
                        Action::make('view')
                            ->label('Ver cotizacion individual')
                            ->button()
                            ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                    ])
                    ->sendToDatabase($recipient_for_user);
            }

        } catch (\Throwable $th) {
            dd($th);
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }

    //getCreatedNotification
    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('NOTIFICACIÓN')
            ->body('Cotización Corporativa exitosa.!')
            ->icon('entypo-pin')
            ->iconColor('danger')
            ->success()
            ->persistent()
            ->send();
    }

    

}