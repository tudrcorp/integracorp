<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Tables;

use App\Http\Controllers\OperationInventoryOutflowExportCsvController;
use App\Models\OperationInventoryOutflow;
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

class OperationInventoryOutflowsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Salidas de inventario')
            ->description('Registro de salidas y ajustes por producto y almacén.')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product.code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventoryOutflow $record): string => $record->product?->code
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
                    ->getStateUsing(fn (OperationInventoryOutflow $record): string => $record->product?->name
                        ?? $record->operationInventory?->name
                        ?? '—'),
                TextColumn::make('ubication.name')
                    ->label('Almacén')
                    ->badge()
                    ->color('info')
                    ->icon('heroicon-o-building-storefront')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (OperationInventoryOutflow $record): string => $record->ubication?->name
                        ?? $record->operationInventory?->ubication
                        ?? '—'),
                TextColumn::make('quantity')
                    ->label('Cantidad saliente')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color('danger')
                    ->suffix(' und.'),
                TextColumn::make('type_entry')
                    ->label('Tipo de salida')
                    ->badge()
                    ->color('warning')
                    ->icon('heroicon-o-arrow-left-start-on-rectangle')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('observations')
                    ->label('Motivo / nota')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->icon('heroicon-m-user')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryOutflow $record): string => $record->created_at?->diffForHumans() ?? '')
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
                    ->label('Tipo de salida')
                    ->options([
                        'AJUSTE INICIAL' => 'Ajuste inicial',
                        'AJUSTE DE EXISTENCIA' => 'Ajuste de existencia',
                        'AJUSTE DE INVENTARIO' => 'Ajuste de inventario',
                        'SALIDA TELEMEDICINA' => 'Salida telemedicina',
                    ]),
            ])
            ->recordActions([
                ViewAction::make()->label('Ver'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_outflows_csv')
                        ->label('Exportar CSV')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (Collection $records, BulkAction $action): void {
                            if ($records->isEmpty()) {
                                Notification::make()
                                    ->warning()
                                    ->title('Selecciona al menos una salida')
                                    ->body('Marca los registros que deseas exportar o usa «Seleccionar todos» en la tabla.')
                                    ->send();

                                return;
                            }

                            $token = OperationInventoryOutflowExportCsvController::storeIdsAndGetToken(
                                $records->pluck('id')->all()
                            );

                            CsvExportDownloadTrigger::fromAction(
                                $action,
                                route('operations.inventory-outflows.export-csv', ['token' => $token]),
                            );
                        }),
                ]),
            ]);
    }
}
