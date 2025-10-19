<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use Carbon\Carbon;
use App\Models\User;
use App\Models\Affiliate;
use Filament\Actions\Action;
use App\Models\IndividualQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use App\Http\Controllers\PdfController;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;

class CreateAffiliation extends CreateRecord
{
    protected static string $resource = AffiliationResource::class;

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(AffiliationResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        session()->put('affiliates', isset($data['affiliates']) ? $data['affiliates'] : []);

        $data['feedback'] = $data['feedback'] == true ? true : false;

        if ($data['feedback_dos']) {
            $data['full_name_payer'] = $data['full_name_ti'];
            $data['nro_identificacion_payer'] = $data['nro_identificacion_ti'];
            $data['email_payer'] = $data['email_ti'];
            $data['phone_payer'] = $data['phone_ti'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            /** 
             * ? Preguntamos si e l contratante es el mismo titular de la cotizacion 
             * $feedback == false significa que el contratante no es el titular, y debemos agregar afiliados
             * $feedback == true significa que el contratante es el titular, y debemos afiliar al contratante
             * -----------------------------------------------------------------------------------------------------------------------------
             */
            // El titular desea agregar afiliados
            if ($record->feedback == true) {
                /**
                 * Recupero los detalles de los afiliados
                 * ----------------------------------------------------------------------------------------------------
                 */
                $affiliates = session()->get('affiliates');
                // dd($affiliates);
                //Agregamos al titular al array de afiliados
                $affiliates[] = [
                    "full_name"          => $record->full_name_ti,
                    "nro_identificacion" => $record->nro_identificacion_ti,
                    "sex"                => $record->sex_ti,
                    "birth_date"         => $record->birth_date_ti,
                    "relationship"       => "TITULAR",
                    "document"           => $record->document,
                ];

                //Ordenamos los afiliados por fecha de nacimiento
                usort($affiliates, function ($a, $b) {
                    // Si uno es TITULAR, va primero
                    if ($a['relationship'] === 'TITULAR' && $b['relationship'] !== 'TITULAR') {
                        return -1;
                    }
                    if ($a['relationship'] !== 'TITULAR' && $b['relationship'] === 'TITULAR') {
                        return 1;
                    }

                    // Si ambos son distintos de TITULAR, ordenar alfabéticamente descendente por relationship
                    return $b['relationship'] <=> $a['relationship'];
                });


                // dd($affiliates);

                /**
                 * Validamos si el numeros de personas en la cotizacion es el mismo numero de personas
                 * afiliadas en el formulario
                 * Si el numero es diferente mostramos actualizamos la cotizacion para que el cliente pueda volver a pre-afiliarse
                 * -----------------------------------------------------------------------------------------------------------------------------
                 */

                /** NUmero de personas en la cotizacion */
                $persons = session()->get('persons');
                // dd($persons, count($affiliates));

                /**Actualizo el calculo de la cotizacion */
                if (count($affiliates) != $persons) {
                    // dd(count($affiliates));
                    $quote = DetailIndividualQuote::where('individual_quote_id', $record->individual_quote_id)->get();
                    foreach ($quote as $item) {
                        $item->total_persons        = count($affiliates);
                        $item->subtotal_anual       = count($affiliates) * $item->fee;
                        $item->subtotal_quarterly   = $item->subtotal_anual / 4;
                        $item->subtotal_biannual    = $item->subtotal_anual / 2;
                        $item->subtotal_monthly     = $item->subtotal_anual / 12;
                        $item->save();
                    }

                    /** Actualizo el PDF de la cotizacion */
                    $individual_quote = IndividualQuote::where('id', $record->individual_quote_id)->first();
                    $update_pdf = PdfController::generatePdfIndividualQuote($individual_quote);
                }


                /**----------------------------------------------------------------------------------------------------------------------------- */


                /**
                 * For para cargar la data de los afiliados en la tabla de affiliates
                 * ----------------------------------------------------------------------------------------------------
                 */
                for ($i = 0; $i < count($affiliates); $i++) {
                    $record->affiliates()->create([
                        'full_name'             => $affiliates[$i]['full_name'],
                        'nro_identificacion'    => $affiliates[$i]['nro_identificacion'],
                        'sex'                   => $affiliates[$i]['sex'],
                        'birth_date'            => $affiliates[$i]['birth_date'],
                        'age'                   => Carbon::parse($affiliates[$i]['birth_date'])->age,
                        'relationship'          => $affiliates[$i]['relationship'],
                        'document'              => $affiliates[$i]['document'],
                        'address'               => $record->adress_ti,
                        'phone'                 => $record->phone_ti,
                        'country_id'            => $record->country_id_ti,
                        'state_id'              => $record->state_id_ti,
                        'city_id'               => $record->city_id_ti,
                        'region'                => $record->region_ti,
                        'status'                => 'PRE-APROBADA',
                    ]);
                }

                /**
                 * Actualizamos el estatus de la cotizacion a EJECUTADA
                 * para evitar que pueda volverse a pre-afiliarse
                 * 
                 * Esta actualizacion se realiza en ambas tablas
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote = IndividualQuote::select('status', 'id')->where('id', $record->individual_quote_id)->firstOrFail();
                $quote->status = 'EJECUTADA';
                $quote->save();

                $quote->detailsQuote()->update(['status' => 'EJECUTADA']);


                /**
                 * Actualizacion de la cotizacion
                 * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote_detail = DetailIndividualQuote::select('coverage_id', 'status', 'id')
                    ->where('individual_quote_id', $record->individual_quote_id)
                    ->where('coverage_id', $record->coverage_id)
                    ->firstOrFail();
                $quote_detail->status = 'APROBADA';
                $quote_detail->save();


                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                $data_titular = Affiliate::where('affiliation_id', $record->id)->where('relationship', 'TITULAR')->firstOrFail()->toArray();

                $this->getRecord()->sendCertificate($record, $data_titular, $affiliates);


                /** 
                 * Elimino las variable de sesion para evitar sobrecargar
                 * ----------------------------------------------------------------------------------------------------
                 */
                session()->forget('affiliates');
                session()->forget('persons');

                /**
                 * Actualizo el numero de afiliados (poblacion)
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->family_members = $record->affiliates()->count();
                $record->save();



                /**
                 * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
                 * ----------------------------------------------------------------------------------------------------
                 * $record [Data de la cotizacion guardada en la base de dastos]
                 */
                $recipient = User::where('is_admin', 1)->where('departament', 'AFILIACIONES')->get();
                foreach ($recipient as $user) {
                    $recipient_for_user = User::find($user->id);
                    Notification::make()
                        ->title('PRE-AFILIACION INDIVIDUAL CREADA')
                        ->body('Se ha registrado una nueva pre-afiliacion individual de forma exitosa. Codigo: ' . $record->code)
                        ->icon('heroicon-s-user-group')
                        ->iconColor('success')
                        ->success()
                        ->actions([
                            Action::make('view')
                                ->label('Ver detalle de pre-afiliación')
                                ->button()
                                ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                        ])
                        ->sendToDatabase($recipient_for_user);
                }
            }

            /**----------------------------------------------------------------------------------------------------------------------------- */

            // El titular ese el unico afiliado
            if ($record->feedback == false) {
                /** 1- Registro los datos de contratante como los datos del afiliado ya que la cotizacion es para el mismo*/
                $record->affiliates()->create([
                    'full_name'             => $record->full_name_ti,
                    'nro_identificacion'    => $record->nro_identificacion_ti,
                    'sex'                   => $record->sex_ti,
                    'birth_date'            => $record->birth_date_ti,
                    'age'                   => Carbon::parse($record->birth_date_ti)->age,
                    'address'               => $record->adress_ti,
                    'document'              => $record->document,
                    'phone'                 => $record->phone_ti,
                    'country_id'            => $record->country_id_ti,
                    'state_id'              => $record->state_id_ti,
                    'city_id'               => $record->city_id_ti,
                    'region'                => $record->region_ti,
                    'status'                => 'PRE-APROBADA',
                    'relationship'          => 'TITULAR',
                ]);

                /**
                 * Actualizamos el estatus de la cotizacion a EJECUTADA
                 * para evitar que pueda volverse a pre-afiliarse
                 * 
                 * Esta actualizacion se realiza en ambas tablas
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote = IndividualQuote::select('status', 'id')->where('id', $record->individual_quote_id)->firstOrFail();
                $quote->status = 'EJECUTADA';
                $quote->save();

                $quote->detailsQuote()->update(['status' => 'EJECUTADA']);


                /**
                 * Actualizacion de la cotizacion
                 * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
                 * ----------------------------------------------------------------------------------------------------
                 */
                $quote_detail = DetailIndividualQuote::select('coverage_id', 'status', 'id')
                    ->where('individual_quote_id', $record->individual_quote_id)
                    ->where('coverage_id', $record->coverage_id)
                    ->firstOrFail();
                $quote_detail->status = 'APROBADA';
                $quote_detail->save();


                /**
                 * Elimino las variable de sesion para evitar sobrecargar
                 * ----------------------------------------------------------------------------------------------------
                 */
                session()->forget('persons');


                /**
                 * Se envia el certificado del afiliado
                 * ----------------------------------------------------------------------------------------------------
                 */
                // $data_titular = Affiliate::where('affiliation_id', $record->id)->where('relationship', 'TITULAR')->firstOrFail()->toArray();
                $affiliate = Affiliate::where('affiliation_id', $record->id)->get()->toArray();
                // dd($data_titular, $affiliate);
                $this->getRecord()->sendCertificateOnlyHolder($record, $affiliate);

                /**
                 * Actualizo el numero de afiliados (poblacion)
                 * ----------------------------------------------------------------------------------------------------
                 */
                $record->family_members = 1;
                $record->save();

                /**
                 * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
                 * ----------------------------------------------------------------------------------------------------
                 * $record [Data de la cotizacion guardada en la base de dastos]
                 */
                $recipient = User::where('is_admin', 1)->where('departament', 'AFILIACIONES')->get();
                foreach ($recipient as $user) {
                    $recipient_for_user = User::find($user->id);
                    Notification::make()
                        ->title('PRE-AFILIACION INDIVIDUAL CREADA')
                        ->body('Se ha registrado una nueva pre-afiliacion individual de forma exitosa. Codigo: ' . $record->code)
                        ->icon('heroicon-s-user-group')
                        ->iconColor('success')
                        ->success()
                        ->actions([
                            Action::make('view')
                                ->label('Ver detalle de pre-afiliación')
                                ->button()
                                ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'business')),
                        ])
                        ->sendToDatabase($recipient_for_user);
                }
            }


            /**
             * Notificación para el usuario que creo la cotización
             * ----------------------------------------------------------------------------------
             */
            NotificationController::createdIndividualPreAfilliation($record->code, Auth::user()->name);
        } catch (\Throwable $th) {
            dd($th);
        }
    }


    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}