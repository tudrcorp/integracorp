<?php

namespace App\Filament\Operations\Resources\OperationInventoryEntries\Schemas;

use App\Models\OperationInventoryEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationInventoryEntryInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryEntryInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::Truck)
                            ->schema([
                                Section::make('Entrada de inventario')
                                    ->description('Producto, almacén y cantidad registrada en la carga.')
                                    ->icon(Heroicon::Truck)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Producto y almacén')
                                            ->schema([
                                                TextEntry::make('product.code')
                                                    ->label('Código')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->state(fn (OperationInventoryEntry $record): string => $record->product?->code
                                                        ?? $record->operationInventory?->barcode
                                                        ?? '—'),
                                                TextEntry::make('product.name')
                                                    ->label('Producto')
                                                    ->state(fn (OperationInventoryEntry $record): string => $record->product?->name
                                                        ?? $record->operationInventory?->name
                                                        ?? '—')
                                                    ->columnSpanFull(),
                                                TextEntry::make('ubication.name')
                                                    ->label('Almacén')
                                                    ->badge()
                                                    ->color('info')
                                                    ->state(fn (OperationInventoryEntry $record): string => $record->ubication?->name
                                                        ?? $record->operationInventory?->ubication
                                                        ?? '—'),
                                                TextEntry::make('operationInventoryType.name')
                                                    ->label('Tipo de inventario')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—')
                                                    ->state(fn (OperationInventoryEntry $record): ?string => $record->operationInventoryType?->name
                                                        ?? $record->operationInventory?->operationInventoryType?->name),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Detalle de la entrada')
                                            ->schema([
                                                TextEntry::make('quantity')
                                                    ->label('Cantidad entrante')
                                                    ->badge()
                                                    ->color('success')
                                                    ->suffix(' und.'),
                                                TextEntry::make('type_entry')
                                                    ->label('Tipo de entrada')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => $state === 'PRIMERA CARGA' ? 'success' : 'warning')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Registro')
                            ->icon(Heroicon::Clock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Quién registró la entrada y cuándo se guardó.')
                                    ->icon(Heroicon::Clock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Trazabilidad')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Registrado por')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de registro')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->dateTime('d/m/Y H:i')
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
