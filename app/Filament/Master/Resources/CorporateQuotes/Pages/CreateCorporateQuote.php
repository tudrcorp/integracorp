<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Models\Fee;
use App\Models\User;
use App\Models\AgeRange;
use Illuminate\Support\Facades\DB;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;

class CreateCorporateQuote extends CreateRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        session()->put('details_corporate_quote', $data['details_corporate_quote']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // dd($this->record);
        try {

            /**
             * Recupero la variable de sesion con los detalles de la cotizacion
             */
            $details_quote = session()->get('details_corporate_quote');

            if ($details_quote[0]['plan_id'] == null) {
                return;
            }

            $record = $this->getRecord();

            $array_form = $record->toArray();

            $array_details = $details_quote;

            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($array_details); $i++) {
                //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
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

            /**
             *  Elimino la variable de sesion para evitar sobrecargar
             * ----------------------------------------------------------------------------------------------------
             */
            session()->forget('details_corporate_quote');


            /**
             * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
             * ----------------------------------------------------------------------------------------------------
             * $record [Data de la cotizacion guardada en la base de dastos]
             */
            $recipient = User::where('is_admin', 1)->get();
            foreach ($recipient as $user) {
                $recipient_for_user = User::find($user->id);
                Notification::make()
                    ->title('COTIZACION CORPORATIVA CREADA')
                    ->body('Se ha registrado una nueva cotizacion corporativa de forma exitosa. Codigo: ' . $record->code)
                    ->icon('heroicon-s-user-group')
                    ->iconColor('success')
                    ->success()
                    ->sendToDatabase($recipient_for_user);
            }


            /**
             * LOgica para el envio de correo con los detalles de la cotizacion
             * @param $this->data [Data del formulario]
             * @param $record [Data de la cotizacion guardada en la base de dastos]
             * ----------------------------------------------------------------------------------------------------
             */
            $detalle = DB::table('detail_corporate_quotes')
                ->join('plans', 'detail_corporate_quotes.plan_id', '=', 'plans.id')
                ->join('age_ranges', 'detail_corporate_quotes.age_range_id', '=', 'age_ranges.id')
                ->join('coverages', 'detail_corporate_quotes.coverage_id', '=', 'coverages.id')
                ->select('detail_corporate_quotes.*', 'plans.description as plan', 'age_ranges.range as age_range', 'coverages.price as coverage')
                ->where('corporate_quote_id', $record->id)
                ->get()
                ->toArray();

            $email = $this->data['email'];

            // $send_email = NotificationController::sendEmail($email, $detalle);

        } catch (\Throwable $th) {
            dd($th);
        }
    }
}