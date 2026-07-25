<?php

namespace App\Filament\Operations\Resources\OperationInventories\Schemas;

use App\Models\OperationInventory;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationInventoryInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen')
                            ->icon(Heroicon::Square3Stack3d)
                            ->schema([
                                Section::make('Ítem de inventario')
                                    ->description('Identificación del producto y existencia actual en el almacén.')
                                    ->icon(Heroicon::Square3Stack3d)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        ImageEntry::make('image')
                                            ->label('Imagen')
                                            ->disk('public')
                                            ->visibility('public')
                                            ->placeholder('Sin imagen')
                                            ->columnSpanFull(),
                                        Fieldset::make('Identificación')
                                            ->schema([
                                                TextEntry::make('product.code')
                                                    ->label('Código')
                                                    ->badge()
                                                    ->color('primary')
                                                    ->state(fn (OperationInventory $record): string => $record->product?->code
                                                        ?? $record->barcode
                                                        ?? ('INV-'.str_pad((string) $record->id, 5, '0', STR_PAD_LEFT))),
                                                TextEntry::make('barcode')
                                                    ->label('Código de barras')
                                                    ->placeholder('—'),
                                                TextEntry::make('name')
                                                    ->label('Producto')
                                                    ->columnSpanFull(),
                                                TextEntry::make('concentration')
                                                    ->label('Concentración')
                                                    ->placeholder('—'),
                                                TextEntry::make('laboratory')
                                                    ->label('Laboratorio')
                                                    ->placeholder('—'),
                                                IconEntry::make('is_active')
                                                    ->label('Activo')
                                                    ->boolean(),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Existencia')
                                            ->schema([
                                                TextEntry::make('existence')
                                                    ->label('Existencia')
                                                    ->badge()
                                                    ->color(fn (OperationInventory $record): string => match (true) {
                                                        $record->existence <= 0 => 'gray',
                                                        $record->existence <= ($record->min_stock ?: 5) => 'danger',
                                                        default => 'success',
                                                    })
                                                    ->suffix(' und.'),
                                                TextEntry::make('min_stock')
                                                    ->label('Stock mínimo')
                                                    ->badge()
                                                    ->color('warning')
                                                    ->suffix(' und.')
                                                    ->placeholder('—'),
                                                TextEntry::make('unit')
                                                    ->label('Unidad')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('cost')
                                                    ->label('Costo')
                                                    ->money('USD')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Clasificación')
                            ->icon(Heroicon::Tag)
                            ->schema([
                                Section::make('Clasificación del ítem')
                                    ->description('Tipo, principio activo, categoría y producto maestro vinculado.')
                                    ->icon(Heroicon::Tag)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Catálogos')
                                            ->schema([
                                                TextEntry::make('operationInventoryType.name')
                                                    ->label('Tipo')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('operationInventoryPrincipleActive.name')
                                                    ->label('Principio activo')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('operationInventoryCategory.name')
                                                    ->label('Categoría (legado)')
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('product.category.name')
                                                    ->label('Categoría de producto')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('product.name')
                                                    ->label('Producto maestro')
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                                IconEntry::make('is_covered')
                                                    ->label('Cubierto')
                                                    ->boolean(),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Almacén')
                            ->icon(Heroicon::BuildingStorefront)
                            ->schema([
                                Section::make('Ubicación')
                                    ->description('Almacén y ubicación física del ítem.')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Ubicación logística')
                                            ->schema([
                                                TextEntry::make('ubicationRelation.name')
                                                    ->label('Almacén')
                                                    ->badge()
                                                    ->color('info')
                                                    ->state(fn (OperationInventory $record): string => $record->ubicationRelation?->name
                                                        ?? $record->ubication
                                                        ?? '—'),
                                                TextEntry::make('location')
                                                    ->label('Ubicación interna')
                                                    ->placeholder('—'),
                                                TextEntry::make('ubication')
                                                    ->label('Almacén (texto)')
                                                    ->placeholder('—')
                                                    ->visible(fn (OperationInventory $record): bool => filled($record->ubication)
                                                        && blank($record->ubicationRelation?->name)),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Registro')
                            ->icon(Heroicon::Clock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->description('Trazabilidad de creación y última actualización del ítem.')
                                    ->icon(Heroicon::Clock)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Trazabilidad')
                                            ->schema([
                                                TextEntry::make('created_by')
                                                    ->label('Creado por')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_by')
                                                    ->label('Actualizado por')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Creado')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_at')
                                                    ->label('Actualizado')
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                            ])
                                            ->columns(2),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
