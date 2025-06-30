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
            ->defaultSort('created_at', 'desc')
            ->description('Lista de afiliaciones corporativas registradas en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('corporate_quote.code')
                    ->label('Nro. de cotización')
                    ->badge()
                    ->color('primary')
                    ->icon('heroicon-m-tag')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('full_name_con')
                    ->label('Nombre contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('email_con')
                    ->label('Email contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('phone_con')
                    ->label('Telefono contratante')
                    ->badge()
                    ->color('verde')
                    ->searchable(),
                TextColumn::make('adress_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('city_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('state_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('country_id_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('region_con')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('cuestion_1')
                    ->label('Prgunta 1')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_2')
                    ->label('Prgunta 2')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_3')
                    ->label('Prgunta 3')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_4')
                    ->label('Prgunta 4')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_5')
                    ->label('Prgunta 5')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_6')
                    ->label('Prgunta 6')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_7')
                    ->label('Prgunta 7')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_8')
                    ->label('Prgunta 8')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_9')
                    ->label('Prgunta 9')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_10')
                    ->label('Prgunta 10')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_11')
                    ->label('Prgunta 11')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_12')
                    ->label('Prgunta 12')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_13')
                    ->label('Prgunta 13')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('cuestion_14')
                    ->label('Prgunta 14')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('date_today')
                    ->label('Fecha')
                    // ->dateTime()
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Estatus')

                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'PRE-APROBADA'          => 'success',
                            'ACTIVA'                => 'success',
                            'PENDIENTE'             => 'warning',
                            'EXCLUIDO'              => 'danger',
                        };
                    })
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