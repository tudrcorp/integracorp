<?php

namespace App\Filament\Agents\Resources\Sales\Pages;

use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Filament\Agents\Resources\Sales\SaleResource;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_observations')
                ->label('Agregar Observaciones')
                ->icon('heroicon-s-hand-raised')
                ->color('warning')
                ->requiresConfirmation()
                ->requiresConfirmation()
                ->modalHeading('OBSERVACIONES DEL AGENTE')
                ->modalIcon('heroicon-s-hand-raised')
                ->form([
                    Textarea::make('description')
                        ->label('Observaciones')
                        ->rows(5)
                ])
                ->action(function (Sale $record, array $data) {

                    try {

                        $record->observations = $data['description'];
                        $record->save();

                        Notification::make()
                            ->body('Las observaciones fueron registradas exitosamente.')
                            ->success()
                            ->send();
                            
                    } catch (\Throwable $th) {
                        LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                        Notification::make()
                            ->title('ERROR')
                            ->body($th->getMessage())
                            ->icon('heroicon-s-x-circle')
                            ->iconColor('danger')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}