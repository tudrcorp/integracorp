<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CorporateQuotes\RelationManagers;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Log;

class DetailCoporateQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'detailCoporateQuotes';

    protected static ?string $title = 'Cotización';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->with(['plan', 'ageRange', 'coverage'])
                ->orderBy('plan_id')
                ->orderBy('age_range_id'))
            ->heading('Detalles de la cotización corporativa')
            ->description('Selecciona planes y coberturas para generar la preafiliación corporativa (simple o múltiple).')
            ->recordTitleAttribute('corporate_quote_id')
            ->emptyStateHeading('Sin detalles de cotización')
            ->emptyStateDescription('Todavía no se han generado planes/coberturas para esta cotización.')
            ->emptyStateIcon(Heroicon::OutlinedClipboardDocumentList)
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
                    ->weight(FontWeight::SemiBold)
                    ->numeric(decimalPlaces: 0)
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
                            'EJECUTADA', 'ACTIVA-PENDIENTE' => 'info',
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
                        'ACTIVA-PENDIENTE' => 'Activa pendiente',
                    ]),
            ])
            ->deferFilters(false)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('pre_affiliation_multiple')
                        ->label('Generar preafiliación')
                        ->icon(Heroicon::OutlinedCheckCircle)
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('Generar preafiliación corporativa')
                        ->modalDescription('Si seleccionas 1 registro, se abrirá preafiliación simple. Con 2 o más, se abrirá preafiliación múltiple.')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (EloquentCollection $records, RelationManager $livewire) {
                            try {
                                if ($records->isEmpty()) {
                                    Notification::make()
                                        ->title('Sin selección')
                                        ->body('Debes seleccionar al menos un detalle para continuar.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                session()->get('data_records', []);
                                session()->put('data_records', $records->toArray());

                                $livewire->ownerRecord->status = 'APROBADA';
                                $livewire->ownerRecord->save();

                                $record = $records->first();
                                if ($record === null) {
                                    Notification::make()
                                        ->title('Sin datos')
                                        ->body('No se pudo identificar el detalle seleccionado.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                if ($records->count() === 1) {
                                    return redirect()->route('filament.business.resources.affiliation-corporates.create', ['id' => $record->corporate_quote_id, 'plan_id' => $record->plan_id]);
                                }

                                return redirect()->route('filament.business.resources.affiliation-corporates.create', ['id' => $livewire->ownerRecord->id, 'plan_id' => null]);
                            } catch (\Throwable $th) {
                                Log::error('BUSINESS: Falla al generar preafiliación desde detalle de cotización corporativa', [
                                    'corporate_quote_id' => $livewire->ownerRecord->id,
                                    'error' => $th->getMessage(),
                                ]);

                                Notification::make()
                                    ->title('No se pudo generar la preafiliación')
                                    ->body('Ocurrió un error inesperado. Intenta nuevamente o contacta a soporte.')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
