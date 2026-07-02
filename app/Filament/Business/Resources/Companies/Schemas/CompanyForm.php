<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Schemas;

use App\Models\Company;
use App\Models\PlanGenerator;
use App\Support\Companies\CompanyResponsibleDays;
use Filament\Forms\Components\Select;
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

class CompanyForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('companyTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->id('company-resource-tabs')
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
        return Section::make('Datos de la Empresa')
            ->description('Información fiscal, de contacto y plan asociado.')
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
                                Select::make('plan_generator_id')
                                    ->label('Plan generado asociado')
                                    ->relationship('planGenerator', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->prefixIcon(Heroicon::OutlinedTableCells)
                                    ->helperText('Define la población máxima permitida para los días contratados.')
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                TextInput::make('name')
                                    ->label('Nombre / Razón Social')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
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
                                    ->prefixIcon(Heroicon::OutlinedPhone),
                                TextInput::make('email')
                                    ->label('Correo electrónico')
                                    ->email()
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::OutlinedEnvelope)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                Textarea::make('address')
                                    ->label('Dirección')
                                    ->rows(3)
                                    ->maxLength(500)
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                            ]),
                    ]),
            ]);
    }

    private static function responsiblesSection(): Section
    {
        return Section::make('Responsables')
            ->description('Uno o varios responsables. La suma de días contratados no puede exceder la población del plan asociado.')
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
                        CompanyResponsibleRepeater::make('responsibles', self::populationResolver())
                            ->relationship(),
                    ]),
            ]);
    }

    private static function populationResolver(): \Closure
    {
        return function (Component $livewire, Get $get): ?int {
            $planId = $get('plan_generator_id');

            if (filled($planId)) {
                return CompanyResponsibleDays::populationTotalFor(PlanGenerator::query()->whereKey($planId)->first());
            }

            $record = method_exists($livewire, 'getRecord') ? $livewire->getRecord() : null;

            if ($record instanceof Company) {
                return CompanyResponsibleDays::populationTotalFor($record->planGenerator);
            }

            return null;
        };
    }
}
