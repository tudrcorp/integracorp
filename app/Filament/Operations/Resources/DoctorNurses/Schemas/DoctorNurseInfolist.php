<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\DoctorNurses\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class DoctorNurseInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-2xl border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private static function statusBadgeColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'VIGENTE', 'APROBADO' => 'success',
            'PENDIENTE', 'POR REVISION', 'EN REVISIÓN' => 'warning',
            'INACTIVO', 'SUSPENDIDO', 'RECHAZADO' => 'danger',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Identidad del proveedor natural')
                    ->description('Datos fiscales, clasificación y especialidad del prestador.')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre comercial')
                                    ->icon(Heroicon::OutlinedBuildingStorefront)
                                    ->weight('semibold')
                                    ->size('lg')
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
                                    ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2])
                                    ->placeholder('—'),
                                TextEntry::make('supplierClasificacion.description')
                                    ->label('Clasificación')
                                    ->icon(Heroicon::OutlinedTag)
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('—'),
                                TextEntry::make('tipo_clinica')
                                    ->label('Tipo de clínica')
                                    ->icon(Heroicon::OutlinedRectangleGroup)
                                    ->badge()
                                    ->color('gray')
                                    ->placeholder('—'),
                                TextEntry::make('speciality')
                                    ->label('Especialidad')
                                    ->icon(Heroicon::OutlinedAcademicCap)
                                    ->badge()
                                    ->color('success')
                                    ->placeholder('—'),
                                TextEntry::make('afiliacion_proveedor')
                                    ->label('Afiliación del proveedor')
                                    ->icon(Heroicon::OutlinedCalendarDateRange)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Ubicación y operación')
                    ->description('Cobertura geográfica, estatus y jornada operativa.')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('state')
                                    ->label('Estado')
                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                    ->placeholder('—'),
                                TextEntry::make('city')
                                    ->label('Ciudad')
                                    ->icon(Heroicon::OutlinedMap)
                                    ->placeholder('—'),
                                TextEntry::make('coverage_zone')
                                    ->label('Zona de cobertura')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->wrap()
                                    ->placeholder('—'),
                                TextEntry::make('ubicacion_principal')
                                    ->label('Dirección principal')
                                    ->icon(Heroicon::OutlinedHomeModern)
                                    ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2])
                                    ->wrap()
                                    ->placeholder('—'),
                                TextEntry::make('horario')
                                    ->label('Horario')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->badge()
                                    ->color('gray')
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
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Contacto y condiciones comerciales')
                    ->description('Canales de contacto y acuerdos administrativos.')
                    ->icon(Heroicon::OutlinedPhone)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3, 'xl' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('personal_phone')
                                    ->label('Teléfono personal')
                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('local_phone')
                                    ->label('Teléfono local')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->copyable()
                                    ->placeholder('—'),
                                TextEntry::make('correo_principal')
                                    ->label('Correo principal')
                                    ->icon(Heroicon::OutlinedEnvelope)
                                    ->copyable()
                                    ->wrap()
                                    ->columnSpan(['default' => 1, 'lg' => 2, 'xl' => 2])
                                    ->placeholder('—'),
                                TextEntry::make('convenio_pago')
                                    ->label('Convenio de pago')
                                    ->icon(Heroicon::OutlinedBanknotes)
                                    ->badge()
                                    ->color('info')
                                    ->placeholder('—'),
                                TextEntry::make('tiempo_credito')
                                    ->label('Tiempo de crédito')
                                    ->icon(Heroicon::OutlinedCreditCard)
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Trazabilidad del registro')
                    ->description('Control de responsables y fechas de actualización.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->extraAttributes([
                        'class' => self::IOS_SECTION_CLASS,
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 2, 'xl' => 4])
                            ->extraAttributes([
                                'class' => self::IOS_INNER_CLASS,
                            ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->icon(Heroicon::OutlinedUser)
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->icon(Heroicon::OutlinedUserGroup)
                                    ->placeholder('—'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),

            ]);
    }
}
