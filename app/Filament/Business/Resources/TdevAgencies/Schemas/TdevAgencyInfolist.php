<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Schemas;

use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class TdevAgencyInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('tdevAgencyInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Agencia')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Agencia TDEV')
                                    ->description('Identidad, nivel y enlaces públicos de la agencia.')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                ImageEntry::make('logo')
                                                    ->label('Logo')
                                                    ->disk('public')
                                                    ->visibility('public')
                                                    ->circular()
                                                    ->columnSpanFull(),
                                                TextEntry::make('name')
                                                    ->label('Nombre de agencia')
                                                    ->weight('semibold'),
                                                TextEntry::make('level')
                                                    ->label('Nivel')
                                                    ->badge()
                                                    ->formatStateUsing(fn (int|string|null $state): string => 'Nivel '.(string) $state)
                                                    ->color(fn (int|string|null $state): string => (int) $state === TdevAgency::LEVEL_TWO ? 'info' : 'warning'),
                                                TextEntry::make('parentAgency.name')
                                                    ->label('Agencia principal')
                                                    ->visible(fn (TdevAgency $record): bool => $record->isLevelThree())
                                                    ->placeholder('—'),
                                                TextEntry::make('identification_number')
                                                    ->label('Número de identificación')
                                                    ->placeholder('—'),
                                                TextEntry::make('email')
                                                    ->label('Correo')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('anniversary_date')
                                                    ->label('Aniversario')
                                                    ->date('d/m/Y')
                                                    ->placeholder('—'),
                                                TextEntry::make('url')
                                                    ->label('URL')
                                                    ->url(fn (?string $state): ?string => $state)
                                                    ->openUrlInNewTab()
                                                    ->placeholder('—'),
                                                TextEntry::make('landing_slogan_line_1')
                                                    ->label('Eslogan página web · línea 1')
                                                    ->state(fn (TdevAgency $record): string => $record->resolvedLandingSloganLine1())
                                                    ->columnSpanFull()
                                                    ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo()),
                                                TextEntry::make('landing_slogan_line_2')
                                                    ->label('Eslogan página web · línea 2')
                                                    ->state(fn (TdevAgency $record): string => $record->resolvedLandingSloganLine2())
                                                    ->columnSpanFull()
                                                    ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo()),
                                                TextEntry::make('public_landing_url')
                                                    ->label('URL de página web')
                                                    ->state(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLandingUrl($record))
                                                    ->copyable()
                                                    ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLandingUrl($record))
                                                    ->openUrlInNewTab()
                                                    ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo()),
                                                TextEntry::make('public_registration_url')
                                                    ->label('URL de registro de agentes')
                                                    ->state(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicAgentRegistrationUrl($record))
                                                    ->copyable()
                                                    ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicAgentRegistrationUrl($record))
                                                    ->openUrlInNewTab(),
                                                TextEntry::make('public_agency_registration_url')
                                                    ->label('URL de registro de agencias nivel 3')
                                                    ->state(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($record))
                                                    ->copyable()
                                                    ->url(fn (TdevAgency $record): string => TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($record))
                                                    ->openUrlInNewTab()
                                                    ->visible(fn (TdevAgency $record): bool => $record->isLevelTwo()),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Representante y contacto')
                                    ->description('Datos del representante y canales de comunicación.')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('representative_name')
                                                    ->label('Representante')
                                                    ->placeholder('—'),
                                                TextEntry::make('representative_birth_date')
                                                    ->label('Nacimiento del representante')
                                                    ->date('d/m/Y')
                                                    ->placeholder('—'),
                                                TextEntry::make('phone')
                                                    ->label('Teléfono')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('phone_additional')
                                                    ->label('Teléfono adicional')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('instagram_username')
                                                    ->label('Instagram')
                                                    ->formatStateUsing(fn (?string $state): string => filled($state) ? '@'.$state : '—'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->description('País, estado, ciudad y dirección de la agencia.')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->placeholder('—'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->columnSpanFull()
                                                    ->placeholder('—'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
