<?php

namespace App\Filament\Business\Resources\BenefitCoverages\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BenefitCoveragesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('benefit.description')
                    ->label('Beneficio')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('limit')
                    ->label('Limite de Consumo')
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}