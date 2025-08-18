<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_doctor_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('code_reference')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->searchable(),
                TextColumn::make('type_service')
                    ->searchable(),
                TextColumn::make('reason_consultation')
                    ->searchable(),
                TextColumn::make('actual_phatology')
                    ->searchable(),
                TextColumn::make('vs_pa')
                    ->searchable(),
                TextColumn::make('vs_fc')
                    ->searchable(),
                TextColumn::make('vs_fr')
                    ->searchable(),
                TextColumn::make('vs_temp')
                    ->searchable(),
                TextColumn::make('vs_sat')
                    ->searchable(),
                TextColumn::make('vs_weight')
                    ->searchable(),
                TextColumn::make('labs')
                    ->wrap()
                    ->badge()
                    ->searchable(),
                TextColumn::make('studies')
                    ->wrap()
                    ->badge()
                    ->searchable(),
                TextColumn::make('diagnostic_impression')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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