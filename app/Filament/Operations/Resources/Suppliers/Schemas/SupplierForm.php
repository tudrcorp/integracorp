<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Models\City;
use App\Models\State;
use App\Models\SupplierClasificacion;
use App\Models\SupplierEstatusSistema;
use App\Models\SupplierStatusConvenio;
use App\Models\SupplierTipoClinica;
use App\Models\SupplierTipoServicio;
use App\Support\Filament\Operations\SupplierBeneficiaryBankingForm;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class SupplierForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const REPEATER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/90 p-2 shadow-sm dark:border-white/10 dark:bg-slate-900/40';

    private static function auditHiddenFields(): array
    {
        return [
            Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
            Hidden::make('updated_by')
                ->default(fn (): string => Auth::user()?->name ?? '')
                ->hiddenOn('create'),
        ];
    }

    private static function phoneField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->placeholder('04141234567')
            ->helperText('11 dígitos, sin espacios ni guiones.')
            ->mask('99999999999')
            ->tel()
            ->maxLength(255)
            ->rules(['nullable', 'regex:/^\d{11}$/'])
            ->validationMessages([
                'regex' => 'Debe tener exactamente 11 dígitos, sin espacios ni guiones.',
            ]);
    }

    /**
     * @param  list<array{key: string, desc: string, label: string}>  $items
     */
    private static function infrastructureGrid(array $items): Grid
    {
        $components = [];
        foreach ($items as $item) {
            $toggleKey = $item['key'];
            $descKey = $item['desc'];
            $components[] = Toggle::make($toggleKey)
                ->label($item['label'])
                ->live()
                ->inline(false)
                ->onIcon('heroicon-s-check')
                ->onColor('success');
            $components[] = TextInput::make($descKey)
                ->label('Detalle opcional')
                ->placeholder('Observaciones del equipo o servicio')
                ->disabled(fn (Get $get): bool => ! $get($toggleKey))
                ->maxLength(500);
        }

        return Grid::make(2)
            ->extraAttributes([
                'class' => self::INNER_CARD,
            ])
            ->schema($components);
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('supplierFormTabs')
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Identificación y razón social')
                                    ->description('Nombre comercial, fiscal y datos legales básicos.')
                                    ->icon('heroicon-o-identification')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('name')
                                                    ->label('Nombre del proveedor')
                                                    ->placeholder('Ej: CENTRO MÉDICO CARACAS')
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('rif')
                                                    ->label('RIF')
                                                    ->placeholder('J-123456789')
                                                    ->mask('J999999999999'),
                                                TextInput::make('razon_social')
                                                    ->label('Razón social')
                                                    ->placeholder('Según documento fiscal')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('razon_social', $state.toUpperCase());
                                                    JS),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Clasificación y convenio')
                                    ->description('Tipo de relación contractual y categoría del proveedor en red.')
                                    ->icon('heroicon-o-tag')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Select::make('status_convenio')
                                                    ->label('Tipo de convenio')
                                                    ->options(SupplierStatusConvenio::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('status_sistema')
                                                    ->label('Estatus del convenio')
                                                    ->options(SupplierEstatusSistema::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('supplier_clasificacion_id')
                                                    ->label('Clasificación del proveedor')
                                                    ->options(SupplierClasificacion::query()->orderBy('description')->pluck('description', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->helperText('Define los tipos de servicio disponibles a continuación.'),
                                                Select::make('tipo_clinica')
                                                    ->label('Tipo de clínica')
                                                    ->options(SupplierTipoClinica::query()->orderBy('description')->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->preload(),
                                                Select::make('type_service')
                                                    ->label('Tipo de servicio')
                                                    ->options(fn (Get $get) => SupplierTipoServicio::query()
                                                        ->where('supplier_clasificacion_id', $get('supplier_clasificacion_id'))
                                                        ->orderBy('description')
                                                        ->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->multiple()
                                                    ->preload()
                                                    ->placeholder('Seleccione uno o más')
                                                    ->helperText('Opciones según la clasificación elegida.')
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Ubicación principal')
                                    ->description('Estado y ciudad de la sede principal.')
                                    ->icon('heroicon-o-map-pin')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(State::query()->orderBy('definition')->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(fn (Get $get) => City::query()
                                                        ->where('state_id', $get('state_id'))
                                                        ->orderBy('definition')
                                                        ->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(fn (Get $get): bool => blank($get('state_id')))
                                                    ->helperText('Elija primero el estado.'),
                                                TextInput::make('ubicacion_principal')
                                                    ->label('Dirección / ubicación principal')
                                                    ->placeholder('Av., urbanización, punto de referencia')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('ubicacion_principal', $state.toUpperCase());
                                                    JS),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Contacto')
                                    ->description('Teléfonos y correo principal del proveedor.')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::phoneField('local_phone', 'Teléfono local'),
                                                self::phoneField('personal_phone', 'Teléfono celular'),
                                                TextInput::make('correo_principal')
                                                    ->label('Correo electrónico principal')
                                                    ->placeholder('contacto@proveedor.com')
                                                    ->email()
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                                            ]),
                                    ])
                                    ->collapsible(),

                                Section::make('Condiciones comerciales')
                                    ->description('Pago, crédito y vigencia de la relación.')
                                    ->icon('heroicon-o-banknotes')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                TextInput::make('convenio_pago')
                                                    ->label('Convenio de pago')
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('convenio_pago', $state.toUpperCase());
                                                    JS),
                                                Select::make('tiempo_credito')
                                                    ->label('Tiempo de crédito')
                                                    ->searchable()
                                                    ->options([
                                                        '3 DIAS' => '3 días',
                                                        '5 DIAS' => '5 días',
                                                        '7 DIAS' => '7 días',
                                                        '10 DIAS' => '10 días',
                                                        '15 DIAS' => '15 días',
                                                        '20 DIAS' => '20 días',
                                                        '25 DIAS' => '25 días',
                                                        '30 DIAS' => '30 días',
                                                        'CONTADO' => 'Contado',
                                                        'FONDO ANTICIPADO' => 'Fondo anticipado',
                                                        'PREPAGO' => 'Prepago',
                                                    ]),
                                                TextInput::make('horario')
                                                    ->label('Horario de atención')
                                                    ->placeholder('Ej: LUN-VIE 8:00–18:00')
                                                    ->maxLength(255)
                                                    ->columnSpan(['default' => 1, 'lg' => 2])
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('horario', $state.toUpperCase());
                                                    JS),
                                                DatePicker::make('afiliacion_proveedor')
                                                    ->label('Fecha de afiliación del proveedor')
                                                    ->native(false)
                                                    ->displayFormat('d/m/Y')
                                                    ->format('d/m/Y'),
                                                Textarea::make('otros_servicios')
                                                    ->label('Otros servicios')
                                                    ->placeholder('Servicios adicionales no listados arriba')
                                                    ->rows(3)
                                                    ->columnSpanFull()
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('otros_servicios', $state.toUpperCase());
                                                    JS),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Cobertura territorial')
                            ->icon('heroicon-o-globe-americas')
                            ->schema([
                                Section::make('Alcance geográfico')
                                    ->description('Indique si la cobertura es local, multiestado o nacional.')
                                    ->icon('heroicon-o-map')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                Select::make('tipo_servicio')
                                                    ->label('Zona de cobertura')
                                                    ->live()
                                                    ->searchable()
                                                    ->options([
                                                        'LOCAL' => 'Local',
                                                        'MULTI-ESTADO' => 'Multi-estado',
                                                        'A-NIVEL-NACIONAL' => 'A nivel nacional',
                                                    ])
                                                    ->helperText('En cobertura nacional se incluyen todos los estados automáticamente.')
                                                    ->afterStateUpdated(
                                                        fn (Set $set, ?string $state) => $set(
                                                            'state_services',
                                                            $state === 'A-NIVEL-NACIONAL'
                                                                ? State::query()->orderBy('definition')->pluck('definition')->all()
                                                                : null
                                                        )
                                                    ),
                                                Select::make('state_services')
                                                    ->label('Estados incluidos en la cobertura')
                                                    ->multiple()
                                                    ->searchable()
                                                    ->options(State::query()->orderBy('definition')->pluck('definition', 'definition'))
                                                    ->preload()
                                                    ->visible(fn (Get $get): bool => in_array($get('tipo_servicio'), ['LOCAL', 'MULTI-ESTADO'], true))
                                                    ->disabled(fn (Get $get): bool => $get('tipo_servicio') === 'A-NIVEL-NACIONAL')
                                                    ->helperText(fn (Get $get): string => match ($get('tipo_servicio')) {
                                                        'MULTI-ESTADO' => 'Seleccione todos los estados donde opera.',
                                                        'LOCAL' => 'Puede acotar o ampliar estados según su operación.',
                                                        default => '',
                                                    }),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Equipamiento')
                            ->icon('heroicon-o-wrench-screwdriver')
                            ->schema([
                                Section::make('Infraestructura y equipos')
                                    ->description('Active solo lo que aplica; el detalle es opcional.')
                                    ->icon('heroicon-o-cube')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Fieldset::make('Diagnóstico e imagen')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::infrastructureGrid([
                                                    ['key' => 'densitometria_osea', 'desc' => 'descripcion_densitometria_osea', 'label' => 'Densitómetro'],
                                                    ['key' => 'dialisis', 'desc' => 'descripcion_dialisis', 'label' => 'Equipo de diálisis'],
                                                    ['key' => 'electrocardiograma_centro', 'desc' => 'descripcion_electrocardiograma_centro', 'label' => 'Electrocardiógrafo'],
                                                    ['key' => 'equipos_especiales_oftalmologia', 'desc' => 'descripcion_equipos_especiales_oftalmologia', 'label' => 'Equipos especiales de oftalmología'],
                                                    ['key' => 'mamografia', 'desc' => 'descripcion_mamografia', 'label' => 'Mamógrafo'],
                                                    ['key' => 'resonancia', 'desc' => 'descripcion_resonancia', 'label' => 'Resonancia'],
                                                    ['key' => 'tomografo', 'desc' => 'descripcion_tomografo', 'label' => 'Tomógrafo'],
                                                    ['key' => 'radioterapia_intraoperatoria', 'desc' => 'descripcion_radioterapia_intraoperatoria', 'label' => 'Radioterapia intraoperatoria'],
                                                ]),
                                            ]),
                                        Fieldset::make('Hospitalización y cirugía')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::infrastructureGrid([
                                                    ['key' => 'urgen_care', 'desc' => 'descripcion_urgen_care', 'label' => 'Urgencias'],
                                                    ['key' => 'consulta_aps', 'desc' => 'descripcion_consulta_aps', 'label' => 'Consultas APS'],
                                                    ['key' => 'amd', 'desc' => 'descripcion_amd', 'label' => 'Asistencia médica domiciliaria'],
                                                    ['key' => 'laboratorio_centro', 'desc' => 'descripcion_laboratorio_centro', 'label' => 'Laboratorio en centro'],
                                                    ['key' => 'laboratorio_domicilio', 'desc' => 'descripcion_laboratorio_domicilio', 'label' => 'Laboratorio en domicilio'],
                                                    ['key' => 'rx_centro', 'desc' => 'descripcion_rx_centro', 'label' => 'Rayos X en centro'],
                                                    ['key' => 'rx_domicilio', 'desc' => 'descripcion_rx_domicilio', 'label' => 'Rayos X en domicilio'],
                                                    ['key' => 'eco_abdominal_centro', 'desc' => 'descripcion_eco_abdominal_centro', 'label' => 'Ecografía abdominal en centro'],
                                                    ['key' => 'eco_abdominal_domicilio', 'desc' => 'descripcion_eco_abdominal_domicilio', 'label' => 'Ecografía abdominal en domicilio'],
                                                    ['key' => 'electrocardiograma_domicilio', 'desc' => 'descripcion_electrocardiograma_domicilio', 'label' => 'Electrocardiograma en domicilio'],
                                                    ['key' => 'oncologia', 'desc' => 'descripcion_oncologia', 'label' => 'Oncología'],
                                                    ['key' => 'uci_uten', 'desc' => 'descripcion_uci_uten', 'label' => 'UCI UTE'],
                                                    ['key' => 'neonatal', 'desc' => 'descripcion_neonatal', 'label' => 'Neonatal'],
                                                    ['key' => 'ambulancias', 'desc' => 'descripcion_ambulancias', 'label' => 'Ambulancias'],
                                                    ['key' => 'odontologia', 'desc' => 'descripcion_odontologia', 'label' => 'Odontología'],
                                                    ['key' => 'oftalmologia', 'desc' => 'descripcion_oftalmologia', 'label' => 'Oftalmología'],
                                                    ['key' => 'quirofanos', 'desc' => 'descripcion_quirofanos', 'label' => 'Quirófanos'],
                                                    ['key' => 'uci_pediatrica', 'desc' => 'descripcion_uci_pediatrica', 'label' => 'UCI pediátrica'],
                                                    ['key' => 'uci_adulto', 'desc' => 'descripcion_uci_adulto', 'label' => 'UCI adulto'],
                                                    ['key' => 'robotica', 'desc' => 'descripcion_robotica', 'label' => 'Cirugía robótica'],
                                                    ['key' => 'otras_unidades_especiales', 'desc' => 'descripcion_otras_unidades_especiales', 'label' => 'Otras unidades especiales'],
                                                ]),
                                            ]),
                                        Fieldset::make('Accesibilidad y comodidades')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::infrastructureGrid([
                                                    ['key' => 'estacionamiento_propio', 'desc' => 'descripcion_estacionamiento_propio', 'label' => 'Estacionamiento propio'],
                                                    ['key' => 'ascensor', 'desc' => 'descripcion_ascensor', 'label' => 'Ascensor operativo'],
                                                ]),
                                            ]),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Contactos')
                            ->icon('heroicon-o-user-group')
                            ->schema([
                                Section::make('Personas de contacto')
                                    ->description('Responsables por departamento o área.')
                                    ->icon('heroicon-o-users')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('supplierContactPrincipals')
                                            ->label('Contactos principales')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Departamento'),
                                                TableColumn::make('Cargo'),
                                                TableColumn::make('Nombre y apellido'),
                                                TableColumn::make('Correo'),
                                                TableColumn::make('Celular'),
                                                TableColumn::make('Teléfono local'),
                                                TableColumn::make('Extensión'),
                                            ])
                                            ->schema([
                                                TextInput::make('departament')
                                                    ->label('Departamento')
                                                    ->placeholder('Ej: FACTURACIÓN')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('departament', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('position')
                                                    ->label('Cargo')
                                                    ->placeholder('Ej: COORDINADOR')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('position', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('name')
                                                    ->label('Nombre y apellido')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('email')
                                                    ->label('Correo')
                                                    ->email(),
                                                self::phoneField('personal_phone', 'Teléfono celular'),
                                                self::phoneField('local_phone', 'Teléfono local'),
                                                TextInput::make('extensions')
                                                    ->label('Extensión(es)')
                                                    ->placeholder('Ej: 101, 102'),
                                                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
                                                Hidden::make('updated_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->hiddenOn('create'),
                                            ])
                                            ->addActionLabel('Agregar contacto')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Sucursales')
                            ->icon('heroicon-o-building-storefront')
                            ->schema([
                                Section::make('Red de sucursales')
                                    ->description('Otras sedes asociadas al proveedor.')
                                    ->icon('heroicon-o-map')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('supplierRedGlobals')
                                            ->label('Sucursales')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Estado'),
                                                TableColumn::make('Ciudad'),
                                                TableColumn::make('Nombre / razón social'),
                                                TableColumn::make('Correo'),
                                                TableColumn::make('Celular'),
                                                TableColumn::make('Teléfono local'),
                                                TableColumn::make('Dirección'),
                                            ])
                                            ->schema([
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(State::query()->orderBy('definition')->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(fn (Get $get) => City::query()
                                                        ->where('state_id', $get('state_id'))
                                                        ->orderBy('definition')
                                                        ->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(fn (Get $get): bool => blank($get('state_id'))),
                                                TextInput::make('name')
                                                    ->label('Nombre o razón social')
                                                    ->maxLength(255)
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('name', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('email')
                                                    ->label('Correo electrónico')
                                                    ->email()
                                                    ->maxLength(255),
                                                self::phoneField('personal_phone', 'Teléfono celular'),
                                                self::phoneField('local_phone', 'Teléfono local'),
                                                TextInput::make('address')
                                                    ->label('Dirección')
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('address', $state.toUpperCase());
                                                    JS),
                                                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
                                                Hidden::make('updated_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->hiddenOn('create'),
                                            ])
                                            ->addActionLabel('Agregar sucursal')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),

                        Tab::make('Zonas de servicio')
                            ->icon('heroicon-o-squares-2x2')
                            ->schema([
                                Section::make('Cobertura por clasificación')
                                    ->description('Combinaciones de clasificación, tipo de servicio y ubicación.')
                                    ->icon('heroicon-o-rectangle-group')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('SupplierZonaCoberturas')
                                            ->label('Zonas de cobertura')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Clasificación'),
                                                TableColumn::make('Tipo de servicio'),
                                                TableColumn::make('Estado'),
                                                TableColumn::make('Ciudad'),
                                            ])
                                            ->schema([
                                                Select::make('clasificacion_id')
                                                    ->label('Clasificación del proveedor')
                                                    ->options(SupplierClasificacion::query()->orderBy('description')->pluck('description', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live(),
                                                Select::make('type_service')
                                                    ->label('Tipo de servicio')
                                                    ->options(fn (Get $get) => SupplierTipoServicio::query()
                                                        ->where('supplier_clasificacion_id', $get('clasificacion_id'))
                                                        ->orderBy('description')
                                                        ->pluck('description', 'description'))
                                                    ->searchable()
                                                    ->multiple()
                                                    ->preload(),
                                                Select::make('state_id')
                                                    ->label('Estado')
                                                    ->options(State::query()->orderBy('definition')->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->live()
                                                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                                                Select::make('city_id')
                                                    ->label('Ciudad')
                                                    ->options(fn (Get $get) => City::query()
                                                        ->where('state_id', $get('state_id'))
                                                        ->pluck('definition', 'id'))
                                                    ->searchable()
                                                    ->preload()
                                                    ->disabled(fn (Get $get): bool => blank($get('state_id'))),
                                                Hidden::make('created_by')->default(fn (): string => Auth::user()?->name ?? ''),
                                                Hidden::make('updated_by')
                                                    ->default(fn (): string => Auth::user()?->name ?? '')
                                                    ->hiddenOn('create'),
                                            ])
                                            ->addActionLabel('Agregar zona')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),

                        SupplierBeneficiaryBankingForm::bankingTab(self::SECTION_CARD, self::INNER_CARD),

                        Tab::make('Notas')
                            ->icon('heroicon-o-chat-bubble-left-right')
                            ->schema([
                                Section::make('Bitácora')
                                    ->description('Observaciones internas sobre el proveedor.')
                                    ->icon('heroicon-o-clipboard-document-list')
                                    ->extraAttributes(['class' => self::SECTION_CARD])
                                    ->schema([
                                        Repeater::make('supplierObservacions')
                                            ->label('Notas y observaciones')
                                            ->relationship()
                                            ->extraAttributes([
                                                'class' => self::REPEATER_CARD,
                                            ])
                                            ->table([
                                                TableColumn::make('Nota')->width('90%'),
                                                TableColumn::make('Responsable')->width('10%'),
                                            ])
                                            ->schema([
                                                Textarea::make('observation')
                                                    ->label('Nota')
                                                    ->autosize()
                                                    ->afterStateUpdatedJs(<<<'JS'
                                                        $set('observation', $state.toUpperCase());
                                                    JS),
                                                TextInput::make('created_by')
                                                    ->label('Responsable')
                                                    ->disabled()
                                                    ->dehydrated()
                                                    ->default(fn (): string => Auth::user()?->name ?? ''),
                                            ])
                                            ->addActionLabel('Agregar nota')
                                            ->columnSpanFull()
                                            ->defaultItems(0)
                                            ->collapsed()
                                            ->reorderable(),
                                    ])
                                    ->collapsible(),
                            ]),
                    ]),

                ...self::auditHiddenFields(),
            ]);
    }
}
