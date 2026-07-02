<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Schemas;

use App\Filament\Business\Resources\Companies\Schemas\CompanyResponsibleRepeater;
use App\Models\PlanGenerator;
use App\Support\Companies\CompanyResponsibleDays;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Component;

class RegisterCompanyForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('registerCompanyTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->id('register-company-tabs')
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Empresa')
                            ->icon(Heroicon::OutlinedBuildingOffice2)
                            ->schema([
                                self::companySection(),
                            ]),
                        Tab::make('Responsables')
                            ->icon(Heroicon::OutlinedUserGroup)
                            ->schema([
                                self::responsiblesSection(),
                            ]),
                    ]),
            ]);
    }

    private static function companySection(): Section
    {
        return Section::make('Registro de Empresa')
            ->description('Datos fiscales y de contacto de la empresa asociada a la cotización aprobada.')
            ->icon(Heroicon::OutlinedBuildingOffice2)
            ->extraAttributes([
                'class' => self::IOS_SECTION_CLASS,
            ])
            ->schema([
                Grid::make(1)
                    ->extraAttributes([
                        'class' => self::IOS_INNER_CLASS,
                    ])
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextInput::make('name')
                                    ->label('Nombre / Razón Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->autofocus()
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                    ->placeholder('Ej: Distribuidora FT 0214, C.A.')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextInput::make('rif')
                                    ->label('RIF')
                                    ->required()
                                    ->maxLength(20)
                                    ->prefixIcon(Heroicon::OutlinedIdentification)
                                    ->placeholder('Ej: J-12345678-9'),
                                TextInput::make('phone')
                                    ->label('Teléfono')
                                    ->tel()
                                    ->maxLength(30)
                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                    ->placeholder('Ej: 0212-1234567'),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                                    ->placeholder('Ej: contacto@empresa.com')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                Textarea::make('address')
                                    ->label('Dirección')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->placeholder('Ej: Av. Principal, Edificio Centro, Piso 4, Caracas')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                            ]),
                    ]),
            ]);
    }

    private static function responsiblesSection(): Section
    {
        return Section::make('Responsables')
            ->description('Registre uno o varios responsables. La suma de días contratados no puede exceder la población del plan asociado.')
            ->icon(Heroicon::OutlinedUserGroup)
            ->extraAttributes([
                'class' => self::IOS_SECTION_CLASS,
            ])
            ->schema([
                Grid::make(1)
                    ->extraAttributes([
                        'class' => self::IOS_INNER_CLASS,
                    ])
                    ->schema([
                        CompanyResponsibleRepeater::make('responsibles', self::populationResolver()),
                    ]),
            ]);
    }

    private static function populationResolver(): \Closure
    {
        return function (Component $livewire, Get $get): ?int {
            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

            if ($record instanceof PlanGenerator) {
                return CompanyResponsibleDays::populationTotalFor($record);
            }

            return null;
        };
    }
}
