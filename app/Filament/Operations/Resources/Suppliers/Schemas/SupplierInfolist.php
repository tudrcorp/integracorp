<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Filament\Operations\Resources\Suppliers\Tables\SuppliersTable;
use App\Models\Supplier;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class SupplierInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_TABLE_WRAP_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/90 shadow-sm dark:border-white/10 dark:bg-gray-900/40 overflow-hidden';

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

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Proveedor')
                    ->description('Información general, ubicación, contacto y condiciones comerciales.')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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
                                    ->placeholder('—'),
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

                Section::make('Contactos principales')
                    ->description('Personas de contacto registradas para este proveedor.')
                    ->icon(Heroicon::OutlinedUsers)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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

                Section::make('Sucursales')
                    ->description('Red y ubicaciones asociadas.')
                    ->icon(Heroicon::OutlinedBuildingLibrary)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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

                Section::make('Zonas de cobertura')
                    ->description('Ámbitos geográficos y de servicio declarados.')
                    ->icon(Heroicon::OutlinedGlobeAlt)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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

                Section::make('Certificación de infraestructura')
                    ->description('Equipamiento e instalaciones declaradas (sí / no y descripción).')
                    ->icon(Heroicon::OutlinedCpuChip)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
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
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Bitácora de notas y observaciones')
                    ->description('Historial de anotaciones operativas.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
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
            ]);
    }
}
