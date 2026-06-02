<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\DoctorNurses\Schemas;

use App\Models\SupplierClasificacion;
use App\Support\Filament\Operations\SupplierBeneficiaryBankingForm;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class DoctorNurseForm
{
    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const INNER_CARD = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    /**
     * @return array<string, list<array{key: string, desc: string, label: string}>>
     */
    private static function homeCareEquipmentCatalog(): array
    {
        return [
            'instrumental' => [
                ['key' => 'equip_diag_vital_signs', 'desc' => 'equip_desc_diag_vital_signs', 'label' => 'Estetoscopio y tensiómetro'],
                ['key' => 'equip_diag_oximeter', 'desc' => 'equip_desc_diag_oximeter', 'label' => 'Oxímetro de pulso'],
                ['key' => 'equip_diag_thermometer', 'desc' => 'equip_desc_diag_thermometer', 'label' => 'Termómetro digital o infrarrojo'],
                ['key' => 'equip_diag_exam_kit', 'desc' => 'equip_desc_diag_exam_kit', 'label' => 'Estuche de diagnóstico (otoscopio/oftalmoscopio)'],
                ['key' => 'equip_diag_glucometer', 'desc' => 'equip_desc_diag_glucometer', 'label' => 'Glucómetro'],
                ['key' => 'equip_diag_flashlight_hammer', 'desc' => 'equip_desc_diag_flashlight_hammer', 'label' => 'Linterna de exploración y martillo de reflejos'],
            ],
            'material_descartable' => [
                ['key' => 'equip_care_gloves', 'desc' => 'equip_desc_care_gloves', 'label' => 'Guantes de nitrilo o látex'],
                ['key' => 'equip_care_antiseptics', 'desc' => 'equip_desc_care_antiseptics', 'label' => 'Antisépticos y limpieza'],
                ['key' => 'equip_care_supplies', 'desc' => 'equip_desc_care_supplies', 'label' => 'Material de cura'],
                ['key' => 'equip_care_sharps_container', 'desc' => 'equip_desc_care_sharps_container', 'label' => 'Contenedor de punzocortantes'],
            ],
            'apoyo_seguridad' => [
                ['key' => 'equip_support_hygiene', 'desc' => 'equip_desc_support_hygiene', 'label' => 'Desinfectante de manos y jabón'],
                ['key' => 'equip_support_scissors_forceps', 'desc' => 'equip_desc_support_scissors_forceps', 'label' => 'Tijeras y pinzas'],
                ['key' => 'equip_support_prescriptions_stamps', 'desc' => 'equip_desc_support_prescriptions_stamps', 'label' => 'Recetas médicas y sellos profesionales'],
            ],
            'avanzados_urgencia' => [
                ['key' => 'equip_adv_basic_medicines', 'desc' => 'equip_desc_adv_basic_medicines', 'label' => 'Medicamentos básicos'],
                ['key' => 'equip_adv_catheters_aspiration', 'desc' => 'equip_desc_adv_catheters_aspiration', 'label' => 'Sondas y material de aspiración'],
                ['key' => 'equip_adv_emergency_bag', 'desc' => 'equip_desc_adv_emergency_bag', 'label' => 'Maletín de urgencias'],
            ],
        ];
    }

    /**
     * @param  list<array{key: string, desc: string, label: string}>  $items
     */
    private static function homeCareEquipmentGrid(array $items): Grid
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
                ->onColor('success')
                ->afterStateUpdated(function (?bool $state, callable $set) use ($descKey): void {
                    if (! $state) {
                        $set($descKey, null);
                    }
                });

            $components[] = TextInput::make($descKey)
                ->label('Detalle opcional')
                ->placeholder('Observaciones o alcance del insumo/equipo')
                ->hidden(fn (Get $get): bool => ! $get($toggleKey))
                ->dehydrated()
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
        $equipmentCatalog = self::homeCareEquipmentCatalog();

        return $schema
            ->components([
                Tabs::make('doctorNurseFormTabs')
                    ->columnSpanFull()
                    ->persistTabInQueryString()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Datos principales')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Section::make('Identidad del proveedor natural')
                                    ->description('Información principal y datos fiscales del prestador.')
                                    ->icon('heroicon-o-user-circle')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema(self::identityFields()),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Ubicación y operación')
                            ->icon('heroicon-o-map-pin')
                            ->schema([
                                Section::make('Ubicación y operación')
                                    ->description('Cobertura, estatus y horario operativo.')
                                    ->icon('heroicon-o-map-pin')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema(self::locationFields()),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Contacto y condiciones')
                            ->icon('heroicon-o-phone')
                            ->schema([
                                Section::make('Contacto y condiciones comerciales')
                                    ->description('Canales de contacto y términos administrativos.')
                                    ->icon('heroicon-o-phone')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema(self::contactFields()),
                                    ])
                                    ->columnSpanFull(),
                            ]),

                        Tab::make('Equipamiento')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Placeholder::make('doctor_nurse_equipment_intro')
                                    ->hiddenLabel()
                                    ->content(new HtmlString(
                                        '<p class="text-sm leading-relaxed text-gray-600 dark:text-gray-300">'
                                        .'<span class="font-semibold text-gray-900 dark:text-white">Equipamiento.</span> '
                                        .'Marca el equipamiento esencial para atención domiciliaria y agrega detalles cuando aplique.'
                                        .'</p>'
                                    ))
                                    ->columnSpanFull(),

                                Section::make('Equipamiento para atención domiciliaria')
                                    ->description('Certificación de infraestructura esencial, material descartable y recursos de urgencia.')
                                    ->icon('heroicon-o-cpu-chip')
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Fieldset::make('Instrumental de diagnóstico')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::homeCareEquipmentGrid($equipmentCatalog['instrumental']),
                                            ]),
                                        Fieldset::make('Material descartable de cura')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::homeCareEquipmentGrid($equipmentCatalog['material_descartable']),
                                            ]),
                                        Fieldset::make('Equipamiento de apoyo y seguridad')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::homeCareEquipmentGrid($equipmentCatalog['apoyo_seguridad']),
                                            ]),
                                        Fieldset::make('Elementos avanzados o de urgencia')
                                            ->extraAttributes([
                                                'class' => self::INNER_CARD,
                                            ])
                                            ->schema([
                                                self::homeCareEquipmentGrid($equipmentCatalog['avanzados_urgencia']),
                                            ]),
                                    ])
                                    ->collapsible()
                                    ->columnSpanFull(),
                            ]),

                        SupplierBeneficiaryBankingForm::bankingTab(self::SECTION_CARD, self::INNER_CARD),
                    ]),

                Hidden::make('created_by')
                    ->default(static fn (): ?string => Auth::user()?->name),
                Hidden::make('updated_by')
                    ->default(static fn (): ?string => Auth::user()?->name),
            ]);
    }

    /**
     * @return array<int, TextInput|Select>
     */
    private static function identityFields(): array
    {
        return [
            TextInput::make('name')
                ->label('Nombre comercial')
                ->required()
                ->maxLength(255)
                ->placeholder('Clínica o nombre del especialista')
                ->prefixIcon('heroicon-o-building-storefront')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('name', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('razon_social')
                ->label('Razón social')
                ->maxLength(255)
                ->placeholder('Razón social o nombre fiscal')
                ->prefixIcon('heroicon-o-document-text')
                ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2])
                ->afterStateUpdatedJs(<<<'JS'
                    $set('razon_social', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('rif')
                ->label('RIF')
                ->required()
                ->maxLength(30)
                ->placeholder('J-12345678-9')
                ->prefixIcon('heroicon-o-identification')
                ->helperText('Formato recomendado: J-12345678-9')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('rif', ($state ?? '').toUpperCase());
                JS),
            Select::make('supplier_clasificacion_id')
                ->label('Clasificación')
                ->relationship('supplierClasificacion', 'description')
                ->searchable()
                ->preload()
                ->options(fn () => SupplierClasificacion::query()->orderBy('description')->pluck('description', 'id'))
                ->placeholder('Seleccione una clasificación'),
            TextInput::make('tipo_clinica')
                ->label('Tipo de clínica')
                ->maxLength(255)
                ->placeholder('Centro médico, laboratorio, consultorio...')
                ->prefixIcon('heroicon-o-rectangle-group')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('tipo_clinica', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('speciality')
                ->label('Especialidad')
                ->maxLength(255)
                ->placeholder('Cardiología, traumatología, medicina general...')
                ->prefixIcon('heroicon-o-academic-cap')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('speciality', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('afiliacion_proveedor')
                ->label('Afiliación del proveedor')
                ->maxLength(255)
                ->placeholder('Fecha o referencia de afiliación')
                ->prefixIcon('heroicon-o-calendar'),
        ];
    }

    /**
     * @return array<int, TextInput|Select>
     */
    private static function locationFields(): array
    {
        return [
            TextInput::make('state')
                ->label('Estado')
                ->maxLength(255)
                ->placeholder('Estado')
                ->prefixIcon('heroicon-o-globe-americas')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('state', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('city')
                ->label('Ciudad')
                ->maxLength(255)
                ->placeholder('Ciudad')
                ->prefixIcon('heroicon-o-map')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('city', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('coverage_zone')
                ->label('Zona de cobertura')
                ->maxLength(255)
                ->placeholder('Municipio, parroquia o región')
                ->prefixIcon('heroicon-o-map-pin')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('coverage_zone', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('ubicacion_principal')
                ->label('Dirección principal')
                ->maxLength(255)
                ->placeholder('Dirección física principal')
                ->prefixIcon('heroicon-o-home-modern')
                ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2]),
            TextInput::make('horario')
                ->label('Horario')
                ->maxLength(255)
                ->placeholder('L-V 8:00 AM - 5:00 PM')
                ->prefixIcon('heroicon-o-clock'),
            Select::make('status_convenio')
                ->label('Estatus del convenio')
                ->options([
                    'ACTIVO' => 'Activo',
                    'PENDIENTE' => 'Pendiente',
                    'INACTIVO' => 'Inactivo',
                ])
                ->searchable()
                ->placeholder('Seleccione estatus'),
            Select::make('status_sistema')
                ->label('Estatus en sistema')
                ->options([
                    'ACTIVO' => 'Activo',
                    'PENDIENTE' => 'Pendiente',
                    'INACTIVO' => 'Inactivo',
                ])
                ->searchable()
                ->placeholder('Seleccione estatus'),
        ];
    }

    /**
     * @return array<int, TextInput>
     */
    private static function contactFields(): array
    {
        return [
            TextInput::make('personal_phone')
                ->label('Teléfono personal')
                ->tel()
                ->maxLength(30)
                ->placeholder('0414-0000000')
                ->prefixIcon('heroicon-o-device-phone-mobile'),
            TextInput::make('local_phone')
                ->label('Teléfono local')
                ->tel()
                ->maxLength(30)
                ->placeholder('0212-0000000')
                ->prefixIcon('heroicon-o-phone'),
            TextInput::make('correo_principal')
                ->label('Correo principal')
                ->email()
                ->maxLength(255)
                ->placeholder('correo@dominio.com')
                ->prefixIcon('heroicon-o-envelope')
                ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2]),
            TextInput::make('convenio_pago')
                ->label('Convenio de pago')
                ->maxLength(255)
                ->placeholder('Contado, crédito, mixto...')
                ->prefixIcon('heroicon-o-banknotes')
                ->afterStateUpdatedJs(<<<'JS'
                    $set('convenio_pago', ($state ?? '').toUpperCase());
                JS),
            TextInput::make('tiempo_credito')
                ->label('Tiempo de crédito')
                ->maxLength(255)
                ->placeholder('30 días, 45 días...')
                ->prefixIcon('heroicon-o-credit-card'),
        ];
    }
}
