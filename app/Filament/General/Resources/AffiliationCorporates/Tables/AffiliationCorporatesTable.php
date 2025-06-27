<?php

namespace App\Filament\General\Resources\AffiliationCorporates\Tables;

use App\Models\Agent;
use App\Models\Agency;
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
        ->query(AffiliationCorporate::query()->where('code_agency', Auth::user()->code_agency))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('corporate_quote.code')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('registrated_by')
                    ->label('Registrado por:')
                    ->default(function ($record) {
                        if ($record->agent_id == null) {
                            return $record->code_agency;
                        }
                        if ($record->agent_id != null) {
                            if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                                return 'SUB-AGT-000' . $record->agent_id;
                            }
                            return 'AGT-000' . $record->agent_id;
                        }
                    })
                    ->badge()
                    ->icon(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();
                        if (Agent::where('id', $record->agent_id)->where('agent_type_id', 3)->exists()) {
                            return 'heroicon-m-users';
                        } elseif (Agent::where('id', $record->agent_id)->where('agent_type_id', 2)->exists()) {
                            return 'heroicon-m-user';
                        } elseif ($agency_type->typeAgency->definition == 'MASTER') {
                            return 'heroicon-m-academic-cap';
                        } else {
                            return 'heroicon-s-building-library';
                        }
                    })
                    ->color('primary')
                    ->searchable(),

                TextColumn::make('plan_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('agent_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coverage_id')
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
                TextColumn::make('vaucher_ils')
                    ->searchable(),
                TextColumn::make('date_payment_initial_ils')
                    ->searchable(),
                TextColumn::make('date_payment_final_ils')
                    ->searchable(),
                TextColumn::make('document_ils')
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

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}