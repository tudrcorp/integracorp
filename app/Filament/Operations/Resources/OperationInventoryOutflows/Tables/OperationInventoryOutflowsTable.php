<?php

namespace App\Filament\Operations\Resources\OperationInventoryOutflows\Tables;

use App\Http\Controllers\OperationInventoryOutflowExportCsvController;
use App\Models\OperationInventoryOutflow;
use App\Support\Filament\CsvExportDownloadTrigger;
use App\Support\Filament\FilamentIosButton;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
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
            ->description('Despachos, ajustes y salidas vinculadas a telemedicina.')
            ->defaultSort('created_at', 'desc')
            ->searchable()
            ->searchPlaceholder('Buscar por código, producto, almacén, caso o tipo…')
            ->persistSearchInSession()
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('Sin salidas de inventario')
            ->emptyStateDescription('Cuando se registre una salida o un despacho de telemedicina aparecerá aquí.')
            ->emptyStateIcon(Heroicon::OutlinedArrowLeftStartOnRectangle)
            ->striped()
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
                    ->icon(Heroicon::OutlinedCube)
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
                    ->icon(Heroicon::OutlinedBuildingStorefront)
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
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'SALIDA TELEMEDICINA' => 'danger',
                        'AJUSTE INICIAL' => 'gray',
                        'AJUSTE DE EXISTENCIA', 'AJUSTE DE INVENTARIO' => 'warning',
                        default => 'warning',
                    })
                    ->icon(fn (?string $state): string => match (mb_strtoupper(trim((string) ($state ?? '')))) {
                        'SALIDA TELEMEDICINA' => 'heroicon-o-heart',
                        default => 'heroicon-o-arrow-left-start-on-rectangle',
                    })
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('telemedicineCase.code')
                    ->label('Nº caso')
                    ->badge()
                    ->color('primary')
                    ->icon('healthicons-f-health-literacy')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->formatStateUsing(fn (?string $state): string => filled($state) ? mb_strtoupper((string) $state) : '—'),
                TextColumn::make('observations')
                    ->label('Motivo / nota')
                    ->wrap()
                    ->lineClamp(2)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('created_by')
                    ->label('Registrado por')
                    ->icon(Heroicon::User)
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (OperationInventoryOutflow $record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays),
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
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->extraAttributes([
                        'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('export_outflows_csv')
                        ->label('Exportar CSV')
                        ->icon(Heroicon::OutlinedArrowDownTray)
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
