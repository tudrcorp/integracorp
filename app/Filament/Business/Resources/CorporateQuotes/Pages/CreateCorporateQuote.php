<?php

namespace App\Filament\Business\Resources\CorporateQuotes\Pages;

use App\Models\Fee;
use App\Models\User;
use App\Models\Agency;
use App\Models\AgeRange;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use App\Models\DetailCorporateQuote;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\Business\Resources\CorporateQuotes\CorporateQuoteResource;

class CreateCorporateQuote extends CreateRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Formulario de Cotizacion Corporativa';

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

        if (!isset($data['observation_dress_tailor'])) {
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

            $data['code_agency']    = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
            $data['agent_id']       = $data['agent_id'] == null ? null : $data['agent_id'];

            if ($data['code_agency'] != 'TDG-100') {
                $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
            } else {
                $data['owner_code'] = 'TDG-100';
            }

            return $data;
        } else {

            $data['code_agency']    = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
            $data['agent_id']       = $data['agent_id'] == null ? null : $data['agent_id'];

            $data['code_agency']    = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
            $data['agent_id']       = $data['agent_id'] == null ? null : $data['agent_id'];

            if (Agency::where('code', $data['code_agency'])->exists()) {
                $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
            } else {
                $data['owner_code'] = 'TDG-100';
            }
            
            return $data;
        }
    }

    protected function afterCreate(): void
    {
        try {

            //Validacion para cotizacion Dress-Tailor
            if (isset($this->data['observation_dress_tailor'])) {
                return;
            }

            //recupero la varaiable de sesion con los detalles de la cotizacion
            $details_quote = session()->get('details_quote');

            if ($details_quote[0]['plan_id'] == null) {
                return;
            }

            $record = $this->getRecord();
            // dd($record);

            $array_form = $record->toArray();

            $array_details = $details_quote;

            /**
             * For para contar cuantos rango de edades son diferentes de null en el array $array_details
             * ----------------------------------------------------------------------------------------------------
             */
            $count_ageRange = 0;
            for ($i = 0; $i < count($array_details); $i++) {
                if ($array_details[$i]['age_range_id'] != null && $array_details[$i]['total_persons'] != null) {
                    $count_ageRange++;
                }
            }

            if ($count_ageRange == 0) {
                return;
            }

            if ($count_ageRange == 1) {
                $details = [];
                for ($i = 0; $i < count($array_details); $i++) {
                    if ($array_details[$i]['age_range_id'] != null && $array_details[$i]['total_persons'] != null) {
                        $details[0] = $array_details[$i];
                        break;
                    }
                }
                UtilsController::createCorporateQuoteGeneral($record->id, $details);
            }

            if ($count_ageRange > 1) {
                UtilsController::createCorporateQuoteEspecific($record, $array_form, $array_details, $details_quote);
            }

            /**
             * Logica para enviar una notificacion a la sesion del administrador despues de crear la corizacion
             * ----------------------------------------------------------------------------------------------------
             * $record [Data de la cotizacion guardada en la base de dastos]
             */
            $recipient = User::where('is_admin', 1)->where('departament', 'NEGOCIOS')->get();
            foreach ($recipient as $user) {
                $recipient_for_user = User::find($user->id);
                Notification::make()
                    ->title('NUEVA COTIZACIÓN CORPORATOVA')
                    ->body('Se ha registrado una nueva cotización corporativa de forma exitosa. Código: ' . $record->code)
                    ->icon('heroicon-m-tag')
                    ->iconColor('success')
                    ->success()
                    ->actions([
                        Action::make('view')
                            ->label('Ver Cotización Corporativa')
                            ->button()
                            ->color('primary')
                            ->url(CorporateQuoteResource::getUrl('edit', ['record' => $record->id], panel: 'admin')),
                        Action::make('link')
                            ->label('Link Interactivo')
                            ->button()
                            ->color('success')
                            ->url(route('volt.cor.home', ['quote' => Crypt::encryptString($record->id)]), shouldOpenInNewTab: true),
                    ])
                    ->sendToDatabase($recipient_for_user);
            }

            //Notificacion por whatsapp al telefono de cotizaciones
            $sendNotificationWp = NotificationController::createdCorporateQuote($record->code, Auth::user()->name);

            
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