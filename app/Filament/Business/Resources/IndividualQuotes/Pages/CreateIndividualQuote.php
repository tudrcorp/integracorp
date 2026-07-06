<?php

namespace App\Filament\Business\Resources\IndividualQuotes\Pages;

use App\Filament\Business\Resources\IndividualQuotes\IndividualQuoteResource;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

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
                ->color('warning')
                ->url(IndividualQuoteResource::getUrl('index')),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($data['plan'] === 'CM') {
            session()->put('details_quote', $data['details_quote_multiple'] ?? []);
        } else {
            $planId = (int) $data['plan'];
            $details = collect($data['details_quote'] ?? [])
                ->map(fn (array $row): array => array_merge($row, ['plan_id' => $planId]))
                ->values()
                ->all();

            session()->put('details_quote', $details);
        }

        $data['code_agency'] = $data['code_agency'] == null ? 'TDG-100' : $data['code_agency'];
        $data['agent_id'] = $data['agent_id'] == null ? null : $data['agent_id'];

        if ($data['code_agency'] != 'TDG-100') {
            $data['owner_code'] = Agency::where('code', $data['code_agency'])->first()->owner_code;
        } else {
            $data['owner_code'] = 'TDG-100';
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        try {
            $detailsQuote = session()->get('details_quote', []);

            if ($detailsQuote === [] || ($detailsQuote[0]['plan_id'] ?? null) === null) {
                return;
            }

            $record = $this->getRecord();

            $res = UtilsController::storeDetailsIndividualQuote(
                $record,
                $record->toArray(),
                $detailsQuote,
                $detailsQuote,
            );

            if (! $res) {
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
}
