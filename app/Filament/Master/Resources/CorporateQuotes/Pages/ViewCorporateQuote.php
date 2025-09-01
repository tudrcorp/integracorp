<?php

namespace App\Filament\Master\Resources\CorporateQuotes\Pages;

use Filament\Actions\Action;
use App\Models\CorporateQuote;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Master\Resources\CorporateQuotes\CorporateQuoteResource;

class ViewCorporateQuote extends ViewRecord
{
    protected static string $resource = CorporateQuoteResource::class;

    protected static ?string $title = 'Información General';

    // protected function getHeaderActions(): array
    // {
    //     return [
    //         Action::make('pre_affiliation_multiple')
    //             ->label('Pre-Afiliación Multi Plan(es)')
    //             ->icon('fluentui-multiselect-rtl-24')
    //             ->requiresConfirmation()
    //             ->modalHeading('Pre-Afiliación Multiple')
    //             ->modalIcon('fluentui-multiselect-rtl-24')
    //             ->modalDescription('El sistema te redirigirá a la pantalla donde se encuentra la tabla de cotización multiple. Debes seleccionar dos o mas planes para realizar el proceso de pre-afiliación multi plan(es).')
    //             ->action(function (CorporateQuote $record) {
    //                 return redirect()->route('filament.master.resources.corporate-quotes.edit', ['record' => $record->id]);
    //             })
    //             ->hidden(function (CorporateQuote $record) {
    //                 return $record->status == 'EJECUTADA' || $record->status == 'APROBADA' || $record->plan != 'CM';
    //             }),
    //         Action::make('add_observations')
    //             ->label('Agregar Observaciones')
    //             ->icon('heroicon-s-hand-raised')
    //             ->color('warning')
    //             ->requiresConfirmation()
    //             ->modalHeading('OBSERVACIONES DEL AGENTE')
    //             ->modalIcon('heroicon-s-hand-raised')
    //             ->form([
    //                 Textarea::make('description')
    //                     ->label('Observaciones')
    //                     ->rows(5)
    //             ])
    //             ->action(function (CorporateQuote $record, array $data) {

    //                 try {

    //                     $record->observations = $data['description'];
    //                     $record->save();

    //                     Notification::make()
    //                         ->body('Las observaciones fueron registradas exitosamente.')
    //                         ->success()
    //                         ->send();
    //                 } catch (\Throwable $th) {
    //                     LogController::log(Auth::user()->id, 'EXCEPTION', 'master.IndividualQuoteResource.action.enit', $th->getMessage());
    //                     Notification::make()
    //                         ->title('ERROR')
    //                         ->body($th->getMessage())
    //                         ->icon('heroicon-s-x-circle')
    //                         ->iconColor('danger')
    //                         ->danger()
    //                         ->send();
    //                 }
    //             }),
    //     ];
    // }
}