<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Schemas;

use App\Models\Benefit;
use App\Models\TelemedicinePatient;
use App\Support\FilamentDateDisplay;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class TelemedicinePatientInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.75rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    private const IOS_PATIENT_HERO_OUTER = 'relative overflow-hidden rounded-[1.75rem] border border-sky-200/75 bg-gradient-to-b from-sky-50/98 via-white to-slate-50/92 shadow-[0_18px_50px_-14px_rgba(14,165,233,0.28),0_1px_0_0_rgba(255,255,255,0.85)_inset] ring-1 ring-sky-300/45 backdrop-blur-[2px] dark:border-sky-500/30 dark:from-sky-950/55 dark:via-gray-900/96 dark:to-slate-950/92 dark:shadow-[0_22px_60px_-18px_rgba(56,189,248,0.14)] dark:ring-sky-400/25';

    private const IOS_PATIENT_HERO_INNER = 'relative rounded-[1.25rem] border border-white/90 bg-white/90 p-5 shadow-[inset_0_1px_0_0_rgba(255,255,255,0.95),0_10px_28px_-10px_rgba(15,23,42,0.1)] backdrop-blur-md dark:border-white/12 dark:bg-white/[0.07] dark:shadow-[inset_0_1px_0_0_rgba(255,255,255,0.05),0_12px_32px_-12px_rgba(0,0,0,0.35)] sm:p-6';

    private static function affiliationStatusColor(?string $status): string
    {
        return match (mb_strtoupper((string) $status)) {
            'ACTIVO', 'ACTIVA' => 'success',
            'SUSPENDIDO', 'SUSPENDIDA' => 'warning',
            'INACTIVO', 'INACTIVA', 'CANCELADO', 'CANCELADA' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>|null
     */
    private static function benefitDescriptionsFromRecord(TelemedicinePatient $record): ?array
    {
        $plan = $record->plan;
        if ($plan === null) {
            return null;
        }

        $lines = $plan->benefitPlans
            ->map(function (Benefit $benefit): string {
                $text = $benefit->pivot->description ?? $benefit->description ?? '';

                return trim((string) $text);
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }

    /**
     * @return array<int, string>|null
     */
    private static function benefitLimitDescriptionsFromRecord(TelemedicinePatient $record): ?array
    {
        $plan = $record->plan;
        if ($plan === null) {
            return null;
        }

        $lines = $plan->benefitPlans
            ->map(fn (Benefit $benefit): ?string => filled($benefit->limit?->description)
                ? trim((string) $benefit->limit->description)
                : null)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $lines === [] ? null : $lines;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicinePatientInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->tabs([
                        Tab::make('Paciente')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Paciente')
                                    ->description('Identidad principal del expediente en telemedicina.')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->extraAttributes([
                                        'class' => self::IOS_PATIENT_HERO_OUTER,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_PATIENT_HERO_INNER,
                                            ])
                                            ->schema([
                                                TextEntry::make('full_name')
                                                    ->label('Nombre completo')
                                                    ->icon(Heroicon::OutlinedUser)
                                                    ->weight('bold')
                                                    ->size(TextSize::Large)
                                                    ->color('gray')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),
                                                TextEntry::make('nro_identificacion')
                                                    ->label('Identificación')
                                                    ->icon(Heroicon::OutlinedIdentification)
                                                    ->prefix('V-')
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('birth_date')
                                                    ->label('Fecha de nacimiento')
                                                    ->icon(Heroicon::OutlinedCalendar)
                                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('age')
                                                    ->label('Edad')
                                                    ->icon(Heroicon::OutlinedClock)
                                                    ->suffix(' años')
                                                    ->placeholder('—'),
                                                TextEntry::make('sex')
                                                    ->label('Sexo')
                                                    ->icon(Heroicon::OutlinedUserGroup)
                                                    ->badge()
                                                    ->color('info')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Contacto y ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Contacto y ubicación')
                                    ->description('Datos para localizar al paciente.')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('phone')
                                                    ->label('Teléfono')
                                                    ->icon(Heroicon::OutlinedPhone)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email')
                                                    ->label('Correo electrónico')
                                                    ->icon(Heroicon::OutlinedEnvelope)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('phone_contact')
                                                    ->label('Teléfono de contacto')
                                                    ->icon(Heroicon::OutlinedDevicePhoneMobile)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('email_contact')
                                                    ->label('Correo de contacto')
                                                    ->icon(Heroicon::OutlinedAtSymbol)
                                                    ->copyable()
                                                    ->placeholder('—'),

                                                TextEntry::make('city.definition')
                                                    ->label('Ciudad')
                                                    ->icon(Heroicon::OutlinedBuildingOffice2)
                                                    ->placeholder('—'),
                                                TextEntry::make('state.definition')
                                                    ->label('Estado')
                                                    ->icon(Heroicon::OutlinedMap)
                                                    ->placeholder('—'),
                                                TextEntry::make('country.name')
                                                    ->label('País')
                                                    ->icon(Heroicon::OutlinedGlobeAlt)
                                                    ->placeholder('—'),
                                                TextEntry::make('region')
                                                    ->label('Región')
                                                    ->icon(Heroicon::OutlinedSquares2x2)
                                                    ->placeholder('—'),
                                                TextEntry::make('address')
                                                    ->label('Dirección')
                                                    ->icon(Heroicon::OutlinedHome)
                                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 4])
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Afiliación')
                            ->icon(Heroicon::OutlinedIdentification)
                            ->hidden(fn (TelemedicinePatient $record): bool => ! in_array(mb_strtoupper((string) $record->type_affiliation), ['INDIVIDUAL', 'CORPORATIVO'], true))
                            ->schema([
                                Section::make('Afiliación')
                                    ->description('Plan, cobertura y datos de afiliación cuando aplica.')
                                    ->icon(Heroicon::OutlinedIdentification)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->hidden(fn (TelemedicinePatient $record): bool => ! in_array(mb_strtoupper((string) $record->type_affiliation), ['INDIVIDUAL', 'CORPORATIVO'], true))
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 5])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('plan.description')
                                                    ->label('Plan')
                                                    ->icon(Heroicon::OutlinedRectangleStack)
                                                    ->badge()
                                                    ->color('info')
                                                    ->placeholder('—'),
                                                TextEntry::make('plan.businessUnit.definition')
                                                    ->label('Unidad de negocio')
                                                    ->icon(Heroicon::OutlinedBuildingLibrary)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('coverage.price')
                                                    ->label('Cobertura (precio)')
                                                    ->icon(Heroicon::OutlinedBanknotes)
                                                    ->formatStateUsing(function ($state): ?string {
                                                        if ($state === null || $state === '') {
                                                            return null;
                                                        }

                                                        return is_numeric($state)
                                                            ? number_format((float) $state, 2, ',', '.')
                                                            : (string) $state;
                                                    })
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('code_affiliation')
                                                    ->label('Número de afiliación')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('primary')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('type_affiliation')
                                                    ->label('Tipo de afiliación')
                                                    ->icon(Heroicon::OutlinedTag)
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                                TextEntry::make('status_affiliation')
                                                    ->label('Estado de afiliación')
                                                    ->icon(Heroicon::OutlinedShieldCheck)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::affiliationStatusColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('date_affiliation')
                                                    ->label('Fecha de afiliación')
                                                    ->icon(Heroicon::OutlinedCalendarDays)
                                                    ->formatStateUsing(fn (mixed $state): ?string => FilamentDateDisplay::toDmy($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('name_corporate')
                                                    ->label('Empresa / corporativo')
                                                    ->icon(Heroicon::OutlinedBuildingOffice)
                                                    ->visible(fn (TelemedicinePatient $record): bool => mb_strtoupper((string) $record->type_affiliation) === 'CORPORATIVO')
                                                    ->columnSpan(['default' => 1, 'sm' => 2])
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Beneficios del plan')
                            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                            ->hidden(fn (TelemedicinePatient $record): bool => $record->plan_id === null)
                            ->schema([
                                Section::make('Beneficios y límites del plan')
                                    ->description('Resumen de beneficios vinculados al plan del paciente.')
                                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->hidden(fn (TelemedicinePatient $record): bool => $record->plan_id === null)
                                    ->schema([
                                        Grid::make(['default' => 1, 'lg' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('benefit_descriptions')
                                                    ->label('Beneficios')
                                                    ->icon(Heroicon::OutlinedCheckCircle)
                                                    ->getStateUsing(fn (TelemedicinePatient $record): ?array => self::benefitDescriptionsFromRecord($record))
                                                    ->listWithLineBreaks()
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                                TextEntry::make('benefit_limits')
                                                    ->label('Límites por beneficio')
                                                    ->icon(Heroicon::OutlinedArrowsPointingOut)
                                                    ->getStateUsing(fn (TelemedicinePatient $record): ?array => self::benefitLimitDescriptionsFromRecord($record))
                                                    ->listWithLineBreaks()
                                                    ->placeholder('—')
                                                    ->columnSpanFull(),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Registro')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([
                                Section::make('Registro en el sistema')
                                    ->description('Auditoría básica del expediente.')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('created_at')
                                                    ->label('Fecha de registro')
                                                    ->icon(Heroicon::OutlinedCalendar)
                                                    ->dateTime()
                                                    ->badge()
                                                    ->color('gray')
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
