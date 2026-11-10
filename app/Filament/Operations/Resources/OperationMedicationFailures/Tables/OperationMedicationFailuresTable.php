<?php

namespace App\Filament\Operations\Resources\OperationMedicationFailures\Tables;

use App\Filament\Operations\Resources\OperationMedicationFailures\Actions\MedicationFailureActions;
use App\Models\OperationMedicationFailure;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OperationMedicationFailuresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Fallas de medicamentos')
            ->description('Medicamentos recetados que el inventario no pudo cubrir. La empresa debe adquirirlos.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('medicine_name')
                    ->label('Medicamento')
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (OperationMedicationFailure $record): ?string => $record->indications)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationMedicationFailure::reasonLabel($state))
                    ->color('warning')
                    ->wrap(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('case_code')
                    ->label('Caso')
                    ->badge()
                    ->color('info')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('reported_by_name')
                    ->label('Reportado por')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => OperationMedicationFailure::statusLabel($state))
                    ->color(fn (?string $state): string => OperationMedicationFailure::statusColor($state))
                    ->sortable(),
                TextColumn::make('resolved_at')
                    ->label('Cerrada')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationMedicationFailure $record): ?string => $record->resolved_by_name)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(OperationMedicationFailure::statusOptions())
                    ->default(OperationMedicationFailure::STATUS_PENDING_PURCHASE),
                SelectFilter::make('reason')
                    ->label('Motivo')
                    ->options(OperationMedicationFailure::reasonOptions()),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
                ActionGroup::make([
                    MedicationFailureActions::markAcquired(),
                    MedicationFailureActions::discard(),
                ]),
            ])
            ->emptyStateHeading('No hay fallas de medicamentos')
            ->emptyStateDescription('Se registran cuando, en «Gestionar servicio», un medicamento no cubierto se marca con «Generar cotización».');
    }
}
