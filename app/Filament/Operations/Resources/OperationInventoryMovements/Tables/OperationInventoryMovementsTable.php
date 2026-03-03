<?php

namespace App\Filament\Operations\Resources\OperationInventoryMovements\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OperationInventoryMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('operation_inventory_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_patient_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_case_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_consultation_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('telemedicine_doctor_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('business_unit_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('business_line_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('unit')
                    ->searchable(),
                TextColumn::make('type')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('status')
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
