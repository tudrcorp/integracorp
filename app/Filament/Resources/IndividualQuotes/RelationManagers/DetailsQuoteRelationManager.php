<?php

namespace App\Filament\Resources\IndividualQuotes\RelationManagers;

use BackedEnum;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\AgeRange;
use App\Models\Collection;
use Filament\Tables\Table;
use App\Models\CoveragePlan;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Support\Icons\Heroicon;
use App\Models\DetailIndividualQuote;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Filters\SelectFilter;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\IndividualQuotes\IndividualQuoteResource;

class DetailsQuoteRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsQuote';

    protected static ?string $title = 'DETALLE DE COTIZACIÓN';

    public function table(Table $table): Table
    {
        return $table
            ->heading('DETALLES DE LA COTIZACIÓN')
            ->description('COBERTURAS, TARIFAS AGRUPADAS POR EL RANGO DE EDAD')
            ->recordTitleAttribute('individual_quote_id')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->searchable()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Total anual')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verde',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'azul',
                        };
                    })
                    ->sortable(),
            ])
            //agrupar por planes y por coberturas
            ->defaultGroup('ageRange.range')
            ->filters([
                SelectFilter::make('coverage_id')
                    ->label('Lista de coberturas')
                    ->relationship('coverage', 'price')
                    ->attribute('sucursal_id'),
            ])
            ->headerActions([
                // CreateAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('quote_multiple')
                        ->label('Pre-Afiliacion')
                        ->color('success')
                        ->icon('heroicon-c-receipt-percent')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records) {
                            dd($records->toArray());
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}