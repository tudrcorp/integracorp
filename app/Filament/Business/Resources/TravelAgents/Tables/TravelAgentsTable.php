<?php

namespace App\Filament\Business\Resources\TravelAgents\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TravelAgentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('cargo')
                    ->searchable(),
                TextColumn::make('fechaNacimiento')
                    ->searchable(),
                TextColumn::make('nameSecundario')
                    ->searchable(),
                TextColumn::make('emailSecundario')
                    ->searchable(),
                TextColumn::make('phoneSecundario')
                    ->searchable(),
                TextColumn::make('cargoSecundario')
                    ->searchable(),
                TextColumn::make('fechaNacimientoSecundario')
                    ->searchable(),
                TextColumn::make('local_beneficiary_name')
                    ->searchable(),
                TextColumn::make('local_beneficiary_rif')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_number')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_bank')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_type')
                    ->searchable(),
                TextColumn::make('local_beneficiary_phone_pm')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_number_mon_inter')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_bank_mon_inter')
                    ->searchable(),
                TextColumn::make('local_beneficiary_account_type_mon_inter')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_name')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_ci_rif')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_account_number')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_account_bank')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_account_type')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_route')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_zelle')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_ach')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_swift')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_aba')
                    ->searchable(),
                TextColumn::make('extra_beneficiary_address')
                    ->searchable(),
                TextColumn::make('logo')
                    ->searchable(),
                TextColumn::make('createdBy')
                    ->searchable(),
                TextColumn::make('updatedBy')
                    ->searchable(),
                TextColumn::make('travel_agency_id')
                    ->numeric()
                    ->sortable(),
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
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
