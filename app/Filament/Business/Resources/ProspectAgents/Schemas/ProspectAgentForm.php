<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Schemas;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProspectAgentForm
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    /** Venezuela: país por defecto de la ubicación del prospecto. */
    private const DEFAULT_COUNTRY_ID = 189;

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('prospectAgentFormTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos del prospecto')
                            ->icon(Heroicon::OutlinedUser)
                            ->schema([
                                Section::make('Datos del prospecto')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->description('Nombre completo, canales de contacto y personas de contacto.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre y apellido / Razon social')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->autocomplete('name')
                                                    ->placeholder('Ej. María Pérez')
                                                    ->columnSpanFull(),
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('phone_1')
                                                            ->label('Teléfono principal')
                                                            ->tel()
                                                            ->required()
                                                            ->regex('/^[0-9]+$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->helperText('Ej. 04125678909 (solo dígitos).')
                                                            ->placeholder('04125678909')
                                                            ->autocomplete('tel-national'),
                                                        TextInput::make('phone_2')
                                                            ->label('Teléfono alternativo')
                                                            ->tel()
                                                            ->regex('/^[0-9]*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->helperText('Opcional. Mismo formato que el principal.')
                                                            ->placeholder('04145678909')
                                                            ->autocomplete('tel-national'),
                                                    ]),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i')
                                                    ->unique(
                                                        table: 'prospect_agents',
                                                        column: 'email',
                                                        ignoreRecord: true,
                                                    )
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->placeholder('correo@ejemplo.com')
                                                    ->autocomplete('email')
                                                    ->columnSpanFull(),
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('website')
                                                            ->label('Página web')
                                                            ->url()
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedGlobeAlt)
                                                            ->placeholder('https://www.ejemplo.com')
                                                            ->helperText('Opcional. Incluye https://'),
                                                        Textarea::make('social_networks')
                                                            ->label('Redes sociales')
                                                            ->rows(3)
                                                            ->maxLength(500)
                                                            ->placeholder('Instagram, Facebook, LinkedIn, TikTok...')
                                                            ->helperText('Puedes listar varias redes o enlaces.'),
                                                    ]),
                                                Repeater::make('prospectAgentContacts')
                                                    ->relationship()
                                                    ->label('Personas de contacto')
                                                    ->helperText('Registra todas las personas de contacto que necesites. El nombre es obligatorio cuando llenas cualquier otro dato del contacto.')
                                                    ->schema([
                                                        TextInput::make('department')
                                                            ->label('Departamento')
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                            ->placeholder('Ej. Comercial'),
                                                        TextInput::make('position')
                                                            ->label('Cargo')
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedBriefcase)
                                                            ->placeholder('Ej. Gerente comercial'),
                                                        TextInput::make('name')
                                                            ->label('Nombre y apellido')
                                                            ->required(fn (Get $get): bool => self::contactHasData([
                                                                'department' => $get('department'),
                                                                'position' => $get('position'),
                                                                'phone' => $get('phone'),
                                                                'local_phone' => $get('local_phone'),
                                                                'extensions' => $get('extensions'),
                                                                'email' => $get('email'),
                                                            ]))
                                                            ->validationMessages([
                                                                'required' => 'Indica el nombre y apellido de este contacto.',
                                                            ])
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedUser)
                                                            ->placeholder('Ej. Carlos Mendoza'),
                                                        TextInput::make('email')
                                                            ->label('Correo electrónico')
                                                            ->email()
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                                                            ->placeholder('contacto@ejemplo.com'),
                                                        TextInput::make('phone')
                                                            ->label('Teléfono celular')
                                                            ->tel()
                                                            ->maxLength(30)
                                                            ->regex('/^[0-9]*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->prefixIcon(Heroicon::OutlinedDevicePhoneMobile)
                                                            ->placeholder('04125678909')
                                                            ->autocomplete('tel-national'),
                                                        TextInput::make('local_phone')
                                                            ->label('Teléfono local')
                                                            ->tel()
                                                            ->maxLength(30)
                                                            ->regex('/^[0-9]*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->prefixIcon(Heroicon::OutlinedPhone)
                                                            ->placeholder('02129876543'),
                                                        TextInput::make('extensions')
                                                            ->label('Extensión(es)')
                                                            ->maxLength(100)
                                                            ->regex('/^[0-9]+(\s*[,\/]\s*[0-9]+)*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números; si son varias, sepáralas con coma. Ej. 201, 204',
                                                            ])
                                                            ->prefixIcon(Heroicon::OutlinedHashtag)
                                                            ->placeholder('Ej. 201, 204')
                                                            ->helperText('Si son varias, sepáralas con coma.'),
                                                    ])
                                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                                    ->defaultItems(1)
                                                    ->minItems(0)
                                                    ->addActionLabel('Agregar contacto')
                                                    ->reorderable()
                                                    ->orderColumn('sort_order')
                                                    ->mutateRelationshipDataBeforeCreateUsing(self::discardEmptyContact(...))
                                                    ->mutateRelationshipDataBeforeSaveUsing(self::discardEmptyContact(...))
                                                    ->collapsible()
                                                    ->collapsed(fn (?Schema $item): bool => self::isSavedRepeaterItem($item))
                                                    ->itemLabel(fn (array $state): string => self::contactItemLabel($state))
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Sucursales')
                            ->icon(Heroicon::OutlinedBuildingStorefront)
                            ->schema([
                                Section::make('Sucursales')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->description('Sedes, oficinas o puntos de venta adicionales del prospecto.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Repeater::make('prospectAgentBranches')
                                                    ->relationship()
                                                    ->hiddenLabel()
                                                    ->helperText('Registra todas las sucursales que necesites. El nombre o razón social es obligatorio cuando llenas cualquier otro dato de la sucursal.')
                                                    ->schema([
                                                        Select::make('state_id')
                                                            ->label('Estado')
                                                            ->options(fn (Get $get): array => self::branchStateOptions($get('../../country_id')))
                                                            ->live()
                                                            ->afterStateUpdated(fn (Set $set): mixed => $set('city_id', null))
                                                            ->searchable()
                                                            ->preload()
                                                            ->native(false)
                                                            ->prefixIcon(Heroicon::OutlinedMap)
                                                            ->placeholder('Selecciona un estado'),
                                                        Select::make('city_id')
                                                            ->label('Ciudad')
                                                            ->options(fn (Get $get): array => self::branchCityOptions($get('state_id')))
                                                            ->disabled(fn (Get $get): bool => blank($get('state_id')))
                                                            ->helperText(fn (Get $get): ?string => blank($get('state_id')) ? 'Primero selecciona el estado.' : null)
                                                            ->searchable()
                                                            ->preload()
                                                            ->native(false)
                                                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                            ->placeholder('Selecciona una ciudad'),
                                                        TextInput::make('name')
                                                            ->label('Nombre / razón social')
                                                            ->required(fn (Get $get): bool => self::branchHasData([
                                                                'state_id' => $get('state_id'),
                                                                'city_id' => $get('city_id'),
                                                                'email' => $get('email'),
                                                                'phone' => $get('phone'),
                                                                'local_phone' => $get('local_phone'),
                                                                'address' => $get('address'),
                                                            ]))
                                                            ->validationMessages([
                                                                'required' => 'Indica el nombre o la razón social de esta sucursal.',
                                                            ])
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedBuildingStorefront)
                                                            ->placeholder('Ej. Sucursal Valencia'),
                                                        TextInput::make('email')
                                                            ->label('Correo')
                                                            ->email()
                                                            ->maxLength(255)
                                                            ->prefixIcon(Heroicon::OutlinedEnvelope)
                                                            ->placeholder('sucursal@ejemplo.com'),
                                                        TextInput::make('phone')
                                                            ->label('Celular')
                                                            ->tel()
                                                            ->maxLength(30)
                                                            ->regex('/^[0-9]*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->prefixIcon(Heroicon::OutlinedDevicePhoneMobile)
                                                            ->placeholder('04125678909'),
                                                        TextInput::make('local_phone')
                                                            ->label('Teléfono local')
                                                            ->tel()
                                                            ->maxLength(30)
                                                            ->regex('/^[0-9]*$/')
                                                            ->validationMessages([
                                                                'regex' => 'Solo números, sin espacios ni signos.',
                                                            ])
                                                            ->prefixIcon(Heroicon::OutlinedPhone)
                                                            ->placeholder('02419876543'),
                                                        Textarea::make('address')
                                                            ->label('Dirección')
                                                            ->rows(2)
                                                            ->maxLength(500)
                                                            ->placeholder('Calle, edificio, urbanización, punto de referencia...')
                                                            ->columnSpanFull(),
                                                    ])
                                                    ->columns(['default' => 1, 'md' => 2, 'xl' => 3])
                                                    ->defaultItems(0)
                                                    ->minItems(0)
                                                    ->addActionLabel('Agregar sucursal')
                                                    ->reorderable()
                                                    ->orderColumn('sort_order')
                                                    ->mutateRelationshipDataBeforeCreateUsing(self::discardEmptyBranch(...))
                                                    ->mutateRelationshipDataBeforeSaveUsing(self::discardEmptyBranch(...))
                                                    ->collapsible()
                                                    ->collapsed(fn (?Schema $item): bool => self::isSavedRepeaterItem($item))
                                                    ->itemLabel(fn (array $state): string => filled(trim((string) ($state['name'] ?? '')))
                                                        ? trim((string) $state['name'])
                                                        : 'Nueva sucursal')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Zonas de cobertura')
                            ->icon(Heroicon::OutlinedGlobeAmericas)
                            ->schema([
                                Section::make('Zonas de cobertura')
                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                    ->description('Dónde atiende o vende el prospecto. Si dejas la ciudad vacía, la zona cubre todo el estado.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Repeater::make('prospectAgentCoverageZones')
                                                    ->relationship()
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('Clasificación'),
                                                        TableColumn::make('Tipo de servicio'),
                                                        TableColumn::make('Estado')->markAsRequired(),
                                                        TableColumn::make('Ciudad'),
                                                    ])
                                                    ->schema([
                                                        TextInput::make('classification')
                                                            ->label('Clasificación')
                                                            ->maxLength(255)
                                                            ->placeholder('Ej. Corretaje'),
                                                        TextInput::make('service_type')
                                                            ->label('Tipo de servicio')
                                                            ->maxLength(255)
                                                            ->placeholder('Ej. Venta de planes'),
                                                        Select::make('state_id')
                                                            ->label('Estado')
                                                            ->options(fn (Get $get): array => self::branchStateOptions($get('../../country_id')))
                                                            ->required(fn (Get $get): bool => self::coverageZoneHasData([
                                                                'classification' => $get('classification'),
                                                                'service_type' => $get('service_type'),
                                                                'city_id' => $get('city_id'),
                                                            ]))
                                                            ->validationMessages([
                                                                'required' => 'Selecciona el estado de esta zona.',
                                                            ])
                                                            ->live()
                                                            ->afterStateUpdated(fn (Set $set): mixed => $set('city_id', null))
                                                            ->searchable()
                                                            ->preload()
                                                            ->native(false)
                                                            ->placeholder('Selecciona un estado'),
                                                        Select::make('city_id')
                                                            ->label('Ciudad')
                                                            ->options(fn (Get $get): array => self::branchCityOptions($get('state_id')))
                                                            ->disabled(fn (Get $get): bool => blank($get('state_id')))
                                                            ->searchable()
                                                            ->preload()
                                                            ->native(false)
                                                            ->placeholder('Todo el estado'),
                                                    ])
                                                    ->defaultItems(0)
                                                    ->minItems(0)
                                                    ->addActionLabel('Agregar zona')
                                                    ->reorderable()
                                                    ->orderColumn('sort_order')
                                                    ->mutateRelationshipDataBeforeCreateUsing(self::discardEmptyCoverageZone(...))
                                                    ->mutateRelationshipDataBeforeSaveUsing(self::discardEmptyCoverageZone(...))
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Embudo comercial')
                            ->icon(Heroicon::OutlinedChartBar)
                            ->schema([
                                Section::make('Embudo comercial')
                                    ->icon(Heroicon::OutlinedChartBar)
                                    ->description('Etapa actual del prospecto en el proceso de captación.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                Select::make('status')
                                                    ->label('Estatus')
                                                    ->options(ProspectAgentLabels::statusOptions())
                                                    ->default(ProspectAgentLabels::defaultStatus())
                                                    ->required()
                                                    ->preload()
                                                    ->searchable()
                                                    ->native(false)
                                                    ->placeholder('Selecciona el estatus')
                                                    ->columnSpanFull(),
                                                Textarea::make('initial_observ')
                                                    ->label('Observaciones iniciales')
                                                    ->maxLength(100)
                                                    ->rows(6)
                                                    ->columnSpanFull()
                                                    ->placeholder('Notas breves sobre el primer contacto o contexto.'),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->description('País, estado, ciudad y dirección del prospecto.')
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
                                                    ->columns(['default' => 1, 'md' => 3])
                                                    ->schema([
                                                        Select::make('country_id')
                                                            ->label('País')
                                                            ->live()
                                                            ->options(
                                                                Country::query()
                                                                    ->orderBy('name')
                                                                    ->pluck('name', 'id')
                                                            )
                                                            ->searchable()
                                                            ->prefixIcon(Heroicon::OutlinedGlobeAmericas)
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Selecciona un país.',
                                                            ])
                                                            ->default(self::DEFAULT_COUNTRY_ID)
                                                            ->preload()
                                                            ->native(false),
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
                                                            ->live()
                                                            ->searchable()
                                                            ->prefixIcon(Heroicon::OutlinedMap)
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Selecciona un estado.',
                                                            ])
                                                            ->preload()
                                                            ->native(false),
                                                        Select::make('city_id')
                                                            ->label('Ciudad')
                                                            ->options(function (Get $get): array {
                                                                $countryId = $get('country_id');
                                                                $stateId = $get('state_id');

                                                                if (blank($countryId) || blank($stateId)) {
                                                                    return [];
                                                                }

                                                                return City::query()
                                                                    ->where('country_id', $countryId)
                                                                    ->where('state_id', $stateId)
                                                                    ->orderBy('definition')
                                                                    ->pluck('definition', 'id')
                                                                    ->all();
                                                            })
                                                            ->searchable()
                                                            ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                                            ->required()
                                                            ->validationMessages([
                                                                'required' => 'Selecciona una ciudad.',
                                                            ])
                                                            ->preload()
                                                            ->native(false),
                                                    ]),
                                                Textarea::make('address')
                                                    ->label('Dirección')
                                                    ->rows(3)
                                                    ->maxLength(500)
                                                    ->placeholder('Calle, edificio, urbanización, punto de referencia...')
                                                    ->helperText('Dirección física o comercial del prospecto.')
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Segmentación')
                            ->icon(Heroicon::OutlinedTag)
                            ->schema([
                                Section::make('Segmentación')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->description('Define el tipo de prospecto y el canal por el que llegó.')
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
                                                        Select::make('type')
                                                            ->label('Tipo de prospecto')
                                                            ->options(ProspectAgentLabels::typeOptions())
                                                            ->required()
                                                            ->preload()
                                                            ->searchable()
                                                            ->native(false)
                                                            ->placeholder('Selecciona un tipo'),
                                                        Select::make('reference_by')
                                                            ->label('Referido por')
                                                            ->options(ProspectAgentLabels::referenceOptions())
                                                            ->required()
                                                            ->preload()
                                                            ->searchable()
                                                            ->native(false)
                                                            ->placeholder('Selecciona el origen'),
                                                    ]),
                                                Grid::make()
                                                    ->columns(['default' => 1, 'lg' => 2])
                                                    ->schema([
                                                        TextInput::make('classification')
                                                            ->label('Clasificación')
                                                            ->maxLength(100)
                                                            ->placeholder('Ej. VIP, retail, etc.'),
                                                        TextInput::make('instagram')
                                                            ->label('Usuario de Instagram')
                                                            ->maxLength(100)
                                                            ->placeholder('@usuario o enlace del perfil'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),

                Hidden::make('created_by')
                    ->default(fn (): ?string => auth()->user()?->name)
                    ->hiddenOn('edit')
                    ->dehydrated()
                    ->required(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function discardEmptyContact(array $data): ?array
    {
        return self::contactHasData($data, includeName: true) ? $data : null;
    }

    /**
     * Indica si una fila de contacto trae algún dato; sin el nombre decide si este es obligatorio.
     *
     * @param  array<string, mixed>  $data
     */
    public static function contactHasData(array $data, bool $includeName = false): bool
    {
        $fields = ['department', 'position', 'phone', 'local_phone', 'extensions', 'email'];

        if ($includeName) {
            $fields[] = 'name';
        }

        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Estados del país elegido en la ubicación del prospecto; Venezuela si aún no se eligió.
     *
     * @return array<int|string, string>
     */
    public static function branchStateOptions(mixed $countryId): array
    {
        return State::query()
            ->where('country_id', filled($countryId) ? $countryId : self::DEFAULT_COUNTRY_ID)
            ->orderBy('definition')
            ->pluck('definition', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function branchCityOptions(mixed $stateId): array
    {
        if (blank($stateId)) {
            return [];
        }

        return City::query()
            ->where('state_id', $stateId)
            ->orderBy('definition')
            ->pluck('definition', 'id')
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function branchHasData(array $data, bool $includeName = false): bool
    {
        $fields = ['state_id', 'city_id', 'email', 'phone', 'local_phone', 'address'];

        if ($includeName) {
            $fields[] = 'name';
        }

        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function discardEmptyBranch(array $data): ?array
    {
        return self::branchHasData($data, includeName: true) ? $data : null;
    }

    /**
     * Una zona con clasificación, tipo de servicio o ciudad necesita su estado.
     *
     * @param  array<string, mixed>  $data
     */
    public static function coverageZoneHasData(array $data, bool $includeState = false): bool
    {
        $fields = ['classification', 'service_type', 'city_id'];

        if ($includeState) {
            $fields[] = 'state_id';
        }

        foreach ($fields as $field) {
            if (filled($data[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null
     */
    private static function discardEmptyCoverageZone(array $data): ?array
    {
        return self::coverageZoneHasData($data, includeState: true) ? $data : null;
    }

    /**
     * Al editar, los contactos y sucursales ya guardados se muestran plegados; uno recién agregado queda abierto para llenarlo.
     */
    public static function isSavedRepeaterItem(?Schema $item): bool
    {
        return $item?->getRecord()?->exists === true;
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function contactItemLabel(array $state): string
    {
        $name = trim((string) ($state['name'] ?? ''));
        $department = trim((string) ($state['department'] ?? ''));

        if ($name === '') {
            return 'Nuevo contacto';
        }

        return $department !== '' ? $name.' · '.$department : $name;
    }
}
