<?php

namespace App\Filament\Agents\Resources\Affiliations\Pages;

use App\Models\User;
use Filament\Actions\Action;
use App\Models\IndividualQuote;
use App\Models\DetailIndividualQuote;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Agents\Resources\Affiliations\AffiliationResource;

class CreateAffiliation extends CreateRecord
{
    protected static string $resource = AffiliationResource::class;

    protected static ?string $title = 'PREAFILIACION INDIVIDUAL';

    protected function mutateFormDataBeforeCreate(array $data): array
    {

        // dd($data['affiliates']);
        session()->put('affiliates', $data['affiliates']);

        if ($data['feedback'] == 1) {

            $data['full_name_ti'] = $data['full_name_con'];
            $data['nro_identificacion_ti'] = $data['nro_identificacion_con'];
            $data['sex_ti'] = $data['sex_con'];
            $data['birth_date_ti'] = $data['birth_date_con'];
            $data['adress_ti'] = $data['adress_con'];
            $data['city_id_ti'] = $data['city_id_con'];
            $data['state_id_ti'] = $data['state_id_con'];
            $data['country_id_ti'] = $data['country_id_con'];
            $data['region_ti'] = $data['region_con'];
            $data['phone_ti'] = $data['phone_con'];
            $data['full_name_ti'] = $data['full_name_con'];
            $data['email_ti'] = $data['email_con'];
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            /**
             * Recupero los detalles de los afiliados
             * ----------------------------------------------------------------------------------------------------
             */
            $affiliates = session()->get('affiliates');


            /**
             * For para cargar la data de los afiliados en la tabla de affiliates
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($affiliates); $i++) {
                $record->affiliates()->create([
                    'full_name' => $affiliates[$i]['full_name'],
                    'nro_identificacion' => $affiliates[$i]['nro_identificacion'],
                    'sex' => $affiliates[$i]['sex'],
                    'birth_date' => $affiliates[$i]['birth_date'],
                    'relationship' => $affiliates[$i]['relationship'],
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
            $this->getRecord()->sendCertificate($record, $affiliates);


            /**
             * Elimino la variable de sesion para evitar sobrecargar
             * ----------------------------------------------------------------------------------------------------
             */
            session()->forget('affiliates');

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
            $recipient = User::where('is_admin', 1)->get();
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
                            ->label('Ver detalle de pre-afiliacion')
                            ->button()
                            ->url(AffiliationResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                    ])
                    ->sendToDatabase($recipient_for_user);
            }
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}