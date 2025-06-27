<?php

namespace App\Filament\Master\Resources\AffiliationCorporates\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use App\Models\AffiliationCorporate;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->query(AffiliationCorporate::query()->whereIn('owner_code', [Auth::user()->code_agency, 'TDG-100']))
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('corporate_quote_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('plan_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('agent_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('full_name_con')
                    ->searchable(),
                TextColumn::make('rif')
                    ->searchable(),
                TextColumn::make('adress_con')
                    ->searchable(),
                TextColumn::make('city_id_con')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('state_id_con')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('country_id_con')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region_con')
                    ->searchable(),
                TextColumn::make('phone_con')
                    ->searchable(),
                TextColumn::make('email_con')
                    ->searchable(),
                IconColumn::make('cuestion_1')
                    ->boolean(),
                IconColumn::make('cuestion_2')
                    ->boolean(),
                IconColumn::make('cuestion_3')
                    ->boolean(),
                IconColumn::make('cuestion_4')
                    ->boolean(),
                IconColumn::make('cuestion_5')
                    ->boolean(),
                IconColumn::make('cuestion_6')
                    ->boolean(),
                IconColumn::make('cuestion_7')
                    ->boolean(),
                IconColumn::make('cuestion_8')
                    ->boolean(),
                IconColumn::make('cuestion_9')
                    ->boolean(),
                IconColumn::make('cuestion_10')
                    ->boolean(),
                IconColumn::make('cuestion_11')
                    ->boolean(),
                IconColumn::make('cuestion_12')
                    ->boolean(),
                IconColumn::make('cuestion_13')
                    ->boolean(),
                IconColumn::make('cuestion_14')
                    ->boolean(),
                IconColumn::make('cuestion_15')
                    ->boolean(),
                TextColumn::make('full_name_applicant')
                    ->searchable(),
                TextColumn::make('signature_applicant')
                    ->searchable(),
                TextColumn::make('nro_identificacion_applicant')
                    ->searchable(),
                TextColumn::make('date_applicant')
                    ->searchable(),
                TextColumn::make('full_name_agent')
                    ->searchable(),
                TextColumn::make('signature_agent')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->searchable(),
                TextColumn::make('activated_at')
                    ->searchable(),
                TextColumn::make('corporate_members')
                    ->searchable(),
                TextColumn::make('document')
                    ->searchable(),
                TextColumn::make('date_today')
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