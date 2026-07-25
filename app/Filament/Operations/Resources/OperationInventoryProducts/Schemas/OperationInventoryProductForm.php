<?php

namespace App\Filament\Operations\Resources\OperationInventoryProducts\Schemas;

use App\Enums\OperationInventoryProductPresentation;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class OperationInventoryProductForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryProductFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-cube')
                            ->schema([
                                Fieldset::make('Identificación del producto')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos generales')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('operation_inventory_product_category_id')
                                                    ->label('Categoría')
                                                    ->relationship(
                                                        name: 'category',
                                                        titleAttribute: 'name',
                                                        modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('name'),
                                                    )
                                                    ->searchable()
                                                    ->preload()
                                                    ->required()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-tag')
                                                    ->helperText('Solo se listan categorías activas.')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                                TextInput::make('code')
                                                    ->label('Código')
                                                    ->disabled()
                                                    ->dehydrated(fn (string $operation): bool => $operation !== 'create')
                                                    ->required(fn (string $operation): bool => $operation !== 'create')
                                                    ->unique(ignoreRecord: true)
                                                    ->prefixIcon('heroicon-m-hashtag')
                                                    ->helperText('Se genera automáticamente al guardar con formato TDG-00001.'),
                                                TextInput::make('name')
                                                    ->label('Nombre del producto')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Nombre comercial o descriptivo')
                                                    ->prefixIcon('heroicon-m-cube')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Comercialización')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Costo y presentación')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('cost')
                                                    ->label('Costo')
                                                    ->numeric()
                                                    ->required()
                                                    ->default(0)
                                                    ->minValue(0)
                                                    ->prefix('$')
                                                    ->prefixIcon('heroicon-m-currency-dollar')
                                                    ->helperText('Costo en dólares (uso administrativo).')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                                TextInput::make('unit')
                                                    ->label('Unidad')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Ej. UNIDAD, TABLETAS, GOTAS, AMPOLLAS')
                                                    ->prefixIcon('heroicon-m-scale')
                                                    ->helperText('Unidad de medida del producto.')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                                Select::make('presentation')
                                                    ->label('Presentación')
                                                    ->options(OperationInventoryProductPresentation::options())
                                                    ->required()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-archive-box')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                            ])
                                            ->columns(2)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                                Fieldset::make('Estatus')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Disponibilidad')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                Select::make('is_active')
                                                    ->label('Estatus')
                                                    ->options([
                                                        1 => 'Activo',
                                                        0 => 'Inactivo',
                                                    ])
                                                    ->default(1)
                                                    ->required()
                                                    ->native(false)
                                                    ->prefixIcon('heroicon-m-check-badge')
                                                    ->helperText('Activo: disponible en inventario. Inactivo: no se podrá usar en operaciones.')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                    ]),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
