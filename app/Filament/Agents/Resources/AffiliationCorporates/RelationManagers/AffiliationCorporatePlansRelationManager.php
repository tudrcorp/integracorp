<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use App\Models\AfilliationCorporatePlan;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Agents\Resources\AffiliationCorporates\AffiliationCorporateResource;

class AffiliationCorporatePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliationCorporatePlans';

    protected static ?string $relatedResource = AffiliationCorporateResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan.description'),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric()
                    ->suffix(' UD$'),
                TextColumn::make('ageRange.range'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Subtotal Anual')
                    ->suffix('US$')
                    ->numeric()
                    ->description(fn(AfilliationCorporatePlan $record): string => $record->total_persons . ' Persona(s)'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Subtotal Trimestral')
                    ->suffix('US$')
                    ->numeric()
                    ->description(fn(AfilliationCorporatePlan $record): string => $record->total_persons . ' Persona(s)'),
                TextColumn::make('subtotal_biannual')
                    ->label('Subtotal Semestral')
                    ->suffix('US$')
                    ->numeric()
                    ->description(fn(AfilliationCorporatePlan $record): string => $record->total_persons . ' Persona(s)'),
                TextColumn::make('subtotal_monthly')
                    ->label('Subtotal Mensual')
                    ->suffix('US$')
                    ->numeric()
                    ->description(fn(AfilliationCorporatePlan $record): string => $record->total_persons . ' Persona(s)'),
            ])
            ->headerActions([
                CreateAction::make(),
            ]);
    }
}