<?php

namespace App\Filament\General\Resources\IndividualQuotes\Pages;

use App\Models\Fee;
use App\Models\User;
use App\Models\AgeRange;
use Filament\Actions\Action;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use App\Models\DetailIndividualQuote;
use Illuminate\Support\Facades\Crypt;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
use Filament\Resources\Pages\CreateRecord;
use App\Http\Controllers\NotificationController;
use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;

class CreateIndividualQuote extends CreateRecord
{
    protected static string $resource = IndividualQuoteResource::class;

    protected static ?string $title = 'Formulario de Cotización Individual';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(IndividualQuoteResource::getUrl('index')),
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

    protected function afterCreate(): void
    {
        try {

            //recupero la varaiable de sesion con los detalles de la cotizacion
            $details_quote = session()->get('details_quote');

            if ($details_quote[0]['plan_id'] == null) {
                return;
            }

            $record = $this->getRecord();

            $array_form = $record->toArray();

            $array_details = $details_quote;

            $res = UtilsController::storeDetailsIndividualQuote($record, $array_form, $array_details, $details_quote);

            if (!$res) {
                throw new \Exception('Error al guardar los detalles de la cotización.');
            }

            NotificationController::createdIndividualQuote($record->code, Auth::user()->name);
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

    //getCreatedNotification
    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->title('NOTIFICACIÓN')
            ->body('Cotización Individual exitosa!. En breves segundos su cotización estará disponible en la opción de descargar cotización. ⬇️')
            ->icon('entypo-pin')
            ->iconColor('danger')
            ->success()
            ->persistent()
            ->send();
    }
}