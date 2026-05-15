<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicineConsultationPatients\Schemas;

use App\Models\TelemedicineConsultationPatient;
use App\Support\Telemedicine\TelemedicineDerivedServiceBadge;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;
use Filament\Support\Icons\Heroicon;

class TelemedicineConsultationPatientInfolist
{
    private const IOS_SECTION_CLASS = 'rounded-[1.75rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    /** Tarjeta iOS resaltada (grupo tipo “destacado” con tinte sistema / vidrio). */
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

    private static function priorityColor(?string $name): string
    {
        return match ($name) {
            'NO URGENTE' => 'gray',
            'ESTANDAR' => 'info',
            'URGENCIA' => 'warning',
            'EMERGENCIA' => 'danger',
            'CRITICO' => 'danger',
            default => 'gray',
        };
    }

    /**
     * @return array<int, string>|null
     */
    private static function flattenListState(mixed $state): ?array
    {
        if ($state === null || $state === '' || $state === []) {
            return null;
        }

        if (! is_array($state)) {
            return [trim((string) $state)];
        }

        $items = collect($state)
            ->flatten()
            ->map(function (mixed $v): ?string {
                if ($v === null || $v === '' || $v === []) {
                    return null;
                }
                if (is_scalar($v) || $v instanceof \Stringable) {
                    return trim((string) $v);
                }

                return null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $items === [] ? null : $items;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('telemedicineConsultationPatientInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->tabs([
                        Tab::make('Paciente en esta consulta')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Paciente en esta consulta')
                                    ->description('Ficha resumida en estilo tarjeta iOS: identidad tal como quedó en esta historia clínica.')
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
                                                TextEntry::make('telemedicinePatient.sex')
                                                    ->label('Sexo')
                                                    ->icon(Heroicon::OutlinedUserGroup)
                                                    ->badge()
                                                    ->color('info')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper($state) : null)
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicinePatient.age')
                                                    ->label('Edad')
                                                    ->icon(Heroicon::OutlinedCalendar)
                                                    ->suffix(' años')
                                                    ->formatStateUsing(fn (?string $state): ?string => filled($state) ? mb_strtoupper((string) $state) : null)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Consulta telemédica')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Consulta telemédica')
                                    ->description(fn (TelemedicineConsultationPatient $record): string => self::headerSummary($record))
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
                                                TextEntry::make('telemedicine_case_code')
                                                    ->label('Número de caso')
                                                    ->icon(Heroicon::OutlinedHashtag)
                                                    ->badge()
                                                    ->color('success')
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('code_reference')
                                                    ->label('Código de referencia')
                                                    ->icon(Heroicon::OutlinedDocumentDuplicate)
                                                    ->copyable()
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineServiceList.name')
                                                    ->label('Servicio')
                                                    ->icon(Heroicon::OutlinedWrenchScrewdriver)
                                                    ->badge()
                                                    ->color('success')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicineServiceListDrift.name')
                                                    ->label('Servicio Derivado:')
                                                    ->badge()
                                                    ->color(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state) ? 'danger' : 'info')
                                                    ->icon(fn (?string $state): string => TelemedicineDerivedServiceBadge::driftNameIsCritical($state)
                                                        ? 'heroicon-m-exclamation-triangle'
                                                        : 'heroicon-m-information-circle'),
                                                TextEntry::make('telemedicineDoctor.full_name')
                                                    ->label('Atendido por')
                                                    ->icon(Heroicon::OutlinedUserCircle)
                                                    ->prefix('Dr(a). ')
                                                    ->weight('medium')
                                                    ->placeholder('—'),
                                                TextEntry::make('telemedicinePriority.name')
                                                    ->label('Prioridad')
                                                    ->icon(Heroicon::OutlinedBolt)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::priorityColor($state))
                                                    ->placeholder('—'),
                                                TextEntry::make('assigned_by')
                                                    ->label('Asignado por')
                                                    ->icon(Heroicon::OutlinedUserPlus)
                                                    ->placeholder('—'),
                                                TextEntry::make('status')
                                                    ->label('Estado de la consulta')
                                                    ->icon(Heroicon::OutlinedSignal)
                                                    ->badge()
                                                    ->color(fn (?string $state): string => self::statusColor($state))
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
                                                    ->helperText(fn (TelemedicineConsultationPatient $record): ?string => $record->updated_at
                                                        ? 'Relativo: '.$record->updated_at->diffForHumans()
                                                        : null)
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Información médica')
                            ->icon(Heroicon::OutlinedHeart)
                            ->hidden(fn (TelemedicineConsultationPatient $record): bool => $record->status == 'EN SEGUIMIENTO' || $record->status == 'ALTA MAEDICA')
                            ->schema([
                                Section::make('Información médica')
                                    ->description('Motivo, evolución e impresión diagnóstica.')
                                    ->icon(Heroicon::OutlinedHeart)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('reason_consultation')
                                                    ->label('Razón de consulta')
                                                    ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('actual_phatology')
                                                    ->label('Cuadro / patología actual')
                                                    ->icon(Heroicon::OutlinedBeaker)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('background')
                                                    ->label('Antecedentes')
                                                    ->icon(Heroicon::OutlinedBookOpen)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('diagnostic_impression')
                                                    ->label('Impresión diagnóstica')
                                                    ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Cuestionario de seguimiento')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->hidden(fn (TelemedicineConsultationPatient $record): bool => $record->status == 'CONSULTA INICIAL')
                            ->schema([
                                Section::make('Cuestionario de seguimiento')
                                    ->description('Respuestas del cuestionario (visible siempre; puede estar vacío en consulta inicial).')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('cuestion_1')
                                                    ->label('1. ¿Cómo se siente el día de hoy?')
                                                    ->prefix('Respuesta: ')
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('cuestion_2')
                                                    ->label('2. ¿Cómo ha respondido al tratamiento indicado?')
                                                    ->prefix('Respuesta: ')
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('cuestion_3')
                                                    ->label('3. ¿Siente que han mejorado los síntomas?')
                                                    ->prefix('Respuesta: ')
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('cuestion_4')
                                                    ->label('4. ¿Se realizaron los estudios solicitados?')
                                                    ->prefix('Respuesta: ')
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                                TextEntry::make('cuestion_5')
                                                    ->label('5. Indicaciones médicas modificadas por resultados alterados')
                                                    ->prefix('Respuesta: ')
                                                    ->columnSpanFull()
                                                    ->wrap()
                                                    ->placeholder('—'),
                                            ]),
                                    ])
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Seguimiento y observaciones')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema([
                                Section::make('Seguimiento y observaciones')
                                    ->description('Notas adicionales y parámetros de seguimiento.')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(['default' => 1, 'sm' => 2])
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                TextEntry::make('priorityMonitoring')
                                                    ->label('Prioridad de monitoreo')
                                                    ->suffix(' minutos')
                                                    ->placeholder('—'),
                                                TextEntry::make('observations')
                                                    ->label('Observaciones')
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

    private static function headerSummary(TelemedicineConsultationPatient $record): string
    {
        $patient = $record->telemedicinePatient;
        $age = $patient?->age;
        $sex = $patient?->sex;

        $parts = [
            'Paciente: '.($record->full_name ?: '—'),
        ];

        if ($age !== null && $age !== '') {
            $parts[] = 'Edad (ficha): '.$age.' años';
        }

        if (filled($sex)) {
            $parts[] = 'Sexo (ficha): '.$sex;
        }

        return implode(' · ', $parts);
    }

    private static function formatListAsText(mixed $state): ?string
    {
        $items = self::flattenListState($state);

        if ($items === null) {
            return null;
        }

        return implode("\n", $items);
    }
}
