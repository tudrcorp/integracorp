<?php

namespace App\Livewire\FilamentTable;

use Livewire\Component;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Illuminate\Contracts\View\View;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ColumnGroup;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;

class TableTelemedicineCases extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;
    use InteractsWithSchemas;

    public $records = [];

    public function mount($records): void
    {
        $this->records = $records->toArray();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TelemedicineCase::query()
                    ->where('telemedicine_patient_id', $this->records[0]['telemedicine_patient_id'])
                    ->latest()
                    ->limit(5) // Devuelve el Builder, no la Collection
            )
            ->columns([

                TextColumn::make('created_at')
                    ->label('Fecha de Asiganción')
                    ->dateTime()
                    // ->description(fn (TelemedicineCase $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('code')
                    ->label('Codigo:')
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')    
                    ->searchable(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Asignado a:')
                    ->prefix('Dr(a): ')
                    ->description(fn (TelemedicineCase $record): string => 'Motivo: '.$record->reason)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad:')
                    ->default(fn (TelemedicineCase $record): string => $record->telemedicine_priority_id == NULL ? 'NO ASIGNADA' : $record->priority->name)
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'No Urgente'  => 'no-urgente',
                            'Estándar'    => 'estandar',
                            'Urgencia'    => 'urgencia',
                            'Emergencia'  => 'emergencia',
                            'Critico'     => 'critico',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'No Urgente'  => 'healthicons-f-health',
                            'Estándar'    => 'healthicons-f-health',
                            'Urgencia'    => 'healthicons-f-health',
                            'Emergencia'  => 'heroicon-c-shield-exclamation',
                            'Critico'     => 'heroicon-c-shield-exclamation',
                        };
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->icon('heroicon-s-check-circle')
                    ->color('warning')
                    ->searchable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('view_details')
                    ->label('Ver Detalles')
                    ->color('primary')
                    ->icon('heroicon-s-eye')
                    ->action(function (TelemedicineCase $record): void {
                        // dd($record);
                        session()->forget('historyCasesToDetails');
                        session()->put('historyCasesToDetails', $record);
                        redirect()->route('filament.telemedicina.resources.telemedicine-cases.view', ['record' => $record->id]);
                    })
                    // ->url(fn (TelemedicineCase $record): string => TelemedicineCaseResource::getUrl('view', ['record' => $record->id])),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }
    
    public function render()
    {
        return view('livewire.filament-table.table-telemedicine-cases');
    }
}