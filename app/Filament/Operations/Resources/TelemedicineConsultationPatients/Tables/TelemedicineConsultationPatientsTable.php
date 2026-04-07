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
            ->modifyQueryUsing(fn ($query) => $query->with('telemedicinePriority'))
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
            ->recordClasses(function ($record): array {
                /** Paleta alineada con AppServiceProvider (no-urgente, estandar, urgencia, emergencia, critico) */
                $name = $record->telemedicinePriority?->name;
                $classes = match ($name) {
                    'NO URGENTE', 'No Urgente' => 'bg-[#005ca9]/10 dark:bg-[#005ca9]/25 border-l-4 border-[#005ca9]',
                    'ESTANDAR', 'Estándar' => 'bg-[#02976d]/10 dark:bg-[#02976d]/25 border-l-4 border-[#02976d]',
                    'URGENCIA', 'Urgencia' => 'bg-[#eab527]/10 dark:bg-[#eab527]/25 border-l-4 border-[#eab527]',
                    'EMERGENCIA', 'Emergencia' => 'bg-[#f17f29]/10 dark:bg-[#f17f29]/25 border-l-4 border-[#f17f29]',
                    'CRITICO', 'Critico' => 'bg-[#e4003b]/10 dark:bg-[#e4003b]/25 border-l-4 border-[#e4003b]',
                    default => 'border-l-4 border-gray-200 bg-gray-50/50 dark:border-gray-600 dark:bg-gray-950/20',
                };

                return [$classes];
            })
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
