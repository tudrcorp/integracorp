<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Schemas;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use App\Support\Operations\OperationInventoryProductPricing;
use App\Support\Operations\OperationInventoryProductStockStatus;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class OperationInventoryProductInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryProductInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Producto')
                            ->icon(Heroicon::Cube)
                            ->schema([
                                Section::make('Datos del producto')
                                    ->description('Identificación y presentación del ítem de inventario.')
                                    ->icon(Heroicon::Cube)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Identificación')
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Nombre del producto')
                                                    ->weight(FontWeight::SemiBold)
                                                    ->columnSpanFull(),
                                                TextEntry::make('code')
                                                    ->label('Código')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->copyable()
                                                    ->copyMessage('Código copiado'),
                                                TextEntry::make('category.name')
                                                    ->label('Categoría')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('Sin categoría'),
                                                IconEntry::make('is_active')
                                                    ->label('Activo')
                                                    ->boolean(),
                                            ])
                                            ->columns(3),
                                        Fieldset::make('Presentación')
                                            ->schema([
                                                TextEntry::make('unit')
                                                    ->label('Unidad')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('presentation')
                                                    ->label('Presentación')
                                                    ->badge()
                                                    ->formatStateUsing(fn (mixed $state): string => OperationInventoryProductPresentation::labelFromMixed($state))
                                                    ->color(fn (mixed $state): string => OperationInventoryProductPresentation::filamentColorFromMixed($state))
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                                Section::make('Precio de venta')
                                    ->description('Precio de referencia: costo más el porcentaje de utilidad de la categoría. No modifica órdenes ni documentos ya emitidos.')
                                    ->icon(Heroicon::CurrencyDollar)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextEntry::make('cost')
                                            ->label('Costo')
                                            ->money('USD')
                                            ->placeholder('Sin costo'),
                                        TextEntry::make('category.profit_percentage')
                                            ->label('% de utilidad')
                                            ->numeric(decimalPlaces: 2)
                                            ->suffix(' %')
                                            ->placeholder('Sin definir'),
                                        TextEntry::make('profit_amount')
                                            ->label('Utilidad por unidad')
                                            ->state(fn (OperationInventoryProduct $record): ?float => OperationInventoryProductPricing::profitAmountFor($record))
                                            ->money('USD')
                                            ->placeholder('—'),
                                        TextEntry::make('final_price')
                                            ->label('Precio final')
                                            ->state(fn (OperationInventoryProduct $record): ?float => OperationInventoryProductPricing::finalPriceFor($record))
                                            ->money('USD')
                                            ->size(TextSize::Large)
                                            ->weight(FontWeight::Bold)
                                            ->color('success')
                                            ->placeholder('Sin definir')
                                            ->helperText(fn (OperationInventoryProduct $record): ?string => OperationInventoryProductPricing::missingPriceReason($record)),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Existencia')
                            ->icon(Heroicon::BuildingStorefront)
                            ->badge(fn (OperationInventoryProduct $record): string => number_format((int) $record->stocks->sum('existence'), 0, ',', '.'))
                            ->badgeColor(fn (OperationInventoryProduct $record): string => OperationInventoryProductStockStatus::color(
                                OperationInventoryProductStockStatus::for((int) $record->stocks->sum('existence'))
                            ))
                            ->schema([
                                Section::make('Existencia por almacén')
                                    ->description('Use la acción «Cargar existencia» para actualizar el stock en cada almacén.')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextEntry::make('total_existence')
                                            ->label('Existencia total')
                                            ->state(fn (OperationInventoryProduct $record): int => (int) $record->stocks->sum('existence'))
                                            ->numeric()
                                            ->badge()
                                            ->color(fn (int $state): string => OperationInventoryProductStockStatus::color(OperationInventoryProductStockStatus::for($state)))
                                            ->suffix(' und.'),
                                        TextEntry::make('stock_status')
                                            ->label('Estado')
                                            ->state(fn (OperationInventoryProduct $record): string => OperationInventoryProductStockStatus::for((int) $record->stocks->sum('existence')))
                                            ->formatStateUsing(fn (string $state): string => OperationInventoryProductStockStatus::label($state))
                                            ->badge()
                                            ->icon(fn (string $state): string => OperationInventoryProductStockStatus::icon($state))
                                            ->color(fn (string $state): string => OperationInventoryProductStockStatus::color($state))
                                            ->helperText(fn (): string => 'Stock bajo: de 1 a '.OperationInventoryProductStockStatus::threshold().' und. (Parámetros de Inventario).'),
                                        TextEntry::make('warehouses_with_stock')
                                            ->label('Almacenes con existencia')
                                            ->state(fn (OperationInventoryProduct $record): string => $record->stocks->where('existence', '>', 0)->count().' de '.$record->stocks->count()),
                                        TextEntry::make('stock_value')
                                            ->label('Valor del inventario al costo')
                                            ->state(fn (OperationInventoryProduct $record): float => round((int) $record->stocks->sum('existence') * (float) $record->cost, 2))
                                            ->money('USD')
                                            ->helperText('Existencia total × costo.'),
                                        RepeatableEntry::make('stocks')
                                            ->label('Almacenes')
                                            ->schema([
                                                TextEntry::make('ubication.name')
                                                    ->label('Almacén')
                                                    ->icon(Heroicon::BuildingStorefront)
                                                    ->placeholder('—'),
                                                TextEntry::make('existence')
                                                    ->label('Existencia')
                                                    ->badge()
                                                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'success' : 'gray')
                                                    ->suffix(' und.'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(3)
                                            ->columnSpanFull()
                                            ->placeholder('Sin existencias cargadas.'),
                                    ])
                                    ->columns(4)
                                    ->columnSpanFull(),
                                Section::make('Últimos movimientos')
                                    ->description('Última entrada y última salida registradas del producto.')
                                    ->icon(Heroicon::ArrowsRightLeft)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Última entrada')
                                            ->schema([
                                                TextEntry::make('latestInventoryEntry.created_at')
                                                    ->label('Fecha')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('Sin entradas'),
                                                TextEntry::make('latestInventoryEntry.quantity')
                                                    ->label('Cantidad')
                                                    ->numeric()
                                                    ->prefix('+ ')
                                                    ->suffix(' und.')
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('latestInventoryEntry.ubication.name')
                                                    ->label('Almacén')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(3),
                                        Fieldset::make('Última salida')
                                            ->schema([
                                                TextEntry::make('latestInventoryOutflow.created_at')
                                                    ->label('Fecha')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('Sin salidas'),
                                                TextEntry::make('latestInventoryOutflow.quantity')
                                                    ->label('Cantidad')
                                                    ->numeric()
                                                    ->prefix('− ')
                                                    ->suffix(' und.')
                                                    ->color('danger')
                                                    ->placeholder('—'),
                                                TextEntry::make('latestInventoryOutflow.ubication.name')
                                                    ->label('Almacén')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Registro')
                            ->icon(Heroicon::Clock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Trazabilidad de creación y última actualización del producto.')
                                    ->icon(Heroicon::Clock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Trazabilidad')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Creado por')
                                                    ->icon(Heroicon::User)
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Creado')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->helperText(fn (OperationInventoryProduct $record): ?string => $record->created_at?->diffForHumans())
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_at')
                                                    ->label('Actualizado')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->helperText(fn (OperationInventoryProduct $record): ?string => $record->updated_at?->diffForHumans())
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
