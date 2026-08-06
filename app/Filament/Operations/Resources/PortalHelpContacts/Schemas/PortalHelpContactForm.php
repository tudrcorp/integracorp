<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\PortalHelpContacts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class PortalHelpContactForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('portalHelpContactFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedPhone)
                            ->schema([
                                Section::make('Datos del contacto')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->description('Nombre y teléfono que verá el paciente en la vista Ayuda del portal.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Información de contacto')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('name')
                                                            ->label('Nombre')
                                                            ->prefixIcon('heroicon-m-identification')
                                                            ->placeholder('Ej. MediChat, Juan Pérez')
                                                            ->required()
                                                            ->maxLength(255)
                                                            ->helperText('Nombre visible para el paciente en el portal.')
                                                            ->validationMessages([
                                                                'required' => 'Campo requerido',
                                                            ]),
                                                        TextInput::make('phone')
                                                            ->label('Teléfono')
                                                            ->prefixIcon('heroicon-m-phone')
                                                            ->tel()
                                                            ->placeholder('+58 424-2132112')
                                                            ->required()
                                                            ->maxLength(40)
                                                            ->helperText('Incluye código de país. Ej. +58 424-2132112')
                                                            ->validationMessages([
                                                                'required' => 'Campo requerido',
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Publicación')
                            ->icon(Heroicon::OutlinedEye)
                            ->schema([
                                Section::make('Visibilidad en el portal')
                                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                                    ->description('Define el orden de aparición y si el contacto se muestra a los pacientes.')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Configuración de publicación')
                                            ->schema([
                                                Grid::make(2)
                                                    ->schema([
                                                        TextInput::make('sort_order')
                                                            ->label('Orden de aparición')
                                                            ->prefixIcon('heroicon-m-bars-3-bottom-left')
                                                            ->numeric()
                                                            ->integer()
                                                            ->minValue(0)
                                                            ->default(0)
                                                            ->required()
                                                            ->helperText('Menor número aparece primero en la lista del portal.')
                                                            ->validationMessages([
                                                                'required' => 'Campo requerido',
                                                            ]),
                                                        Select::make('status')
                                                            ->label('Estado')
                                                            ->prefixIcon('heroicon-m-check-badge')
                                                            ->options([
                                                                'ACTIVO' => 'ACTIVO',
                                                                'INACTIVO' => 'INACTIVO',
                                                            ])
                                                            ->default('ACTIVO')
                                                            ->required()
                                                            ->native(false)
                                                            ->searchable()
                                                            ->helperText('Solo los contactos ACTIVOS se muestran en la ayuda del portal.')
                                                            ->validationMessages([
                                                                'required' => 'Campo requerido',
                                                            ]),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
