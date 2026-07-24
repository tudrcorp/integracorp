<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhDepartamentoForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('rrhhDepartamentoFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información principal')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Fieldset::make('Identificación del departamento')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Datos generales')
                                            ->extraAttributes(['class' => self::INNER_CARD])
                                            ->schema([
                                                TextInput::make('description')
                                                    ->label('Nombre del departamento')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('Ej: TECNOLOGÍA')
                                                    ->prefixIcon('heroicon-m-building-office-2')
                                                    ->helperText('El nombre se convertirá automáticamente a mayúsculas.')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('description', $state.toUpperCase());
                                                    JS)
                                                    ->columnSpanFull(),
                                            ])
                                            ->columns(1)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(1)
                                    ->columnSpanFull(),
                            ]),
                    ]),
                Hidden::make('created_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated()
                    ->hiddenOn('edit'),
                Hidden::make('updated_by')
                    ->default(fn () => Auth::user()?->name ?? '')
                    ->dehydrated(),
            ]);
    }
}
