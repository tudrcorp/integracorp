<?php

namespace App\Filament\Operations\Resources\TelemedicinePatients\RelationManagers;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Models\TelemedicineCase;
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
                    ->color(function (string $state): string {
                        return match ($state) {
                            'NO URGENTE' => 'no-urgente',
                            'ESTANDAR' => 'estandar',
                            'URGENCIA' => 'urgencia',
                            'EMERGENCIA' => 'emergencia',
                            'CRITICO' => 'critico',
                        };
                    })
                    ->icon(function (string $state): string {
                        return match ($state) {
                            'NO URGENTE' => 'healthicons-f-health',
                            'ESTANDAR' => 'healthicons-f-health',
                            'URGENCIA' => 'healthicons-f-health',
                            'EMERGENCIA' => 'heroicon-c-shield-exclamation',
                            'CRITICO' => 'heroicon-c-shield-exclamation',
                        };
                    })
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
            ->recordClasses(function ($record): array {
                $name = $record->priority?->name;
                $classes = match ($name) {
                    'No Urgente' => 'bg-[#005ca9]/10 dark:bg-[#005ca9]/20 border-l-4 border-[#005ca9]',
                    'Estándar' => 'bg-[#02976d]/10 dark:bg-[#02976d]/20 border-l-4 border-[#02976d]',
                    'Urgencia' => 'bg-[#eab527]/10 dark:bg-[#eab527]/20 border-l-4 border-[#eab527]',
                    'Emergencia' => 'bg-[#f17f29]/10 dark:bg-[#f17f29]/20 border-l-4 border-[#f17f29]',
                    'Critico' => 'bg-[#e4003b]/10 dark:bg-[#e4003b]/20 border-l-4 border-[#e4003b]',
                    default => 'border-l-4 border-gray-200 dark:border-gray-700',
                };

                return [$classes];
            })
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
