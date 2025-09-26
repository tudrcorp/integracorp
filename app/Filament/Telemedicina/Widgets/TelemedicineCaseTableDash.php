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
            ->query(fn (): Builder => TelemedicineCase::query()->where('telemedicine_doctor_id', Auth::user()->doctor_id))
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
                ActionGroup::make([
                    
                    //...Actions consultation
                    Action::make('consultation')
                        ->label('Telemedicína')
                        ->icon('healthicons-f-call-centre')
                        ->color('primary')
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
                            return $record->status != 'ASIGNADO';
                        }),
                        
                    //...Actions last follow up
                    Action::make('view_last')
                        ->label('Ver ultimo Seguimiento')
                        ->icon('heroicon-s-eye')
                        ->color('')
                        ->action(function (TelemedicineCase $record) {
    
                            $follow_up = TelemedicineFollowUp::where('code', $record->code)->latest()->first();
    
                            return redirect()->route('filament.telemedicina.resources.telemedicine-follow-ups.view', ['record' => $follow_up->id]);
    
                            
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            $follow_up = TelemedicineFollowUp::where('code', $record->code)->latest()->first();
                            if(isset($follow_up)) {
                                return false;
                            }
                            return true;
                        }),
                        
                    //...Actions follow up
                    Action::make('add_follow_up')
                        ->label('Hacer Seguimiento')
                        ->icon('healthicons-f-health-literacy')
                        ->color('success')
                        ->action(function (TelemedicineCase $record) {
    
                            $follow_up_count = TelemedicineFollowUp::where('code', $record->code)->get();
    
                            if($follow_up_count->count() == 1) {
                                $id = $follow_up_count->where('code', $record->code)->first()->id;
                                return redirect()->route('filament.telemedicina.resources.telemedicine-follow-ups.edit', ['record' => $id]);
                            }
                            
                            return redirect()->route('filament.telemedicina.resources.telemedicine-follow-ups.create', ['record' => $record->id]);
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'EN SEGUIMIENTO';
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