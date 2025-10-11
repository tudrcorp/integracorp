<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Illuminate\Support\Collection;
use App\Models\PathologicalHistory;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use App\Models\NoPathologicalHistory;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Hidden;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class TablePathologicalHistory extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;
    use InteractsWithSchemas;

    public $records;

    public function mount($records): void
    {
        $this->records = $records->toArray();
        // dd($this->records);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Antecedentes Patológicos')
            ->description('Listas de Antecedentes Patológicos registrados en la Historia de la Consulta, ordenas de forma cronológica. Esta acción solo le permite asociar un antecedente')
            ->query(fn (): Builder => PathologicalHistory::query()->where('telemedicine_history_patient_id', $this->records[0]['telemedicine_history_patient_id']))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    ->description(fn(PathologicalHistory $record): string => $record->updated_at->diffForHumans())
                    ->color('primary')
                    ->icon('heroicon-s-calendar')
                    ->searchable(),
                TextColumn::make('observations')
                    ->label('Antecedente')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Registrado por:')
                    ->badge()
                    ->searchable(),
            ])
            ->headerActions([
                CreateAction::make()
                    ->icon('heroicon-s-plus')
                    ->label('Nuevo Antecedente')
                    ->modalHeading('Nuevo Antecedente Patológico')
                    ->form([
                        Textarea::make('observations')
                            ->autosize()
                            ->label('Antecedente')
                            ->required(),
                        Hidden::make('created_by')->default(Auth::user()->name),
                    ])
                    ->action(function (array $data): void {
                        try {
                            $PathologicalHistory = PathologicalHistory::create([
                                'telemedicine_history_patient_id' => $this->records[0]['telemedicine_history_patient_id'],
                                'telemedicine_patient_id' => $this->records[0]['telemedicine_patient_id'],
                                'observations' => $data['observations'],
                                'created_by' => $data['created_by'],
                            ]);
                        } catch (\Throwable $th) {
                            dd($th);
                        }
                    })
            ])
            ->recordActions([
                Action::make('associate')
                ->label('Asociar')
                ->icon('heroicon-c-arrow-left-circle')
                ->color('estandar')
                ->requiresConfirmation()
                ->closeModalByClickingAway(true)
                ->action(function (PathologicalHistory $record): void {

                    //...Limpio la variable de session
                    session()->forget('patologicalHistorySelected');

                    //...Guardo en la variable de session el antecedente
                    session()->put('patologicalHistorySelected', $record->observations);

                    // 2. Notificación opcional
                    Notification::make()
                        ->title('Información generada')
                        ->success()
                        ->send();

                    // $this->mount();
                }),
            ])
            // ->toolbarActions([
            //     BulkActionGroup::make([
            //         BulkAction::make('associate')
            //         ->label('Asociar Antecedente')
            //         ->icon('heroicon-s-check-circle')
            //         ->color('success')
            //         ->requiresConfirmation()
            //         ->closeModalByClickingAway(true)
            //         ->action(function (Collection $records): void {

            //             $record = $records->first();
            //             //...Limpio la variable de session
            //             session()->forget('patologicalHistorySelected');
                        
            //             //...Guardo en la variable de session el antecedente
            //             session()->put('patologicalHistorySelected', $record->observations);

            //             // 2. Notificación opcional
            //             Notification::make()
            //                 ->title('Información generada')
            //                 ->success()
            //                 ->send();

            //         }),
            //     ]),
            // ])
            ->striped();
    }

    public function render(): View
    {
        return view('livewire.table-pathological-history');
    }
}