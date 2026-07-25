<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Tables;

use App\Http\Controllers\OperationInventoryEntryExportCsvController;
use App\Models\OperationInventoryEntry;
use App\Support\Filament\CsvExportDownloadTrigger;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class OperationInventoryEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Entradas de inventario')
            ->description('Registro de cargas y reposiciones por producto y almacén.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventoryEntry $record): string => $record->product?->code
                        ?? $record->operationInventory?->barcode
                        ?? '—'),
                TextColumn::make('product.name')
                    ->label('Producto')
                    ->icon('heroicon-o-cube')
                    ->weight(FontWeight::SemiBold)
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->lineClamp(2)
                    ->getStateUsing(fn (OperationInventoryEntry $record): string => $record->product?->name
                        ?? $record->operationInventory?->name
                        ?? '—'),
                TextColumn::make('ubication.name')
                    ->label('Almacén')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventoryEntry $record): string => $record->ubication?->name
                        ?? $record->operationInventory?->ubication
                        ?? '—'),
                TextColumn::make('quantity')
                    ->label('Cantidad entrante')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->suffix(' und.'),
                TextColumn::make('type_entry')
                    ->label('Tipo de entrada')
                    ->badge()
                    ->color(fn (?string $state): string => $state === 'PRIMERA CARGA' ? 'success' : 'warning')
                    ->icon(fn (?string $state): string => $state === 'PRIMERA CARGA'
                        ? 'heroicon-o-clipboard-document-check'
                        : 'heroicon-o-truck')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryEntry $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon('heroicon-m-calendar-days'),
            ])
            ->filters([
                SelectFilter::make('operation_inventory_ubication_id')
                    ->label('Almacén')
                    ->relationship('ubication', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('type_entry')
                    ->label('Tipo de entrada')
                    ->options([
                        'PRIMERA CARGA' => 'Primera carga',
                        'REPOSICIÓN DE INVENTARIO' => 'Reposición de inventario',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_entries_csv')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, BulkAction $action): void {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una entrada')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $token = OperationInventoryEntryExportCsvController::storeIdsAndGetToken(
                                $records->pluck('id')->all()
                            );

                            CsvExportDownloadTrigger::fromAction(
                                $action,
                                route('operations.inventory-entries.export-csv', ['token' => $token]),
                            );
                        }),
                ]),
            ]);
    }
}
