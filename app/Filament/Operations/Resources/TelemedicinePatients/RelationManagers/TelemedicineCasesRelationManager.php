<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\RelationManagers;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicinePriorityFilamentBadge;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TelemedicineCasesRelationManager extends RelationManager
{
    protected static string $relationship = 'telemedicineCases';

    protected static ?string $title = 'Histórico de Casos';

    protected static string|BackedEnum|null $icon = 'healthicons-f-health-literacy';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                TelemedicineCase::query()
                    ->with(['priority', 'telemedicineDoctor', 'telemedicinePatient'])
                    ->where('telemedicine_patient_id', $this->getOwnerRecord()->getKey())
                    ->where('status', '=', 'ALTA MEDICA')
                    ->orderBy('created_at', 'desc')
            )
            ->heading('Casos de Telemedicina')
            ->description('Listado de casos de Telemedicina, desde aqui puedes ver el detalle del caso registrar y seguimientos')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Numero de Caso')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('telemedicinePatient.full_name')
                    ->label('Paciente')
                    ->description(fn ($record): string => 'Asignado a Dr(a):'.$record->telemedicineDoctor->full_name)
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
                TextColumn::make('assigned_by')
                    ->label('Asignado por:')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'primary',
                            'EN SEGUIMIENTO' => 'warning',
                            'ALTA MEDICA' => 'success',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'ASIGNADO' => 'healthicons-f-i-note-action',
                            'EN SEGUIMIENTO' => 'healthicons-f-i-note-action',
                            'ALTA MEDICA' => 'healthicons-f-i-documents-accepted',
                        };
                    })
                    ->searchable(),
                TextColumn::make('priority.name')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (string $state): string => TelemedicinePriorityFilamentBadge::color($state))
                    ->icon(fn (string $state): string => TelemedicinePriorityFilamentBadge::icon($state))
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha de Registro')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Ultima Actualización')
                    ->dateTime()
                    // ->description(fn (TelemedicineConsultationPatient $record): string => $record->created_at->diffForHumans())
                    ->description(fn (TelemedicineCase $record): string => $record->updated_at->diffForHumans())
                    ->sortable(),
            ])
            ->recordClasses(fn ($record): array => [TelemedicinePriorityFilamentBadge::recordRowClasses($record->priority?->name)])
            ->recordActions([
                Action::make('view_details')
                    ->icon('heroicon-s-eye')
                    ->label('Ver consultas del caso')
                    ->color('primary')
                    ->url(function (TelemedicineCase $record): string {
                        return TelemedicineCaseResource::getUrl('view', [
                            'record' => $record->getKey(),
                        ]).'?relation=consultations&from=patient';
                    }),
            ])
            ->filters([
                //
            ]);
    }
}
