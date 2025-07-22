<?php

namespace App\Filament\General\Resources\Agencies\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AgenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('owner_code')
                    ->searchable(),
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('agency_type_id')
                    ->searchable(),
                TextColumn::make('rif')
                    ->searchable(),
                TextColumn::make('name_corporative')
                    ->searchable(),
                TextColumn::make('ci_responsable')
                    ->searchable(),
                TextColumn::make('address')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('user_instagram')
                    ->searchable(),
                TextColumn::make('country.name')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('state.id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('city.id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->searchable(),
                TextColumn::make('name_contact_2')
                    ->searchable(),
                TextColumn::make('email_contact_2')
                    ->searchable(),
                TextColumn::make('phone_contact_2')
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
                IconColumn::make('tdec')
                    ->boolean(),
                IconColumn::make('tdev')
                    ->boolean(),
                TextColumn::make('commission_tdec')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_tdec_renewal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_tdev')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_tdev_renewal')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('file_acuerdo')
                    ->searchable(),
                TextColumn::make('file_planilla')
                    ->searchable(),
                TextColumn::make('status')
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
                TextColumn::make('fir_dig_agent')
                    ->searchable(),
                TextColumn::make('fir_dig_agency')
                    ->searchable(),
                TextColumn::make('date_register')
                    ->searchable(),
                IconColumn::make('is_accepted')
                    ->boolean(),
                TextColumn::make('file_ci_rif')
                    ->searchable(),
                TextColumn::make('file_w8_w9')
                    ->searchable(),
                TextColumn::make('file_account_usd')
                    ->searchable(),
                TextColumn::make('file_account_bsd')
                    ->searchable(),
                TextColumn::make('file_account_zelle')
                    ->searchable(),
                TextColumn::make('owner_master')
                    ->searchable(),
                TextColumn::make('owner_general')
                    ->searchable(),
                TextColumn::make('owner_agent')
                    ->searchable(),
                TextColumn::make('user_tdev')
                    ->searchable(),
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
