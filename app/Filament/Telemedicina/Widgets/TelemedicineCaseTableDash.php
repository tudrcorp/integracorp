<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\User;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Widgets\TableWidget;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicineFollowUp;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use App\Models\TelemedicineHistoryPatient;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineCase as TelemedicineCase;

class TelemedicineCaseTableDash extends TableWidget
{

    protected int | string | array $columnSpan = 'full';
    
    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Pacientes Asignados')
            ->description('Lista de pacientes asignados para la consulta')
            ->query(fn (): Builder => TelemedicineCase::query()->where('telemedicine_doctor_id', Auth::user()->doctor_id)->where('status', '!=', 'ALTA MEDICA'))
            ->columns([
                TextColumn::make('code')
                    ->label('Nro. de Caso')
                    ->alignCenter()
                    ->badge()
                    ->icon('healthicons-f-health-literacy')
                    ->color('success')
                    ->searchable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->badge()
                    ->icon('healthicons-f-boy-1015y')
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->description(fn ($record): string => $record->patient_sex)
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label('Número de Teléfono')
                    ->iconColor('primary')
                    ->icon('heroicon-s-phone')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Asignación')
                    ->badge()
                    ->icon('heroicon-s-calendar')
                    ->color('primary')
                    ->date(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->icon('heroicon-s-check-circle')
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('priority.name')
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
                TextColumn::make('updated_at')
                    ->label('Ultima Actualización')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                    
            ])
            ->filters([
                //
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                ActionGroup::make([

                    //...Actions History
                    Action::make('view_history')
                        ->label('Historia Clínica')
                        ->icon('heroicon-s-book-open')
                        ->color('primary')
                        ->action(function (TelemedicineCase $record) {
                                // dd($record);
                            $history = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->first();

                            // dd($record, $history, TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first());
    
                            if(isset($history)) {
                                return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.view', ['record' => $history->id]);
                            } else {
                                //Si no tiene historia, redirigir a crear historia
                                session()->put('patient', TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first());
                                return redirect()->route('filament.telemedicina.resources.telemedicine-history-patients.create', ['record' => $record->telemedicine_patient_id]);
                            }
                        }),
                    
                    //...Actions consultation
                    Action::make('consultation')
                        ->label('Consulta Inicial')
                        ->icon('healthicons-f-call-centre')
                        ->color('success')
                        ->disabled(function (TelemedicineCase $record) {
                            $case = TelemedicineConsultationPatient::where('telemedicine_case_code', $record->code)->exists();
                            // dd($record->status);
                            if($case && $record->status == 'ATENDIDO') {
                                return true;
                            }
                            return false;
                        })
                        ->action(function (TelemedicineCase $record) {
    
                            $case        = TelemedicineCase::where('code', $record->code)->first();
                            $patient     = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();
                            $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->exists();
    
                            session()->forget('case');
                            session()->forget('patient');
                            // session()->forget('exit_record');
                            session()->forget('redCode');
    
                            //Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                            session(['case' => $case]);
                            session(['patient' => $patient]);
                            // session(['exit_record' => $exit_record]);
    
                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                            
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'ASIGNADO';
                        }),
                          
                    //...Actions follow up
                    Action::make('add_follow_up')
                        ->label('Hacer Seguimiento')
                        ->icon('healthicons-f-health-literacy')
                        ->color('success')
                        ->action(function (TelemedicineCase $record) {
                            $case        = TelemedicineCase::where('code', $record->code)->first();
                            $patient     = TelemedicinePatient::where('id', $record->telemedicine_patient_id)->first();
                            $exit_record = TelemedicineHistoryPatient::where('telemedicine_patient_id', $record->telemedicine_patient_id)->exists();

                            session()->forget('case');
                            session()->forget('patient');
                            session()->forget('exit_record');

                            //Almacenamos en la variable de sesion del usuario la informacion del caso y del paciente
                            session(['case' => $case]);
                            session(['patient' => $patient]);
                            session(['exit_record' => $exit_record]);

                            Log::info(session()->get('case'));
                            Log::info(session()->get('patient'));
                            Log::info(session()->get('exit_record'));

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'EN SEGUIMIENTO';
                        }),

                    Action::make('view_last')
                        ->label('Ver ultimo Seguimiento')
                        ->icon('heroicon-s-eye')
                        ->color('')
                        ->action(function (TelemedicineCase $record) {

                        $last = TelemedicineConsultationPatient::where('telemedicine_case_id', $record->id)->latest()->first();

                        return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.view', ['record' => $last->id]);

                        })
                        ->hidden(function (TelemedicineCase $record) {
                            $last = TelemedicineConsultationPatient::where('telemedicine_case_id', $record->id)->latest()->first();
                            if($last == null) {
                                return true;
                            }
                            return false;
                        }),
                        
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    //
                ]),
            ])
            ->poll('5s');
    }
}