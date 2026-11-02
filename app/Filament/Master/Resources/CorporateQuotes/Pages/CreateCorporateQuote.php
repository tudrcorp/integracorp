<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use App\Filament\Concerns\HandlesQuoteUpgrades;
use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateCorporateQuote extends CreateRecord
{
    use HandlesQuoteUpgrades;

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

    protected function quotesForNetwork(): bool
    {
        return true;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    // mutateFormDataBeforeSave()
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->captureQuoteUpgrades($data);

        if (! isset($data['observation_dress_tailor'])) {
            if ($data['plan'] == 1) {
                // guardar en la variable de sesion los detalles de la cotizacion
                session()->put('details_quote', $data['details_quote_plan_inicial']);
            }
            if ($data['plan'] == 2) {
                // guardar en la variable de sesion los detalles de la cotizacion
                session()->put('details_quote', $data['details_quote_plan_ideal']);
            }
            if ($data['plan'] == 3) {
                // guardar en la variable de sesion los detalles de la cotizacion
                session()->put('details_quote', $data['details_quote_plan_especial']);
            }
            if ($data['plan'] == 'CM') {
                // guardar en la variable de sesion los detalles de la cotizacion
                session()->put('details_quote', $data['details_quote']);
            }

            return $data;
        } else {

            return $data;
        }
    }

    protected function afterCreate(): void
    {
        $this->persistQuoteUpgrades($this->getRecord());

        try {

            // Validacion para cotizacion Dress-Tailor
            if (isset($this->data['observation_dress_tailor'])) {
                return;
            }

            // recupero la varaiable de sesion con los detalles de la cotizacion
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

            // Notificacion por whatsapp al telefono de cotizaciones
            $sendNotificationWp = NotificationController::createdCorporateQuote($record->code, Auth::user()->name);
        } catch (\Throwable $th) {
            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }

    // getCreatedNotification
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
