<?php

namespace App\Filament\Business\Resources\AffiliationCorporates\Pages;

use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAffiliationCorporate extends CreateRecord
{
    protected static string $resource = AffiliationCorporateResource::class;

    protected function afterCreate(): void
    {
        try {

            $record = $this->getRecord();

            /**
             * Actualizacion de la cotizacion
             * Se cambia el estatus de la cobertura de la cotizacion que selecciono el cliente
             * ----------------------------------------------------------------------------------------------------
             */
            $quote = DetailCorporateQuote::select('coverage_id', 'status', 'id')->where('coverage_id', $record->coverage_id)->firstOrFail();
            $quote->status = 'APROBADA';
            $quote->save();


            /**
             * Recupero los planes de afiliados seleccionados por el agente o la agencia
             * en la vista de cotizaciones corporativas
             * ----------------------------------------------------------------------------------------------------
             */
            $data_records = session()->get('data_records');

            for ($i = 0; $i < count($data_records); $i++) {

                /** Guardar los datos en la tabla de afiliados */
                $detailsAfiliationPlans = AfilliationCorporatePlan::create([
                    'affiliation_corporate_id'  => $record->id,
                    'code_affiliation'          => $record->code,
                    'plan_id'                   => $data_records[$i]['plan_id'],
                    'coverage_id'               => $data_records[$i]['coverage_id'],
                    'age_range_id'              => $data_records[$i]['age_range_id'],
                    'total_persons'             => $data_records[$i]['total_persons'],
                    'fee'                       => $data_records[$i]['fee'],
                    'subtotal_anual'            => $data_records[$i]['subtotal_anual'],
                    'subtotal_quarterly'        => $data_records[$i]['subtotal_quarterly'],
                    'subtotal_biannual'         => $data_records[$i]['subtotal_biannual'],
                    'subtotal_monthly'          => $data_records[$i]['subtotal_monthly'],
                    'status'                    => 'PRE-APROBADA',
                    'created_by'                => Auth::user()->name,
                ]);
            }

            //elimino la variable de sesion para evitar sobrecargar dicho contenedor
            session()->forget('data_records');


            /**
             * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
             * ----------------------------------------------------------------------------------------------------
             * $record [Data de la cotizacion guardada en la base de dastos]
             */
            $recipient = User::where('is_admin', 1)->get();
            foreach ($recipient as $user) {
                $recipient_for_user = User::find($user->id);
                Notification::make()
                    ->title('PRE-AFILIACION CORPORATIVA CREADA')
                    ->body('Se ha registrado una nueva pre-afiliacion corporativa de forma exitosa. Codigo: ' . $record->code)
                    ->icon('heroicon-s-user-group')
                    ->iconColor('success')
                    ->success()
                    ->sendToDatabase($recipient_for_user);
            }
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}