<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Filament\Operations\Resources\OperationServiceOrders\OperationServiceOrderResource;
use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;
use App\Filament\Operations\Support\OperationsLocationMapAction;
use App\Models\OperationServiceOrder;
use App\Models\Supplier;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const SECTION_CARD = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_TABLE_WRAP_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm dark:border-white/10 dark:bg-gray-900/40 overflow-hidden';

    /** Estilo tipo “tarjeta” iOS (grupo inset) para órdenes de servicio. */
    private const IOS_ORDERS_SECTION_CLASS = 'rounded-[1.75rem] border border-sky-200/70 bg-gradient-to-br from-sky-50/95 via-white to-slate-50/90 shadow-[0_24px_60px_-24px_rgba(14,165,233,0.35)] ring-1 ring-sky-400/15 backdrop-blur-sm dark:from-sky-950/40 dark:via-gray-900/95 dark:to-slate-950 dark:border-sky-500/25 dark:ring-sky-400/20 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.5)]';

    private const IOS_ORDERS_FRAME_CLASS = 'rounded-[1.25rem] border border-sky-100/90 bg-white/65 p-3 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.95)] dark:border-white/10 dark:bg-slate-900/45 dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.06)] sm:p-4';

    private const IOS_ORDERS_TABLE_WRAP_CLASS = 'rounded-2xl border border-sky-200/65 bg-white/95 shadow-[0_8px_30px_-12px_rgba(14,165,233,0.28)] dark:border-sky-500/25 dark:bg-gray-950/55 overflow-hidden';

    private static function statusBadgeColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'VIGENTE', 'APROBADO' => 'success',
            'PENDIENTE', 'POR REVISION', 'EN REVISIÓN' => 'warning',
            'INACTIVO', 'SUSPENDIDO', 'RECHAZADO' => 'danger',
            default => 'gray',
        };
    }

    private static function infraDescription(Supplier $record, string $field): string
    {
        $text = (string) ($record->{$field} ?? '');

        return $text !== '' ? 'Descripción: '.$text : 'Sin descripción registrada.';
    }

    private static function operationServiceOrderStatusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'EN GESTION', 'EN GESTIÓN' => 'primary',
            'FINALIZADO' => 'success',
            'PENDIENTE' => 'warning',
            'CANCELADO' => 'gray',
            default => 'gray',
        };
    }

    private static function operationServiceOrderPriorityColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'NO URGENTE' => 'no-urgente',
            'ESTANDAR', 'ESTÁNDAR' => 'estandar',
            'URGENCIA' => 'urgencia',
            'EMERGENCIA' => 'emergencia',
            'CRITICO', 'CRÍTICO' => 'critico',
            default => 'gray',
        };
    }

    private static function formatMoneyUsd(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return 'US$ '.number_format((float) $state, 2, ',', '.');
    }

    private static function formatMoneyVes(mixed $state): ?string
    {
        if ($state === null || $state === '') {
            return null;
        }

        return 'Bs. '.number_format((float) $state, 2, ',', '.');
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('supplierInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Proveedor')
                            ->icon('heroicon-o-building-office-2')
                            ->schema([
                                Section::make('Proveedor')
                                    ->description('Información general, ubicación, contacto y condiciones comerciales.')
                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('name')
                                                    ->label('Nombre del proveedor')
                                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                                    ->weight('semibold')
                                                    ->size('lg')
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('rif')
                                                    ->label('RIF')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->badge()
                                                    ->color('info')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('razon_social')
                                                    ->label('Razón social')
                                                    ->icon(Heroicon::OutlinedDocumentText)
                                                    ->weight('medium')
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('status_convenio')
                                                    ->label('Estatus del convenio')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('status_sistema')
                                                    ->label('Estatus en sistema')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('SupplierClasificacion.description')
                                                    ->label('Clasificación')
                                                    ->icon(Heroicon::OutlinedTag)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->placeholder('—'),
                                                TextEntry::make('tipo_clinica')
                                                    ->label('Categoría del proveedor')
                                                    ->icon(Heroicon::OutlinedRectangleGroup)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('type_service')
                                                    ->label('Tipo de servicio')
                                                    ->getStateUsing(function (Supplier $record): ?array {
                                                        $normalized = SuppliersTable::normalizeJsonListField($record->type_service);

                                                        return ($normalized === null || $normalized === []) ? null : $normalized;
                                                    })
                                                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                                                    ->badge()
                                                    ->color('success')
                                                    ->listWithLineBreaks()
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedMap)
                                                    ->placeholder('—'),
                                                TextEntry::make('tipo_servicio')
                                                    ->label('Zona de cobertura')
                                                    ->formatStateUsing(function (mixed $state): ?string {
                                                        if ($state === null || $state === '') {
                                                            return null;
                                                        }
                                                        if (is_array($state)) {
                                                            $flat = SuppliersTable::normalizeJsonListField($state);

                                                            return ($flat === null || $flat === []) ? null : implode(', ', $flat);
                                                        }

                                                        $normalized = SuppliersTable::normalizeJsonListField($state);

                                                        return $normalized !== null && $normalized !== []
                                                            ? implode(', ', $normalized)
                                                            : (string) $state;
                                                    })
                                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('state_services')
                                                    ->label('Presta servicios en')
                                                    ->getStateUsing(function (Supplier $record): ?array {
                                                        $normalized = SuppliersTable::normalizeJsonListField($record->state_services);

                                                        return ($normalized === null || $normalized === []) ? null : $normalized;
                                                    })
                                                    ->icon(Heroicon::OutlinedCheckCircle)
                                                    ->badge()
                                                    ->color('success')
                                                    ->listWithLineBreaks()
                                                    ->placeholder('—'),
                                                TextEntry::make('personal_phone')
                                                    ->label('Teléfono celular')
                                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('local_phone')
                                                    ->label('Teléfono local')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('correo_principal')
                                                    ->label('Correo electrónico')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('afiliacion_proveedor')
                                                    ->label('Fecha de afiliación')
                                                    ->icon(Heroicon::OutlinedCalendarDateRange)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('ubicacion_principal')
                                                    ->label('Dirección principal')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2])
                                                    ->wrap()
                                                    ->placeholder('—')
                                                    ->helperText('Use el icono del mapa para buscar o actualizar la dirección.')
                                                    ->suffixAction(OperationsLocationMapAction::forSupplier()),
                                                TextEntry::make('convenio_pago')
                                                    ->label('Convenio de pago')
                                                    ->icon(Heroicon::OutlinedBanknotes)
                                                    ->placeholder('—'),
                                                TextEntry::make('tiempo_credito')
                                                    ->label('Tiempo de crédito')
                                                    ->icon(Heroicon::OutlinedClock)
                                                    ->placeholder('—'),
                                                TextEntry::make('promedio_costo_proveedor')
                                                    ->label('Promedio de costo del proveedor')
                                                    ->icon(Heroicon::OutlinedChartBar)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Contactos principales')
                            ->icon('heroicon-o-users')
                            ->schema([
                                Section::make('Contactos principales')
                                    ->description('Personas de contacto registradas para este proveedor.')
                                    ->icon(Heroicon::OutlinedUsers)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        RepeatableEntry::make('supplierContactPrincipals')
                                            ->placeholder('No posee contactos principales.')
                                            ->label('Listado')
                                            ->extraAttributes([
                                                'class' => self::IOS_TABLE_WRAP_CLASS,
                                            ])
                                            ->table([
                                                TableColumn::make('Departamento'),
                                                TableColumn::make('Cargo'),
                                                TableColumn::make('Nombre y apellido'),
                                                TableColumn::make('Correo electrónico'),
                                                TableColumn::make('Teléfono celular'),
                                                TableColumn::make('Teléfono local'),
                                                TableColumn::make('Extensión(es)'),
                                            ])
                                            ->schema([
                                                TextEntry::make('departament'),
                                                TextEntry::make('position'),
                                                TextEntry::make('name'),
                                                TextEntry::make('email'),
                                                TextEntry::make('personal_phone'),
                                                TextEntry::make('local_phone'),
                                                TextEntry::make('extensions'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Sucursales')
                            ->icon('heroicon-o-building-library')
                            ->schema([
                                Section::make('Sucursales')
                                    ->description('Red y ubicaciones asociadas.')
                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        RepeatableEntry::make('supplierRedGlobals')
                                            ->placeholder('No posee sucursales registradas.')
                                            ->label('Listado')
                                            ->extraAttributes([
                                                'class' => self::IOS_TABLE_WRAP_CLASS,
                                            ])
                                            ->table([
                                                TableColumn::make('Estado'),
                                                TableColumn::make('Ciudad'),
                                                TableColumn::make('Nombre y apellido'),
                                                TableColumn::make('Correo electrónico'),
                                                TableColumn::make('Teléfono celular'),
                                                TableColumn::make('Teléfono local'),
                                                TableColumn::make('Dirección de ubicación'),
                                            ])
                                            ->schema([
                                                TextEntry::make('state.definition'),
                                                TextEntry::make('city.definition'),
                                                TextEntry::make('name'),
                                                TextEntry::make('email'),
                                                TextEntry::make('personal_phone'),
                                                TextEntry::make('local_phone'),
                                                TextEntry::make('address')
                                                    ->wrap(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Zonas de cobertura')
                            ->icon('heroicon-o-globe-alt')
                            ->schema([
                                Section::make('Zonas de cobertura')
                                    ->description('Ámbitos geográficos y de servicio declarados.')
                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        RepeatableEntry::make('SupplierZonaCoberturas')
                                            ->placeholder('No posee zonas de cobertura.')
                                            ->label('Listado')
                                            ->extraAttributes([
                                                'class' => self::IOS_TABLE_WRAP_CLASS,
                                            ])
                                            ->table([
                                                TableColumn::make('Clasificación del proveedor'),
                                                TableColumn::make('Tipo de servicio'),
                                                TableColumn::make('Estado'),
                                                TableColumn::make('Ciudad'),
                                            ])
                                            ->schema([
                                                TextEntry::make('supplierClasificacion.description'),
                                                TextEntry::make('type_service'),
                                                TextEntry::make('state.definition'),
                                                TextEntry::make('city.definition'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Infraestructura')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Section::make('Certificación de infraestructura')
                                    ->description('Equipamiento e instalaciones declaradas (sí / no y descripción).')
                                    ->icon(Heroicon::OutlinedCpuChip)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                IconEntry::make('urgen_care')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Urgencias')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_urgen_care')),
                                                IconEntry::make('consulta_aps')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Consultas APS')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_consulta_aps')),
                                                IconEntry::make('amd')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Asistencia médica domiciliaria')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_amd')),
                                                IconEntry::make('laboratorio_centro')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Laboratorio en centro')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_laboratorio_centro')),
                                                IconEntry::make('laboratorio_domicilio')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Laboratorio en domicilio')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_laboratorio_domicilio')),
                                                IconEntry::make('rx_centro')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Rayos X en centro')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_rx_centro')),
                                                IconEntry::make('rx_domicilio')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Rayos X en domicilio')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_rx_domicilio')),
                                                IconEntry::make('eco_abdominal_centro')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Ecografía abdominal en centro')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_eco_abdominal_centro')),
                                                IconEntry::make('eco_abdominal_domicilio')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Ecografía abdominal en domicilio')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_eco_abdominal_domicilio')),
                                                IconEntry::make('electrocardiograma_domicilio')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Electrocardiógrafo en domicilio')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_electrocardiograma_domicilio')),
                                                IconEntry::make('densitometria_osea')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Densitómetro')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_densitometria_osea')),
                                                IconEntry::make('dialisis')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Equipo de diálisis')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_dialisis')),
                                                IconEntry::make('electrocardiograma_centro')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Electrocardiógrafo')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_electrocardiograma_centro')),
                                                IconEntry::make('equipos_especiales_oftalmologia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Equipos especiales de oftalmología')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_equipos_especiales_oftalmologia')),
                                                IconEntry::make('mamografia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Mamógrafo')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_mamografia')),
                                                IconEntry::make('quirofanos')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Quirófanos')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_quirofanos')),
                                                IconEntry::make('radioterapia_intraoperatoria')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Radioterapia intraoperatoria')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_radioterapia_intraoperatoria')),
                                                IconEntry::make('resonancia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Resonador')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_resonancia')),
                                                IconEntry::make('tomografo')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Tomógrafo')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_tomografo')),
                                                IconEntry::make('oncologia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Oncología')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_oncologia')),
                                                IconEntry::make('uci_uten')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('UCI UTE')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_uci_uten')),
                                                IconEntry::make('neonatal')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Neonatal')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_neonatal')),
                                                IconEntry::make('ambulancias')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Ambulancias')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_ambulancias')),
                                                IconEntry::make('odontologia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Odontología')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_odontologia')),
                                                IconEntry::make('oftalmologia')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Oftalmología')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_oftalmologia')),
                                                IconEntry::make('uci_pediatrica')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('UCI pediátrica')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_uci_pediatrica')),
                                                IconEntry::make('uci_adulto')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('UCI adulto')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_uci_adulto')),
                                                IconEntry::make('estacionamiento_propio')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Estacionamiento propio')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_estacionamiento_propio')),
                                                IconEntry::make('ascensor')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Ascensor operativo')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_ascensor')),
                                                IconEntry::make('robotica')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Equipo de cirugía robótica')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_robotica')),
                                                IconEntry::make('otras_unidades_especiales')
                                                    ->boolean()
                                                    ->trueIcon(Heroicon::OutlinedCheckCircle)
                                                    ->falseIcon(Heroicon::OutlinedXCircle)
                                                    ->trueColor('success')
                                                    ->falseColor('gray')
                                                    ->label('Otras unidades especiales')
                                                    ->helperText(fn (Supplier $record): string => self::infraDescription($record, 'descripcion_otras_unidades_especiales')),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Bitácora')
                            ->icon('heroicon-o-clipboard-document-list')
                            ->schema([
                                Section::make('Bitácora de notas y observaciones')
                                    ->description('Historial de anotaciones operativas.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes([
                                        'class' => self::SECTION_CARD,
                                    ])
                                    ->schema([
                                        RepeatableEntry::make('supplierObservacions')
                                            ->placeholder('No posee notas u observaciones.')
                                            ->label('Registros')
                                            ->extraAttributes([
                                                'class' => self::IOS_TABLE_WRAP_CLASS,
                                            ])
                                            ->table([
                                                TableColumn::make('Notas y/o observación'),
                                                TableColumn::make('Responsable de la nota'),
                                                TableColumn::make('Fecha de la nota'),
                                            ])
                                            ->schema([
                                                TextEntry::make('observation')
                                                    ->wrap(),
                                                TextEntry::make('created_by'),
                                                TextEntry::make('created_at')
                                                    ->dateTime('d/m/Y H:i:s'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Órdenes de servicio')
                            ->icon('heroicon-o-clipboard-document-check')
                            ->schema([
                                Section::make('Órdenes de servicio atendidas')
                                    ->description('Solo órdenes en estatus FINALIZADO vinculadas a este proveedor en coordinación.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                                    ->extraAttributes([
                                        'class' => self::IOS_ORDERS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_ORDERS_FRAME_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('finalizedOperationServiceOrders')
                                                    ->label('Órdenes finalizadas')
                                                    ->placeholder('Este proveedor no tiene órdenes de servicio finalizadas.')
                                                    ->extraAttributes([
                                                        'class' => self::IOS_ORDERS_TABLE_WRAP_CLASS,
                                                    ])
                                                    ->table([
                                                        TableColumn::make('Nº orden'),
                                                        TableColumn::make('Estado'),
                                                        TableColumn::make('Prioridad'),
                                                        TableColumn::make('Referencia caso'),
                                                        TableColumn::make('Tipo servicio'),
                                                        TableColumn::make('Total US$'),
                                                        TableColumn::make('Total Bs.'),
                                                        TableColumn::make('Registro'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('order_number')
                                                            ->label('Nº orden')
                                                            ->weight('semibold')
                                                            ->icon(Heroicon::OutlinedHashtag)
                                                            ->color('primary')
                                                            ->copyable()
                                                            ->url(fn (OperationServiceOrder $record): string => OperationServiceOrderResource::getUrl('view', ['record' => $record])),
                                                        TextEntry::make('status')
                                                            ->label('Estado')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::operationServiceOrderStatusColor($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('telemedicinePriority.name')
                                                            ->label('Prioridad')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => self::operationServiceOrderPriorityColor($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('operationCoordinationService.reference_number')
                                                            ->label('Referencia caso')
                                                            ->placeholder('—')
                                                            ->tooltip(fn (OperationServiceOrder $record): ?string => filled($record->operationCoordinationService?->patient)
                                                                ? 'Paciente: '.$record->operationCoordinationService->patient
                                                                : null),
                                                        TextEntry::make('service_type')
                                                            ->label('Tipo servicio')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->placeholder('—'),
                                                        TextEntry::make('total_amount_usd')
                                                            ->label('Total US$')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatMoneyUsd($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('total_amount_ves')
                                                            ->label('Total Bs.')
                                                            ->formatStateUsing(fn (mixed $state): ?string => self::formatMoneyVes($state))
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Registro')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
