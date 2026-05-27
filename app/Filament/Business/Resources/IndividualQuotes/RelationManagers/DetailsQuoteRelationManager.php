<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\IndividualQuotes\RelationManagers;

use App\Models\IndividualQuote;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class DetailsQuoteRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsQuote';

    // protected static ?string $relatedResource = IndividualQuoteResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['plan', 'ageRange', 'coverage'])
                ->orderBy('plan_id')
                ->orderBy('age_range_id'))
            ->heading('Detalles de la cotización')
            ->description('Revisa planes, coberturas y subtotales por rango de edad antes de generar la preafiliación.')
            ->emptyStateHeading('Sin detalles de cotización')
            ->emptyStateDescription('Aún no se han generado filas para esta cotización individual.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->recordTitleAttribute('individual_quote_id')
            ->striped()
            ->defaultSort('subtotal_anual', 'desc')
            ->defaultPaginationPageOption(10)
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->numeric(decimalPlaces: 0)
                    ->weight(FontWeight::SemiBold)
                    ->suffix(' UD$'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record): string => (int) $record->total_persons.' '.((int) $record->total_persons === 1 ? 'persona' : 'personas'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Total anual')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record): string => (int) $record->total_persons.' '.((int) $record->total_persons === 1 ? 'persona' : 'personas'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record): string => (int) $record->total_persons.' '.((int) $record->total_persons === 1 ? 'persona' : 'personas'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn ($record): string => (int) $record->total_persons.' '.((int) $record->total_persons === 1 ? 'persona' : 'personas'))
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'warning',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'info',
                            default => 'gray',
                        };
                    })
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultGroup('plan.description')
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Plan')
                    ->multiple()
                    ->preload()
                    ->relationship('plan', 'description')
                    ->attribute('plan_id'),
                SelectFilter::make('coverage_id')
                    ->label('Cobertura')
                    ->multiple()
                    ->preload()
                    ->relationship('coverage', 'price')
                    ->attribute('coverage_id'),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-APROBADA' => 'Pre-aprobada',
                        'APROBADA' => 'Aprobada',
                        'EJECUTADA' => 'Ejecutada',
                    ]),
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
                                session()->forget('data_records');
                                session()->get('data_records', []);
                                session()->put('data_records', $records->toArray());

                                $record = $records->first();
                                if ($record === null) {
                                    Notification::make()
                                        ->title('Sin selección')
                                        ->body('Debes seleccionar un detalle para continuar.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $individualQuote = IndividualQuote::query()->find($livewire->ownerRecord->id);
                                if ($individualQuote !== null) {
                                    $individualQuote->status = 'APROBADA';
                                    $individualQuote->save();
                                }

                                return redirect()->route('filament.business.resources.affiliations.create', ['plan_id' => $record->plan_id, 'individual_quote_id' => $livewire->ownerRecord->id]);
                            } catch (\Throwable $th) {
                                Log::error('BUSINESS: Falla al generar preafiliación desde detalle de cotización individual', [
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
