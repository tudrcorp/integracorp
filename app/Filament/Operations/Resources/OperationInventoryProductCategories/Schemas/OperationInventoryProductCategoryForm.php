<?php

namespace App\Filament\Operations\Resources\OperationInventoryProductCategories\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class OperationInventoryProductCategoryForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('operationInventoryProductCategoryFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-tag')
                            ->schema([
                                Fieldset::make('Identificación de la categoría')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos generales')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->unique(ignoreRecord: true)
                                                    ->placeholder('Ej. Medicamento')
                                                    ->prefixIcon('heroicon-m-tag')
                                                    ->helperText('Nombre único de la categoría en el catálogo.')
                                                    ->validationMessages([
                                                        'required' => 'Campo requerido.',
                                                        'unique' => 'Ya existe una categoría con este nombre.',
                                                    ]),
                                                Textarea::make('description')
                                                    ->label('Descripción')
                                                    ->rows(3)
                                                    ->placeholder('Describa el alcance de esta categoría de productos.')
                                                    ->helperText('Opcional. Ayuda a distinguir categorías similares.')
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
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
                                                    ->helperText('Activo: disponible al crear productos. Inactivo: no se muestra en el catálogo.')
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
