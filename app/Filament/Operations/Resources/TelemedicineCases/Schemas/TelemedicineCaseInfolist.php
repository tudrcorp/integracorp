<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineCases\Schemas;

use App\Models\TelemedicineCase;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class TelemedicineCaseInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.75rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /** Tarjeta iOS resaltada (mismo tratamiento que consulta telemédica — paciente). */
    private const IOS_PATIENT_HERO_OUTER = 'relative overflow-hidden rounded-[1.75rem] border border-sky-200/75 bg-gradient-to-b from-sky-50/98 via-white to-slate-50/92 shadow-[0_18px_50px_-14px_rgba(14,165,233,0.28),0_1px_0_0_rgba(255,255,255,0.85)_inset] ring-1 ring-sky-300/45 backdrop-blur-[2px] dark:border-sky-500/30 dark:from-sky-950/55 dark:via-gray-900/96 dark:to-slate-950/92 dark:shadow-[0_22px_60px_-18px_rgba(56,189,248,0.14)] dark:ring-sky-400/25';

    private const IOS_PATIENT_HERO_INNER = 'relative rounded-[1.25rem] border border-white/90 bg-white/90 p-5 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.95),0_10px_28px_-10px_rgba(15,23,42,0.1)] backdrop-blur-md dark:border-white/12 dark:bg-white/[0.07] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.05),0_12px_32px_-12px_rgba(0,0,0,0.35)] sm:p-6';

    private static function statusColor(?string $status): string
    {
        return match ($status) {
            'EN SEGUIMIENTO' => 'warning',
            'CONSULTA INICIAL' => 'info',
            'ALTA MEDICA' => 'success',
            default => 'gray',
        };
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicineCaseInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->tabs([
                        Tab::make('Paciente en el caso')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Datos del paciente en el caso')
                                    ->description('Tarjeta resaltada iOS: identidad registrada en el expediente del caso (puede diferir de la ficha maestra).')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::IOS_PATIENT_HERO_OUTER,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_PATIENT_HERO_INNER,
                                            ])
                                            ->schema([
                                                TextEntry::make('patient_name')
                                                    ->label('Nombre completo')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('bold')
                                                    ->size(TextSize::Large)
                                                    ->color('gray')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),

                                                TextEntry::make('telemedicinePatient.nro_identificacion')
                                                    ->label('Número de identificación')
                                                    ->prefix('V-')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable()
                                                    ->placeholder('—'),

                                                TextEntry::make('patient_age')
                                                    ->label('Edad')
                                                    ->icon(Heroicon::OutlinedCalendar)
                                                    ->suffix(' años')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper((string) $state) : null)
                                                    ->placeholder('—'),
                                                TextEntry::make('patient_sex')
                                                    ->label('Sexo')
                                                    ->icon(Heroicon::OutlinedUserGroup)
                                                    ->badge()
                                                    ->color('info')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),
                                            ]),
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('patient_address')
                                                    ->label('Dirección')
                                                    ->icon(Heroicon::OutlinedHome)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('patient_phone')
                                                    ->label('Teléfono principal')
                                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('patient_phone_2')
                                                    ->label('Teléfono alternativo')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado / provincia')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon(Heroicon::OutlinedGlobeAmericas)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Caso de telemedicina')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Caso de telemedicina')
                                    ->description('Identificación, estado, fechas y contexto clínico-administrativo.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('code')
                                                    ->label('Número de caso')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estatus del caso')
                                                    ->icon(Heroicon::OutlinedSignal)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('priority.name')
                                                    ->label('Prioridad')
                                                    ->icon(Heroicon::OutlinedExclamationTriangle)
                                                    ->badge()
                                                    ->color('warning')
                                                    ->placeholder('—'),
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de registro')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->dateTime('d/m/Y H:i')
                                                    ->placeholder('—'),
                                                TextEntry::make('updated_at')
                                                    ->label('Última actualización')
                                                    ->icon(Heroicon::OutlinedClock)
                                                    ->dateTime('d/m/Y H:i')
                                                    ->helperText(fn (?TelemedicineCase $record): ?string => $record?->updated_at
                                                        ? 'Relativo: '.$record->updated_at->diffForHumans()
                                                        : null)
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicinePatient.full_name')
                                                    ->label('Paciente (ficha)')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicinePatient.code')
                                                    ->label('Código del paciente')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicine_doctor_id')
                                                    ->label('ID médico (FK)')
                                                    ->icon(Heroicon::OutlinedKey)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineDoctor.full_name')
                                                    ->label('Médico asignado')
                                                    ->icon(Heroicon::OutlinedUserCircle)
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineDoctor.code')
                                                    ->label('Código del médico')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('assigned_by')
                                                    ->label('Asignado por')
                                                    ->icon(Heroicon::OutlinedUserPlus)
                                                    ->placeholder('—'),
                                                TextEntry::make('reason')
                                                    ->label('Motivo / razón del caso')
                                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('ambulanceParking')
                                                    ->label('Estacionamiento de ambulancia')
                                                    ->icon(Heroicon::OutlinedTruck)
                                                    ->placeholder('—'),
                                                TextEntry::make('directionAmbulance')
                                                    ->label('Dirección / indicaciones ambulancia')
                                                    ->icon(Heroicon::OutlinedMapPin)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
