<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Schemas;

use App\Enums\OperationInventoryProductPresentation;
use App\Models\OperationInventoryProduct;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
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
                                    ->description('Identificación, costo y presentación del ítem de inventario.')
                                    ->icon(Heroicon::Cube)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Identificación')
                                            ->schema([
                                                TextEntry::make('category.name')
                                                    ->label('Categoría')
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('code')
                                                    ->label('Código')
                                                    ->badge()
                                                    ->color('primary'),
                                                TextEntry::make('name')
                                                    ->label('Nombre del producto')
                                                    ->columnSpanFull(),
                                                IconEntry::make('is_active')
                                                    ->label('Activo')
                                                    ->boolean(),
                                            ])
                                            ->columns(2),
                                        Fieldset::make('Comercial')
                                            ->schema([
                                                TextEntry::make('cost')
                                                    ->label('Costo')
                                                    ->money('USD'),
                                                TextEntry::make('unit')
                                                    ->label('Unidad')
                                                    ->badge()
                                                    ->color('gray'),
                                                TextEntry::make('presentation')
                                                    ->label('Presentación')
                                                    ->badge()
                                                    ->formatStateUsing(fn (mixed $state): string => OperationInventoryProductPresentation::labelFromMixed($state))
                                                    ->color(fn (mixed $state): string => OperationInventoryProductPresentation::filamentColorFromMixed($state)),
                                            ])
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Existencia')
                            ->icon(Heroicon::BuildingStorefront)
                            ->schema([
                                Section::make('Existencia por almacén')
                                    ->description('Use la acción «Cargar existencia» para actualizar el stock en cada almacén.')
                                    ->icon(Heroicon::BuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        TextEntry::make('total_existence')
                                            ->label('Existencia total')
                                            ->state(fn (OperationInventoryProduct $record): int => (int) $record->stocks->sum('existence'))
                                            ->badge()
                                            ->color('success')
                                            ->suffix(' und.'),
                                        RepeatableEntry::make('stocks')
                                            ->label('Almacenes')
                                            ->schema([
                                                TextEntry::make('ubication.name')
                                                    ->label('Almacén')
                                                    ->placeholder('—'),
                                                TextEntry::make('existence')
                                                    ->label('Existencia')
                                                    ->badge()
                                                    ->color(fn (mixed $state): string => (int) $state > 0 ? 'success' : 'gray')
                                                    ->suffix(' und.'),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull()
                                            ->placeholder('Sin existencias cargadas.'),
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
                                            ->columns(3),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
