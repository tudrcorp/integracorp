<?php

namespace App\Filament\Telemedicina\Widgets;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Widgets\TableWidget;
use App\Models\TelemedicinePatient;
use Filament\Support\Enums\TextSize;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Illuminate\Support\Facades\Crypt;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Stack;
use Illuminate\Database\Eloquent\Builder;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineCase as TelemedicineCase;

class TelemedicineCaseTableDash extends TableWidget
{

    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->heading('Pacientes Asignados')
            ->description('Lista de pacientes asignados para la consulta')
            ->query(fn (): Builder => TelemedicineCase::query()->where('telemedicine_doctor_id', Auth::user()->doctor_id))
            ->columns([
                TextColumn::make('code')
                    ->label('Nro. de Caso')
                    ->alignCenter()
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Motivo de Consulta')
                    ->color('success')
                    ->icon('healthicons-f-contact-support')
                    ->size(TextSize::Small)
                    ->wrap()
                    ->limit(100, end: '...')
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column contents exceeds the length limit.
                        return $state;
                    })
                    ->searchable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->alignCenter()
                    ->badge()
                    ->icon('healthicons-f-boy-1015y')
                    ->color('success')
                    ->searchable(),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->patient_sex)
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label('Número de Teléfono')
                    ->iconColor('primary')
                    ->icon('heroicon-s-phone')
                    ->searchable(),
                TextColumn::make('patient_address')
                    ->label('Dirección')
                    ->iconColor('primary')
                    ->icon('heroicon-s-map-pin')
                    ->color('success')
                    ->size(TextSize::Small)
                    ->wrap()
                    ->limit(100, end: '...')
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();

                        if (strlen($state) <= $column->getCharacterLimit()) {
                            return null;
                        }

                        // Only render the tooltip if the column contents exceeds the length limit.
                        return $state;
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Asignación')
                    ->badge()
                    ->icon('heroicon-s-calendar')
                    ->color('warning')
                    ->date(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->icon('heroicon-s-check-circle')
                    ->color(function ($record) {
                        return $record->status == 'ASIGNADO' ? 'warning' : 'success';
                    })
                    ->searchable(),
                    
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                Action::make('view_history')
                    ->label('Historia Clínica')
                    ->icon('healthicons-f-health-worker-form')
                    ->color('info')
                    ->button()
                    ->action(function (TelemedicineCase $record) {
                        
                        $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->first(); // dd($record->telemedicine_patient_id);
                        
                        if(isset($exit_record)) {
                            return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.view', ['record' => $exit_record->id]);
                        
                        }else {
                            return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.create', ['record' => $record->telemedicine_patient_id]);
                        }
                        
                    }),
                Action::make('consultation')
                    ->label('Consulta')
                    ->icon('healthicons-f-call-centre')
                    ->color('success')
                    ->button()
                    ->action(function (TelemedicineCase $record) {

                        $case = TelemedicineCase::where('code', $record->code)->first();
                        $patient = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();

                        //Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                        session(['case' => $case]);
                        session(['patient' => $patient]);

                        return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create');
                        
                    }),
                
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->poll('5s');
    }
}