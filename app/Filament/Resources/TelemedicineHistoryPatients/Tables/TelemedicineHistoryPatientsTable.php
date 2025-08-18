<?php

namespace App\Filament\Resources\TelemedicineHistoryPatients\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicineHistoryPatientsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('telemedicine_patient_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cod_history')
                    ->searchable(),
                TextColumn::make('user_id')
                    ->searchable(),
                TextColumn::make('cod_patient')
                    ->searchable(),
                TextColumn::make('history_date')
                    ->searchable(),
                TextColumn::make('weight')
                    ->searchable(),
                TextColumn::make('height')
                    ->searchable(),
                TextColumn::make('reason')
                    ->searchable(),
                TextColumn::make('current_illness')
                    ->searchable(),
                TextColumn::make('strain')
                    ->searchable(),
                TextColumn::make('temperature')
                    ->searchable(),
                TextColumn::make('breaths')
                    ->searchable(),
                TextColumn::make('pulse')
                    ->searchable(),
                TextColumn::make('saturation')
                    ->searchable(),
                TextColumn::make('condition')
                    ->searchable(),
                IconColumn::make('cancer')
                    ->boolean(),
                IconColumn::make('diabetes')
                    ->boolean(),
                IconColumn::make('tension_alta')
                    ->boolean(),
                IconColumn::make('cardiacos')
                    ->boolean(),
                IconColumn::make('psiquiatricas')
                    ->boolean(),
                IconColumn::make('alteraciones_coagulacion')
                    ->boolean(),
                IconColumn::make('trombosis_embooleanas')
                    ->boolean(),
                IconColumn::make('tranfusiones_sanguineas')
                    ->boolean(),
                IconColumn::make('COVID19')
                    ->boolean(),
                IconColumn::make('hepatitis')
                    ->boolean(),
                IconColumn::make('VIH_SIDA')
                    ->boolean(),
                IconColumn::make('gastritis_ulceras')
                    ->boolean(),
                IconColumn::make('neurologia')
                    ->boolean(),
                IconColumn::make('ansiedad_angustia')
                    ->boolean(),
                IconColumn::make('tiroides')
                    ->boolean(),
                IconColumn::make('lupus')
                    ->boolean(),
                IconColumn::make('enfermedad_autoimmune')
                    ->boolean(),
                IconColumn::make('diabetes_mellitus')
                    ->boolean(),
                IconColumn::make('presion_arterial_alta')
                    ->boolean(),
                IconColumn::make('tiene_cateter_venoso')
                    ->boolean(),
                IconColumn::make('fracturas')
                    ->boolean(),
                IconColumn::make('trombosis_venosa')
                    ->boolean(),
                IconColumn::make('embooleania_pulmonar')
                    ->boolean(),
                IconColumn::make('varices_piernas')
                    ->boolean(),
                IconColumn::make('insuficiencia_arterial')
                    ->boolean(),
                IconColumn::make('coagulacion_anormal')
                    ->boolean(),
                IconColumn::make('moretones_frecuentes')
                    ->boolean(),
                IconColumn::make('sangrado_cirugias_previas')
                    ->boolean(),
                IconColumn::make('sangrado_cepillado_dental')
                    ->boolean(),
                IconColumn::make('alcohol')
                    ->boolean(),
                IconColumn::make('drogas')
                    ->boolean(),
                IconColumn::make('vacunas_recientes')
                    ->boolean(),
                IconColumn::make('transfusiones_sanguineas')
                    ->boolean(),
                TextColumn::make('edad_primera_menstruation')
                    ->searchable(),
                TextColumn::make('fecha_ultima_regla')
                    ->searchable(),
                TextColumn::make('numero_embarazos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('numero_partos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('numero_abortos')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cesareas')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('observations_ginecologica')
                    ->searchable(),
                TextColumn::make('observations_allergies')
                    ->searchable(),
                TextColumn::make('observations_medication')
                    ->searchable(),
                TextColumn::make('observations_back_family')
                    ->searchable(),
                TextColumn::make('observations_diagnosis')
                    ->searchable(),
                TextColumn::make('observations_not_pathological')
                    ->searchable(),
                TextColumn::make('created_by')
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
