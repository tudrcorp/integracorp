<?php

namespace App\Filament\General\Resources\IndividualQuotes\RelationManagers;

use Filament\Tables\Table;
use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Collection;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\General\Resources\IndividualQuotes\IndividualQuoteResource;

class DetailsQuoteRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsQuote';


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
                        ->label('Preparar afiliación')
                        ->color('success')
                        ->icon('heroicon-c-receipt-percent')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, RelationManager $livewire) {
                            // dd($records);
                            try {

                                //Guardo data records en una varaiable de sesion, si la variable de session exite y tiene informacion se actualiza

                                session()->get('data_records', []);

                                session()->put('data_records', $records->toArray());

                                $data_records = session()->get('data_records');

                                /**
                                 * Actualizo el status a APROBADA
                                 */
                                $record = $records->first();

                                $individual_quote = IndividualQuote::where('id', $livewire->ownerRecord->id)->first();
                                $individual_quote->status = 'APROBADA';
                                $individual_quote->save();

                                if ($records->count() == 1) {
                                    return redirect()->route('filament.general.resources.affiliations.create', ['plan_id' => $record->plan_id, 'individual_quote_id' => $livewire->ownerRecord->id]);
                                }

                                if ($records->count() > 1) {
                                    return redirect()->route('filament.general.resources.affiliations.create', ['plan_id' => null, 'individual_quote_id' => $livewire->ownerRecord->id]);
                                }
                            } catch (\Throwable $th) {
                                dd($th);
                                // $parte_entera = 0;
                            }
                        }),

                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}