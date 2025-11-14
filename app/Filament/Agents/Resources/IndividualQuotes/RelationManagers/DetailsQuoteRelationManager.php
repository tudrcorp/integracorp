<?php

namespace App\Filament\Agents\Resources\IndividualQuotes\RelationManagers;

use App\Models\Agent;
use Filament\Tables\Table;
use Filament\Actions\Action;
use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Agents\Resources\IndividualQuotes\IndividualQuoteResource;

class DetailsQuoteRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsQuote';

    // protected static ?string $relatedResource = IndividualQuoteResource::class;

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
                TextColumn::make('subtotal_monthly')
                    ->label('Total Mensual')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$')
                    ->hidden(fn (): bool => Agent::where('id', Auth::user()->agent_id)->first()->activate_monthly_frequency == 0),
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
                // BulkActionGroup::make([
                // BulkAction::make('quote_multiple')
                //     ->label('Preparar afiliación')
                //     ->color('success')
                //     ->icon('heroicon-c-receipt-percent')
                //     ->requiresConfirmation()
                //     ->deselectRecordsAfterCompletion()
                //     ->action(function (Collection $records, RelationManager $livewire) {
                //         // dd($records);
                //         try {

                //             //Guardo data records en una varaiable de sesion, si la variable de session exite y tiene informacion se actualiza

                //             session()->get('data_records', []);

                //             session()->put('data_records', $records->toArray());

                //             $data_records = session()->get('data_records');

                //             /**
                //              * Actualizo el status a APROBADA
                //              */
                //             $record = $records->first();
                            
                //             $individual_quote = IndividualQuote::where('id', $livewire->ownerRecord->id)->first();
                //             $individual_quote->status = 'APROBADA';
                //             $individual_quote->save();

                //         if ($records->count() == 1) {
                //                 return redirect()->route('filament.agents.resources.affiliations.create', ['plan_id' => $record->plan_id, 'individual_quote_id' => $livewire->ownerRecord->id]);
                //             }

                //             if ($records->count() > 1) {
                //                 return redirect()->route('filament.agents.resources.affiliations.create', ['plan_id' => null, 'individual_quote_id' => $livewire->ownerRecord->id]);
                //             }
                //         } catch (\Throwable $th) {
                //             dd($th);
                //             // $parte_entera = 0;
                //         }
                //     }),

                // DeleteBulkAction::make(),
                // ]),
            ]);
    }
}