<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\RelationManagers;

use Dom\Text;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Log;
use App\Models\TelemedicineFollowUp;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\TelemedicineConsultationPatientResource;
use BackedEnum;

class TelemedicineFollowUpsRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineFollowUps';

    protected static ?string $title = 'Seguimientos del Caso';

    protected static string|BackedEnum|null $icon = 'healthicons-f-i-note-action';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telemedicineCase.code')
                    ->label('Número de caso')
                    ->icon('heroicon-s-tag')
                    ->badge()                    
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicine_consultation_patient_id')
                    ->label('Número de consulta')
                    ->badge()
                    ->color('primary')
                    ->prefix('TEL-CON-000')
                    ->sortable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->searchable(),
                TextColumn::make('telemedicineDoctor.full_name')
                    ->label('Doctor(a)')
                    ->sortable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('next_follow_up')
                    ->label('Proximo seguimiento')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Ver Detalles')
                    ->icon('fontisto-info')
                    ->color('primary')
                    ->modalHeading('Cuestionario de seguimiento')
                    ->modalIcon('fontisto-info')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalSubmitAction(false)
                    ->button()
                    ->form([
                        Textarea::make('cuestion_1')
                            ->label('2.- ¿Cómo HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn(TelemedicineFollowUp $record) => $record->cuestion_1)
                            ->required(),
                        Textarea::make('cuestion_2')
                            ->label('2.- ¿Cómo HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn(TelemedicineFollowUp $record) => $record->cuestion_2)
                            ->required(),
                        Textarea::make('cuestion_3')
                            ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn(TelemedicineFollowUp $record) => $record->cuestion_3)
                            ->required(),
                        Textarea::make('cuestion_4')
                            ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn(TelemedicineFollowUp $record) => $record->cuestion_4)
                            ->required(),
                        Textarea::make('cuestion_5')
                            ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                            ->disabled()
                            ->autoSize()
                            ->default(fn(TelemedicineFollowUp $record) => $record->cuestion_5)
                            ->required(),
            ]),
            ])
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}