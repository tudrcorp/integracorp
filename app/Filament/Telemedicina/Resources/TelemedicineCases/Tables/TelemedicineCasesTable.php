<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\TelemedicineCase;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use App\Models\TelemedicinePatient;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Models\TelemedicineHistoryPatient;

class TelemedicineCasesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Casos de Telemedicina')
            ->description('Listado de casos de Telemedicina, desde aqui puedes ver el detalle del caso registrar y seguimientos')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn($record): string => 'Asignado a Dr(a):' . $record->telemedicineDoctor->full_name)
                    ->sortable(),
                TextColumn::make('patient_age')
                    ->label('Edad')
                    ->searchable(),
                TextColumn::make('patient_sex')
                    ->label('Sexo')
                    ->searchable(),
                TextColumn::make('patient_phone')
                    ->label('Numero de Teléfono')
                    ->searchable(),
                TextColumn::make('patient_address')
                    ->label('Dirección')
                    ->searchable(),
                // TextColumn::make('city.definition')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('state.definition')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('country.name')
                //     ->numeric()
                //     ->sortable(),
                TextColumn::make('assigned_by')
                ->label('Asignado por:')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO'          => 'primary',
                            'EN SEGUIMIENTO'    => 'warning',
                            'ALTA MEDICA'       => 'success',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO'          => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO'    => 'healthicons-f-i-note-action',
                            'ALTA MEDICA'       => 'healthicons-f-i-documents-accepted',
                        };
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    // ->color(function (string $state): string {
                    //     return match ($state) {
                    //         'ASIGNADO'          => 'primary',
                    //         'EN SEGUIMIENTO'    => 'warning',
                    //         'ALTA MEDICA'       => 'success',
                    //     };
                    // })
                    // ->icon(function (string $state): string {
                    //     return match ($state) {
                    //         'ASIGNADO'          => 'healthicons-f-i-note-action',
                    //         'EN SEGUIMIENTO'    => 'healthicons-f-i-note-action',
                    //         'ALTA MEDICA'       => 'healthicons-f-i-documents-accepted',
                    //     };
                    // })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn(TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
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
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->color('primary')
                        ->label('Ver Detalle'),
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

                            return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        })
                        ->hidden(function (TelemedicineCase $record) {
                            return $record->status != 'EN SEGUIMIENTO';
                        }),
                ])
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}