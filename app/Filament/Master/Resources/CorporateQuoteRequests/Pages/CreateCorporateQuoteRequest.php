<?php

namespace App\Filament\Master\Resources\CorporateQuoteRequests\Pages;

use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Master\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class CreateCorporateQuoteRequest extends CreateRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'CREAR SOLICITUD';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        session()->put('details_corporate_quote_requests', $data['details_corporate_quote_requests']);
        return $data;
    }

    protected function afterCreate(): void
    {
        // dd($this->record);
        try {

            /**
             * Recupero la variable de sesion con los detalles de la cotizacion
             */
            $details_quote_requests = session()->get('details_corporate_quote_requests');

            $record = $this->getRecord();

            $array_form = $record->toArray();

            $array_details = $details_quote_requests;

            /**
             * For para realizar el guardado en la tabla de detalle de cotizacion
             * ----------------------------------------------------------------------------------------------------
             */
            for ($i = 0; $i < count($array_details); $i++) {
                //Guardamos el detalle de la cotizacion en la tabla de detalle de cotizacion como segundo paso
                if ($array_details[$i]['plan_id'] != null && $array_details[$i]['total_persons'] != null) {
                    $record->details()->create(
                        [
                            'plan_id'       => $array_details[$i]['plan_id'],
                            'total_persons' => $array_details[$i]['total_persons'],
                            'status'        => 'PRE-APROBADA',
                            'created_by'    => Auth::user()->name,

                        ]
                    );
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
                    ->actions([
                        Action::make('view')
                            ->label('Ver detalle de solicitud')
                            ->button()
                            ->url(CorporateQuoteRequestResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                    ])
                    ->sendToDatabase($recipient_for_user);
            }

            $this->getRecord()->sendNotification($record);
        } catch (\Throwable $th) {
            dd($th);
        }
    }
}