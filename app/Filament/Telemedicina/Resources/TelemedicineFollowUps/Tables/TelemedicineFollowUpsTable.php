<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineFollowUps\Tables;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Width;
use App\Models\TelemedicineFollowUp;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;

class TelemedicineFollowUpsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Lista de Seguimientos por número de casos')
            ->description('...')
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
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha de creación')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('next_follow_up')
                    ->label('Proxima Seguimiento')
                    ->searchable(),
                TextColumn::make('hour')
                    ->label('Hora de Seguimiento')
                    ->time()
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->label('Fecha de actualización')
                    ->description(fn(TelemedicineFollowUp $record): string => $record->updated_at->diffForHumans())
                    ->dateTime()
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
                            ->label('1.- ¿COMO SE SIENTE EL DIA DE HOY?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->cuestion_1)
                            ->required(),
                        Textarea::make('cuestion_2')
                            ->label('2.- ¿Cómo HA RESPONDIDO AL TRATAMIENTO INDICADO?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->cuestion_2)
                            ->required(),
                        Textarea::make('cuestion_3')
                            ->label('3. ¿SIENTE QUE HAN MEJORADO LOS SÍNTOMAS?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->cuestion_3)
                            ->required(),
                        Textarea::make('cuestion_4')
                            ->label('4. ¿SE REALIZO LOS ESTUDIOS SOLICITADOS?')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->cuestion_4)
                            ->required(),
                        Textarea::make('cuestion_5')
                            ->label('5. EN VISTA DE QUE SUS RESULTADOS DE LABORATORIO ESTÁN ALTERADOS, SE MODIFICAN LAS INDICACIONES MEDICAS.')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->cuestion_5)
                            ->required(),
                    ]),
            ])
            ->striped();
    }
}