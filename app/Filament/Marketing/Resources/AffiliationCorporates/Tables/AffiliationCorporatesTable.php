<?php

namespace App\Filament\Marketing\Resources\AffiliationCorporates\Tables;

use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ColumnGroup;

class AffiliationCorporatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->columns([
            TextColumn::make('agency.name_corporative')
                ->label('Agencia')
                ->badge()
                ->color('azulOscuro')
                ->searchable(),
            //...  
            ColumnGroup::make('Plan Afiliado', [
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('poblation')
                    ->label('Población')
                    ->alignCenter()
                    ->suffix(' persona(s)')
                    ->badge()
                    ->color('success')
                    ->searchable(),
            ]),
            TextColumn::make('rif')
                ->label('Rif')
                ->prefix('J-')
                ->badge()
                ->color('verde')
                ->searchable(),
            TextColumn::make('email')
                ->label('Email contratante')
                ->searchable(),
            TextColumn::make('phone')
                ->label('Telefono contratante')
                ->searchable(),
            TextColumn::make('address')
                ->searchable(),
            TextColumn::make('city.definition')
                ->searchable(),
            TextColumn::make('state.definition')
                ->searchable(),
            TextColumn::make('country.name')
                ->searchable(),
            TextColumn::make('region_con')
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
                ->searchable()
                ->icon(function (mixed $state): ?string {
                    return match ($state) {
                        'PRE-APROBADA'          => 'heroicon-c-information-circle',
                        'ACTIVA'                => 'heroicon-s-check-circle',
                        'PENDIENTE'             => 'heroicon-s-exclamation-circle',
                        'EXCLUIDO'              => 'heroicon-c-x-circle',
                    };
                }),
        ])
            ->filters([
                //
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}