<?php

declare(strict_types=1);

namespace App\Filament\General\Resources\IndividualQuotes\RelationManagers;

use App\Models\Agency;
use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_monthly')
                    ->label('Total Mensual')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$')
                    ->hidden(fn (): bool => Agency::where('code', Auth::user()->code_agency)->first()->activate_monthly_frequency == 0),
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
                        ->label('Pre Afiliación')
                        ->color('warning')
                        ->icon('heroicon-o-pencil-square')
                        ->requiresConfirmation()
                        ->modalHeading('PREAFILIACIÓN')
                        ->modalDescription('El sistema te redirigirá a la pantalla donde se encuentra el formulario de pre-afiliación.')
                        ->modalIcon('heroicon-o-pencil-square')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, RelationManager $livewire) {
                            try {
                                if ($records->count() > 1) {
                                    Notification::make()
                                        ->title('Acción no permitida')
                                        ->body('Solo se puede procesar una cotización a la vez para generar la afiliación.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $record = $records->first();
                                if ($record === null) {
                                    Notification::make()
                                        ->title('Sin selección')
                                        ->body('Debes seleccionar un detalle para continuar.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                session()->forget('data_records');
                                session()->put('data_records', $records->toArray());
                                session()->put('persons', max(1, (int) ($record->total_persons ?? 1)));

                                $individualQuote = IndividualQuote::query()->find($livewire->ownerRecord->id);
                                if ($individualQuote !== null) {
                                    $individualQuote->status = 'APROBADA';
                                    $individualQuote->save();
                                }

                                return redirect()->route('filament.general.resources.affiliations.create', [
                                    'id' => $livewire->ownerRecord->id,
                                    'plan_id' => $record->plan_id,
                                    'coverage_id' => $record->coverage_id,
                                ]);
                            } catch (\Throwable $th) {
                                Log::error('GENERAL: Falla al generar preafiliación desde detalle de cotización individual', [
                                    'individual_quote_id' => $livewire->ownerRecord->id,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('No se pudo procesar la preafiliación')
                                    ->body('Ocurrió un error inesperado. Intenta nuevamente o contacta a soporte.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
