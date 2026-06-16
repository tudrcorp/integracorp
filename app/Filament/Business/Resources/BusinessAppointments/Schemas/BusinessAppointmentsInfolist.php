<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\BusinessAppointments\Schemas;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentLabels;
use App\Models\BusinessAppointments;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class BusinessAppointmentsInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('businessAppointmentsInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Resumen de la cita')
                            ->icon(Heroicon::OutlinedCalendarDays)
                            ->schema([
                                Section::make('Resumen de la cita')
                                    ->icon(Heroicon::OutlinedCalendarDays)
                                    ->description('Datos de contacto y estado de la solicitud.')
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
                                                        TextEntry::make('legal_name')
                                                            ->label('Nombre o razón social')
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('status')
                                                            ->label('Estado')
                                                            ->badge()
                                                            ->formatStateUsing(fn (?string $state): string => BusinessAppointmentLabels::statusLabel($state))
                                                            ->color(fn (?string $state): string => BusinessAppointmentLabels::statusColor($state))
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('phone')
                                                            ->label('Teléfono')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->copyMessage('Copiado')
                                                            ->url(fn (BusinessAppointments $record): ?string => filled($record->phone) ? 'tel:'.$record->phone : null)
                                                            ->openUrlInNewTab(false)
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->copyMessage('Correo copiado')
                                                            ->url(fn (BusinessAppointments $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                                                            ->openUrlInNewTab(false)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Ubicación')
                            ->icon(Heroicon::OutlinedMapPin)
                            ->schema([
                                Section::make('Ubicación')
                                    ->icon(Heroicon::OutlinedMapPin)
                                    ->description('Referencia geográfica de la cita.')
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
                                                        TextEntry::make('country.name')
                                                            ->label('País')
                                                            ->icon(Heroicon::OutlinedGlobeAmericas)
                                                            ->placeholder('—'),
                                                        TextEntry::make('state.definition')
                                                            ->label('Estado')
                                                            ->icon(Heroicon::OutlinedMap)
                                                            ->placeholder('—'),
                                                        TextEntry::make('city.definition')
                                                            ->label('Ciudad')
                                                            ->icon(Heroicon::OutlinedBuildingOffice2)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Observaciones')
                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                            ->schema([
                                Section::make('Observaciones')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->description('Notas asociadas a esta cita.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('businessAppointmentObservations')
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('Nota')->width('50%'),
                                                        TableColumn::make('Autor')->width('20%'),
                                                        TableColumn::make('Fecha')->width('30%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('observation')
                                                            ->limit(200)
                                                            ->tooltip(fn ($record): ?string => is_string($record->observation ?? null) ? $record->observation : null),
                                                        TextEntry::make('created_by')
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Auditoría')
                            ->icon(Heroicon::OutlinedClock)
                            ->schema([
                                Section::make('Auditoría')
                                    ->icon(Heroicon::OutlinedClock)
                                    ->description('Registro de altas y últimas modificaciones.')
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
                                                        TextEntry::make('created_by')
                                                            ->label('Creado por')
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_by')
                                                            ->label('Actualizado por')
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->label('Fecha de creación')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->icon(Heroicon::OutlinedCalendarDays)
                                                            ->placeholder('—'),
                                                        TextEntry::make('updated_at')
                                                            ->label('Última actualización')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->icon(Heroicon::OutlinedArrowPath)
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
