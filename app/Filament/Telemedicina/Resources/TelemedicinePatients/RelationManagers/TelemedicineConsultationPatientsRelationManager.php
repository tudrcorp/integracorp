<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use App\Models\TelemedicineConsultationPatient;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;

class TelemedicineConsultationPatientsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineConsultationPatients';

    protected static ?string $title = 'Consultas Médicas';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function table(Table $table): Table
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
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Atenido por:')
                    ->prefix('Dr(a). ')
                    ->searchable(),
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
                    ->description(fn(TelemedicineConsultationPatient $record): string => 'Otro: ' . $record->other_labs)
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
                    ->description(fn(TelemedicineConsultationPatient $record): string => $record->updated_at->diffForHumans())

                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
            Action::make('information_case')
                ->label('Ver')
                ->button()
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
                                ->prefix('mmHg')
                                ->label('PA')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_pa),
                            TextInput::make('vs_fc')
                                ->prefix('bpm')
                                ->label('FC')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_fc),
                            TextInput::make('vs_fr')
                                ->prefix('bpm')
                                ->label('FR')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_fr),
                            TextInput::make('vs_temp')
                                ->prefix('°C')
                                ->label('TEMP')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_temp),
                            TextInput::make('vs_sat')
                                ->prefix('%')
                                ->label('SAT')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_sat),
                            TextInput::make('vs_weight')
                                ->prefix('kg')
                                ->label('W')
                                ->disabled()
                                ->default(fn(TelemedicineConsultationPatient $record) => $record->vs_weight),
                        ])->columns(3),
                    Textarea::make('reason_consultation')
                        ->label('Motivo de consulta:')
                        ->disabled()
                        ->autoSize()
                        ->default(fn(TelemedicineConsultationPatient $record) => $record->reason_consultation),
                    Textarea::make('actual_phatology')
                        ->label('Patología actual:')
                        ->disabled()
                        ->autoSize()
                        ->default(fn(TelemedicineConsultationPatient $record) => $record->actual_phatology),
                    Textarea::make('diagnostic_impression')
                        ->label('Impresión Diagnóstica:')
                        ->disabled()
                        ->autoSize()
                        ->default(fn(TelemedicineConsultationPatient $record) => $record->diagnostic_impression),
                ])
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}