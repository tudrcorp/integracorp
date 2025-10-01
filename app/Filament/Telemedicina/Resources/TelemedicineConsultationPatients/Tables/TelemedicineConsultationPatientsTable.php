<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use PhpParser\Node\Stmt\Label;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Filament\Actions\BulkActionGroup;
use Filament\Schemas\Components\Grid;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineConsultationPatient;

class TelemedicineConsultationPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Listado de Telemedicinas por caso')
            ->description('...')
            ->defaultSort('created_at', 'desc')
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
                TextColumn::make('telemedicineServiceList.name')
                    ->label('Servicio')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-check')
                    ->searchable()
                    ->sortable(),
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
                            'ALTA'          => 'success',
                            'MEDIA'         => 'warning',
                            'BAJA'          => 'primary',
                            'EMERGENCIA'    => 'danger',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ALTA'             => 'healthicons-f-health',
                            'MEDIA'            => 'healthicons-f-health',
                            'BAJA'             => 'healthicons-f-health',
                            'EMERGENCIA'       => 'heroicon-c-shield-exclamation',
                        };
                    })
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
                        ->modalWidth(Width::Medium)
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