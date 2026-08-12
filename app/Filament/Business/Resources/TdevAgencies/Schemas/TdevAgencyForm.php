<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\TdevAgencies\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use App\Models\TdevAgency;
use App\Support\Tdev\TdevAgencyRegistrar;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class TdevAgencyForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('tdevAgencyFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Agencia')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Identidad')
                                    ->description('Datos principales y marca de la agencia TDEV.')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                FileUpload::make('logo')
                                                    ->label('Imagen del logo')
                                                    ->directory('logos-agencias-tdev')
                                                    ->visibility('public')
                                                    ->image()
                                                    ->imageEditor()
                                                    ->maxSize(2048)
                                                    ->columnSpanFull(),
                                                TextInput::make('name')
                                                    ->label('Nombre de agencia')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('identification_number')
                                                    ->label('Número de identificación')
                                                    ->prefix('J/V/E-')
                                                    ->maxLength(50),
                                                TextInput::make('email')
                                                    ->label('Correo')
                                                    ->email()
                                                    ->maxLength(255),
                                                DatePicker::make('anniversary_date')
                                                    ->label('Fecha de aniversario de la agencia')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y'),
                                                TextInput::make('url')
                                                    ->label('URL')
                                                    ->url()
                                                    ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                                    ->maxLength(255)
                                                    ->helperText('Sitio web o enlace externo de la agencia.'),
                                                Hidden::make('level')
                                                    ->default(TdevAgency::LEVEL_TWO)
                                                    ->dehydrated(),
                                                Placeholder::make('level_display')
                                                    ->label('Nivel')
                                                    ->content(fn (?TdevAgency $record): string => $record?->isLevelThree()
                                                        ? 'Nivel 3 (asociada)'
                                                        : 'Nivel 2 (principal)'),
                                                Placeholder::make('parent_agency_name')
                                                    ->label('Agencia principal')
                                                    ->content(fn (?TdevAgency $record): string => $record?->parentAgency?->name ?? '—')
                                                    ->visible(fn (?TdevAgency $record): bool => (bool) $record?->isLevelThree()),
                                                TextInput::make('landing_slogan_line_1')
                                                    ->label('Eslogan página web · línea 1')
                                                    ->default(TdevAgency::DEFAULT_LANDING_SLOGAN_LINE_1)
                                                    ->maxLength(160)
                                                    ->helperText('Texto público en la página web de la agencia nivel 2.')
                                                    ->columnSpanFull()
                                                    ->visible(fn (?TdevAgency $record): bool => $record === null || $record->isLevelTwo()),
                                                TextInput::make('landing_slogan_line_2')
                                                    ->label('Eslogan página web · línea 2')
                                                    ->default(TdevAgency::DEFAULT_LANDING_SLOGAN_LINE_2)
                                                    ->maxLength(160)
                                                    ->columnSpanFull()
                                                    ->visible(fn (?TdevAgency $record): bool => $record === null || $record->isLevelTwo()),
                                                Placeholder::make('public_landing_url')
                                                    ->label('URL de página web')
                                                    ->helperText('Página pública de la agencia con accesos a registro de agencias asociadas y agentes freelance.')
                                                    ->content(function (?TdevAgency $record): HtmlString {
                                                        if ($record === null || ! $record->isLevelTwo()) {
                                                            return new HtmlString('<span class="text-sm text-gray-500">Solo disponible para agencias nivel 2.</span>');
                                                        }

                                                        if (blank($record->registration_token)) {
                                                            return new HtmlString('<span class="text-sm text-gray-500">Se generará automáticamente al guardar la agencia.</span>');
                                                        }

                                                        $url = TdevAgencyRegistrar::publicLandingUrl($record);

                                                        return new HtmlString(
                                                            '<a href="'.e($url).'" target="_blank" rel="noopener" class="text-sm font-medium text-cyan-700 underline decoration-cyan-700/30 underline-offset-2 hover:decoration-cyan-700 dark:text-cyan-300">'
                                                            .e($url)
                                                            .'</a>'
                                                        );
                                                    })
                                                    ->visible(fn (?TdevAgency $record): bool => $record === null || $record->isLevelTwo())
                                                    ->visibleOn(['edit', 'view']),
                                                Placeholder::make('public_registration_url')
                                                    ->label('URL de registro de agentes')
                                                    ->helperText('Cada agencia (nivel 2 o 3) tiene su propio enlace; los agentes quedan asociados a esa agencia.')
                                                    ->content(function (?TdevAgency $record): HtmlString {
                                                        if ($record === null || blank($record->registration_token)) {
                                                            return new HtmlString('<span class="text-sm text-gray-500">Se generará automáticamente al guardar la agencia.</span>');
                                                        }

                                                        $url = TdevAgencyRegistrar::publicAgentRegistrationUrl($record);

                                                        return new HtmlString(
                                                            '<a href="'.e($url).'" target="_blank" rel="noopener" class="text-sm font-medium text-cyan-700 underline decoration-cyan-700/30 underline-offset-2 hover:decoration-cyan-700 dark:text-cyan-300">'
                                                            .e($url)
                                                            .'</a>'
                                                        );
                                                    })
                                                    ->visibleOn(['edit', 'view']),
                                                Placeholder::make('public_agency_registration_url')
                                                    ->label('URL de registro de agencias nivel 3')
                                                    ->content(function (?TdevAgency $record): HtmlString {
                                                        if ($record === null || ! $record->isLevelTwo()) {
                                                            return new HtmlString('<span class="text-sm text-gray-500">Solo disponible para agencias nivel 2.</span>');
                                                        }

                                                        if (blank($record->agency_registration_token)) {
                                                            return new HtmlString('<span class="text-sm text-gray-500">Se generará automáticamente al guardar la agencia.</span>');
                                                        }

                                                        $url = TdevAgencyRegistrar::publicLevelThreeAgencyRegistrationUrl($record);

                                                        return new HtmlString(
                                                            '<a href="'.e($url).'" target="_blank" rel="noopener" class="text-sm font-medium text-cyan-700 underline decoration-cyan-700/30 underline-offset-2 hover:decoration-cyan-700 dark:text-cyan-300">'
                                                            .e($url)
                                                            .'</a>'
                                                        );
                                                    })
                                                    ->visible(fn (?TdevAgency $record): bool => $record === null || $record->isLevelTwo())
                                                    ->visibleOn(['edit', 'view']),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Representante y contacto')
                                    ->description('Información del representante y canales de contacto.')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                TextInput::make('representative_name')
                                                    ->label('Nombre del representante')
                                                    ->maxLength(255)
                                                    ->live(onBlur: true)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('representative_name', $state.toUpperCase());
                                                    JS),
                                                DatePicker::make('representative_birth_date')
                                                    ->label('Fecha de nacimiento del representante')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y'),
                                                TextInput::make('phone')
                                                    ->label('Número de teléfono')
                                                    ->tel()
                                                    ->prefixIcon(Heroicon::OutlinedPhone)
                                                    ->maxLength(40),
                                                TextInput::make('phone_additional')
                                                    ->label('Número de teléfono adicional')
                                                    ->tel()
                                                    ->maxLength(40),
                                                TextInput::make('instagram_username')
                                                    ->label('Usuario Instagram')
                                                    ->prefix('@')
                                                    ->maxLength(100),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->description('País, estado, ciudad y dirección.')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->extraAttributes(['class' => self::SECTION_CLASS])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2, 'xl' => 3])
                                            ->extraAttributes(['class' => self::INNER_CLASS])
                                            ->schema([
                                                Select::make('country_id')
                                                    ->label('País')
                                                    ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->prefixIcon(Heroicon::OutlinedGlobeAmericas),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(function (Get $get): array {
                                                        $countryId = $get('country_id');

                                                        if (blank($countryId)) {
                                                            return [];
                                                        }

                                                        return State::query()
                                                            ->where('country_id', $countryId)
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->preload()
                                                    ->live(),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(function (Get $get): array {
                                                        $stateId = $get('state_id');

                                                        if (blank($stateId)) {
                                                            return [];
                                                        }

                                                        return City::query()
                                                            ->where('state_id', $stateId)
                                                            ->orderBy('definition')
                                                            ->pluck('definition', 'id')
                                                            ->all();
                                                    })
                                                    ->searchable()
                                                    ->preload(),
                                                Textarea::make('address')
                                                    ->label('Dirección')
                                                    ->rows(3)
                                                    ->columnSpanFull(),
                                                Hidden::make('created_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->dehydrated()
                                                    ->hiddenOn('edit'),
                                                Hidden::make('updated_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->dehydrated()
                                                    ->hiddenOn('create'),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
