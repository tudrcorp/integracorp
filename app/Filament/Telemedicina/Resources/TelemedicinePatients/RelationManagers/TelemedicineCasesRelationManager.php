<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use App\Models\TelemedicineCase;
use Filament\Actions\ViewAction;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;

class TelemedicineCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineCases';

    protected static ?string $title = 'Histórico de Casos';

    protected static string|BackedEnum|null $icon = 'healthicons-f-health-literacy';

    public function table(Table $table): Table
    {
        return $table
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
                ViewAction::make()
                    ->icon('heroicon-s-eye')
                    ->label('Ver Detalle')
                    ->color('primary')
                    ->url(function (TelemedicineCase $record) {
                        return TelemedicineCaseResource::getUrl('view', ['record' => $record->getKey()]);
                    })
            ]);
            
    }
}