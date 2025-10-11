<?php

namespace App\Livewire;

use Livewire\Component;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Filament\Actions\BulkActionGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\ColumnGroup;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Contracts\HasSchemas;
use App\Models\TelemedicineConsultationPatient;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;

class TableTelemedicineConsultationPatient extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;
    use InteractsWithSchemas;

    public $records = [];
    
    public function mount($records): void
    {
        $this->records = $records->toArray();
        // dd($this->records);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(TelemedicineConsultationPatient::query()
                ->where('telemedicine_case_id', $this->records[0]['telemedicine_case_id'])
                ->where('telemedicine_patient_id', $this->records[0]['telemedicine_patient_id'])
            )
            ->columns([

                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineConsultationPatient $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                
                TextColumn::make('telemedicineServiceList.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-check')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (TelemedicineConsultationPatient $record) {
                        return $record->status == 'EN SEGUIMIENTO' ? 'warning' : 'success';
                    }),

                TextColumn::make('telemedicinePriority.name')
                    ->label('Prioridad')
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
                    ->searchable(),
                ColumnGroup::make('LABORATORIOS Y ESTUDIOS CUBIERTOS', [
                    TextColumn::make('labs')
                        ->label('Laboratorio')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->labs ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->labs ? $record->labs : 'N/A';
                        })
                        ->searchable(),
                    TextColumn::make('studies')
                        ->label('Estudios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? $record->studies : 'N/A';
                        })
                        ->searchable(),
                    TextColumn::make('consult_specialist')
                        ->label('Consultas de Especialistas')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? $record->consult_specialist : 'N/A';
                        })
                        ->searchable(),
                ]),

                ColumnGroup::make('LABORATORIOS Y ESTUDIOS NO CUBIERTOS', [
                    TextColumn::make('other_labs')
                        ->label('Otros Laboratorios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->other_labs ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->other_labs ? $record->labs : 'N/A';
                        })
                        ->searchable(),

                    TextColumn::make('other_studies')
                        ->label('Otros Estudios')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->studies ? $record->studies : 'N/A';
                        })
                        ->searchable(),

                    TextColumn::make('other_specialist')
                        ->label('Otros Especialistas')
                        ->alignCenter()
                        ->wrap()
                        ->badge()
                        ->color(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? 'success' : 'gray';
                        })
                        ->default(function (TelemedicineConsultationPatient $record) {
                            return $record->consult_specialist ? $record->consult_specialist : 'N/A';
                        })
                        ->searchable(),
                ]),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ]);
    }

    public function render(): View
    {
        return view('livewire.table-telemedicine-consultation-patient');
    }
}