<?php

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicineConsultationPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telemedicine_case_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_case_code')
                    ->searchable(),
                TextColumn::make('telemedicine_patient_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_doctor_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_priority_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_service_list_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('code_reference')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('assigned_by')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('feedbackOne')
                    ->boolean(),
                TextColumn::make('duration')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('priorityMonitoring')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('pa')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fc')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fr')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('temp')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('saturacion')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('peso')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estatura')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('imc')
                    ->numeric()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
