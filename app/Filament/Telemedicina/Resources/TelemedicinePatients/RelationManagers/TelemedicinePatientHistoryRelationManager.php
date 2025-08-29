<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;

class TelemedicinePatientHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicinePatientHistory';

    protected static ?string $title = 'Historia';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ColumnGroup::make('DATOS DEL PACIENTE')
                    ->columns([
                        TextColumn::make('code')
                            ->label('Nro. de historia')
                            ->badge()
                            ->color('success')
                            ->searchable(),
                        TextColumn::make('code_patient')
                            ->label('Codigo de paciente')
                            ->badge()
                            ->color('success')
                            ->searchable(),
                        TextColumn::make('telemedicinePatient.full_name')
                            ->label('Paciente')
                            ->badge()
                            ->color('success')
                            ->numeric()
                            ->sortable(),
                        TextColumn::make('history_date')
                            ->label('Fecha de historia')
                            ->date()
                            ->searchable(),
                        TextColumn::make('weight')
                            ->label('Peso (kg)')
                            ->searchable(),
                        TextColumn::make('height')
                            ->label('Altura (cm)')
                            ->searchable(),
                        
                    ]),

                ColumnGroup::make('ANTECEDENTES PERSONALES Y FAMILIARES')
                    ->columns([
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
                        
                    ]),

                ColumnGroup::make('ANTECEDENTES PERSONALES Y PATOLÓGICOS ')
                    ->columns([
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
                    
                    ]),

                ColumnGroup::make('ANTECEDENTES NO PATOLÓGICOS')
                    ->columns([
                        IconColumn::make('alcohol')
                            ->boolean(),
                        IconColumn::make('drogas')
                            ->boolean(),
                        IconColumn::make('vacunas_recientes')
                            ->boolean(),
                        IconColumn::make('transfusiones_sanguineas')
                            ->boolean(),
                    ]),

            ColumnGroup::make('ANTECEDENTES GINECÓLOGOS')
                ->columns([
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
                    
                ]),
                TextColumn::make('allergies')
                    ->searchable(),
                TextColumn::make('history_surgical')
                    ->searchable(),
                TextColumn::make('medications_supplements')
                    ->searchable(),
                TextColumn::make('observations_ginecologica')
                    ->searchable(),
                TextColumn::make('observations_allergies')
                    ->searchable(),
                TextColumn::make('observations_medication')
                    ->searchable(),
                TextColumn::make('observations_personal')
                    ->searchable(),
                TextColumn::make('observations_not_pathological')
                    ->searchable(),
                TextColumn::make('observations_pathological')
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
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}