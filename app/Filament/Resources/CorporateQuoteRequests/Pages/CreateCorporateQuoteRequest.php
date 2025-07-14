<?php

namespace App\Filament\Resources\CorporateQuoteRequests\Pages;

use App\Models\User;
use App\Models\Agent;
use App\Models\Agency;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;

class CreateCorporateQuoteRequest extends CreateRecord
{
    protected static string $resource = CorporateQuoteRequestResource::class;

    protected static ?string $title = 'Crea Solicitud de Cotización Corporativa';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('warning')
                ->url(CorporateQuoteRequestResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        session()->put('details_corporate_quote_requests', $data['details_corporate_quote_requests']);

        if($data['agent_id'] != null){
            
            $owner = Agent::select('owner_code', 'id')->where('id', $data['agent_id'])->first()->owner_code;
    
            if ($owner == 'TDG-100') {
    
                $data['owner_code']  = 'TDG-100';
                $data['code_agency'] = 'TDG-100';
                
            } else {
    
                $data['owner_code']  = Agency::select('owner_code', 'code')->where('code', $data['code_agency'])->first()->owner_code;
            }
            
        }else{
            $data['code_agency'] = $data['code_agency'];
            $data['owner_code']  = Agency::select('owner_code', 'code')->where('code', $data['code_agency'])->first()->owner_code;
            
        }
        
        return $data;
    }

    protected function afterCreate(): void
    {

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

            if($record->agent_id != null){
                $recipient = User::where('is_agent', 1)->where('agent_id', $record->agent_id)->get();
                foreach ($recipient as $user) {
                    $recipient_for_user = User::find($user->id);
                    Notification::make()
                        ->title('COTIZACION CORPORATIVA CREADA')
                        ->body('Se ha registrado una nueva cotización corporativa de forma exitosa. Código: ' . $record->code)
                        ->icon('heroicon-s-user-group')
                        ->iconColor('success')
                        ->success()
                        ->actions([
                            Action::make('view')
                                ->label('Ver detalle de solicitud')
                                ->button()
                                ->url(CorporateQuoteRequestResource::getUrl('view', ['record' => $record->id], panel: 'agents')),
                        ])
                        ->sendToDatabase($recipient_for_user);
                }

            }

            if ($record->agent_id == null) {
                $recipient = User::where('is_agency', 1)->where('code_agency', $record->code_agency)->get();
                foreach ($recipient as $user) {
                    $recipient_for_user = User::find($user->id);
                    Notification::make()
                        ->title('COTIZACION CORPORATIVA CREADA')
                        ->body('Se ha registrado una nueva cotización corporativa de forma exitosa. Código: ' . $record->code)
                        ->icon('heroicon-s-user-group')
                        ->iconColor('success')
                        ->success()
                        ->sendToDatabase($recipient_for_user);
                }
            }


            $this->getRecord()->sendNotification($record);
        } catch (\Throwable $th) {
            dd($th);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
    
}