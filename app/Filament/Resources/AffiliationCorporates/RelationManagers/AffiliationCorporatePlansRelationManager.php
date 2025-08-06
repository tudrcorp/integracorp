<?php

namespace App\Filament\Resources\AffiliationCorporates\RelationManagers;

use BackedEnum;
use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\AffiliationCorporates\AffiliationCorporateResource;

class AffiliationCorporatePlansRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliationCorporatePlans';

    protected static ?string $title = 'Plan(es) Afiliado(s)';

    protected static string|BackedEnum|null $icon = 'fontisto-share';

    public function table(Table $table): Table
    {
        return $table
            ->description('Lista de plan(es) afiliado(s)')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->numeric()
                    ->suffix(' US$')
                    ->searchable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('fee')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Tarifa'),
                TextColumn::make('total_persons')
                    ->suffix(' afiliado(s)')
                    ->numeric()
                    ->label('Afiliados'),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de Pago')
                    ->searchable(),
                TextColumn::make('subtotal_anual')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Anual'),
                TextColumn::make('subtotal_biannual')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Semestral'),
                TextColumn::make('subtotal_quarterly')
                    ->suffix(' US$')
                    ->numeric()
                    ->label('Subtotal Trimestral'),

            ]);
    }
}