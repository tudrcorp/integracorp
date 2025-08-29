<?php

namespace App\Filament\Resources\TelemedicineConsultationPatients\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineConsultationPatient;

class TelemedicineConsultationPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telemedicine_case_code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('code_reference')
                    ->label('Referencia')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn ($record): string => 'Atenido por: Dr(a):'.$record->telemedicineDoctor->full_name)
                    ->sortable(),
                TextColumn::make('nro_identificacion')
                    ->label('Número de Identificación')
                    ->prefix('V-')
                    ->alignCenter()
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->searchable(),
                
                TextColumn::make('labs')
                    ->label('Laboratorio')
                    ->alignCenter()
                    ->wrap()
                    ->badge()
                    ->color('success')
                    ->description(fn (TelemedicineConsultationPatient $record): string => 'Otro: '. $record->other_labs)
                    ->searchable(),
                TextColumn::make('studies')
                    ->label('Estudios')
                    ->alignCenter()
                    ->wrap()
                    ->badge()
                    ->color('success')
                    ->description(fn(TelemedicineConsultationPatient $record): string => 'Otro: ' . $record->other_studies)
                    ->searchable(),
                TextColumn::make('consult_specialist')
                    ->label('Consultas de Especialistas')
                    ->alignCenter()
                    ->wrap()
                    ->badge()
                    ->color('success')
                    ->description(fn(TelemedicineConsultationPatient $record): string => 'Otro: ' . $record->other_specialist)
                    ->searchable(),
                
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn (TelemedicineConsultationPatient $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('information_case')
                        ->label('Información')
                        ->icon('fontisto-info')
                        ->color('primary')
                        ->modalHeading('Información de la consulta')
                        ->modalIcon('fontisto-info')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalSubmitAction(false)
                        ->form([
                            Fieldset::make('Valores')
                                ->schema([
                                    TextInput::make('vs_pa')
                                        ->label('PA')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_pa),
                                    TextInput::make('vs_fc')
                                        ->label('FC')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_fc),
                                    TextInput::make('vs_fr')
                                        ->label('FR')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_fr),
                                    TextInput::make('vs_temp')
                                        ->label('TEMP')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_temp),
                                    TextInput::make('vs_sat')
                                        ->label('SAT')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_sat),
                                    TextInput::make('vs_weight')
                                        ->label('W')
                                        ->disabled()
                                        ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_weight),
                                ])->columns(3),
                            Textarea::make('reason_consultation')
                                ->label('Motivo de consulta:')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->reason_consultation),
                            Textarea::make('actual_phatology')
                                ->label('Patología actual:')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->actual_phatology),
                            Textarea::make('diagnostic_impression')
                                ->label('Impresión Diagnóstica:')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->diagnostic_impression),
                        ]),
                    Action::make('follow_up')
                        ->label('Hacer Seguimiento')
                        ->icon('healthicons-f-i-note-action')
                        ->color('success')
                        ->modalHeading('Seguimiento de la consulta')
                        ->modalIcon('healthicons-f-i-note-action')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalSubmitAction(false)
                        ->form([
                            Textarea::make('reason')
                                ->label('Descripción:')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->patient_address)
                                ->required(),
                        ]),
                    
                ])

            // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}