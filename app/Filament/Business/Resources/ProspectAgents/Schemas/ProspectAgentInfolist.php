<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Schemas;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\ProspectAgent;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProspectAgentInfolist
{
    private const TABS_CONTAINER = 'rounded-[1.75rem] border border-slate-200/85 bg-gradient-to-br from-white via-slate-50/90 to-white p-2 shadow-[0_24px_60px_-26px_rgba(15,23,42,0.2)] ring-1 ring-slate-200/55 dark:border-white/10 dark:from-slate-900/95 dark:via-slate-950/95 dark:to-slate-900/95 dark:ring-white/10 dark:shadow-[0_24px_60px_-24px_rgba(0,0,0,0.55)]';

    private const IOS_SECTION_CLASS = 'rounded-[1.5rem] border border-slate-200/90 bg-gradient-to-b from-white to-slate-50/95 shadow-[0_12px_40px_-12px_rgba(15,23,42,0.12)] dark:from-gray-900/90 dark:to-slate-950/95 dark:border-white/10 dark:shadow-[0_12px_40px_-12px_rgba(0,0,0,0.45)]';

    private const IOS_INNER_CLASS = 'rounded-[1.25rem] border border-slate-200/80 bg-white/80 p-4 shadow-inner dark:border-white/10 dark:bg-white/5 sm:p-5';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Tabs::make('prospectAgentInfolistTabs')
                    ->columnSpanFull()
                    ->persistTab()
                    ->extraAttributes([
                        'class' => self::TABS_CONTAINER,
                    ])
                    ->tabs([
                        Tab::make('Información del prospecto')
                            ->icon(Heroicon::OutlinedUserCircle)
                            ->schema([
                                Section::make('Resumen del prospecto')
                                    ->icon(Heroicon::OutlinedUserCircle)
                                    ->description('Identificación, tipo, clasificación y origen del contacto.')
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
                                                        TextEntry::make('name')
                                                            ->label('Nombre y apellido')
                                                            ->weight('semibold')
                                                            ->columnSpan(['default' => 1, 'lg' => 2]),
                                                        TextEntry::make('type')
                                                            ->label('Tipo')
                                                            ->badge()
                                                            ->color('gray')
                                                            ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::typeLabel($state)),
                                                        TextEntry::make('reference_by')
                                                            ->label('Referido por')
                                                            ->badge()
                                                            ->color('info')
                                                            ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::referenceLabel($state)),
                                                        TextEntry::make('classification')
                                                            ->label('Clasificación')
                                                            ->icon(Heroicon::OutlinedTag)
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->label('Estatus en el embudo')
                                                            ->badge()
                                                            ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::statusLabel($state))
                                                            ->color(fn (?string $state): string => ProspectAgentLabels::statusColor($state)),
                                                        TextEntry::make('initial_observ')
                                                            ->label('Observaciones iniciales')
                                                            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                                            ->columnSpan(['default' => 1, 'lg' => 2])
                                                            ->placeholder('—'),
                                                    ]),
                                            ]),
                                    ]),
                            ]),
                        Tab::make('Contacto')
                            ->icon(Heroicon::OutlinedPhone)
                            ->schema([
                                Section::make('Contacto')
                                    ->icon(Heroicon::OutlinedPhone)
                                    ->description('Teléfonos, correo e Instagram (puedes copiar o abrir enlace).')
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
                                                        TextEntry::make('phone_1')
                                                            ->label('Teléfono principal')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->copyMessage('Copiado')
                                                            ->url(fn (ProspectAgent $record): ?string => filled($record->phone_1) ? 'tel:'.$record->phone_1 : null)
                                                            ->openUrlInNewTab(false)
                                                            ->placeholder('—'),
                                                        TextEntry::make('phone_2')
                                                            ->label('Teléfono alternativo')
                                                            ->icon(Heroicon::OutlinedPhone)
                                                            ->copyable()
                                                            ->copyMessage('Copiado')
                                                            ->url(fn (ProspectAgent $record): ?string => filled($record->phone_2) ? 'tel:'.$record->phone_2 : null)
                                                            ->openUrlInNewTab(false)
                                                            ->placeholder('—'),
                                                        TextEntry::make('email')
                                                            ->label('Correo electrónico')
                                                            ->icon(Heroicon::OutlinedEnvelope)
                                                            ->copyable()
                                                            ->copyMessage('Correo copiado')
                                                            ->url(fn (ProspectAgent $record): ?string => filled($record->email) ? 'mailto:'.$record->email : null)
                                                            ->openUrlInNewTab(false)
                                                            ->columnSpan(['default' => 1, 'lg' => 2])
                                                            ->placeholder('—'),
                                                        TextEntry::make('instagram')
                                                            ->label('Instagram')
                                                            ->icon(Heroicon::OutlinedAtSymbol)
                                                            ->copyable()
                                                            ->copyMessage('Copiado')
                                                            ->url(function (ProspectAgent $record): ?string {
                                                                $ig = trim((string) ($record->instagram ?? ''));

                                                                if ($ig === '') {
                                                                    return null;
                                                                }

                                                                if (str_starts_with(strtolower($ig), 'http')) {
                                                                    return $ig;
                                                                }

                                                                $handle = ltrim($ig, '@');

                                                                return $handle !== '' ? 'https://instagram.com/'.$handle : null;
                                                            })
                                                            ->openUrlInNewTab()
                                                            ->columnSpan(['default' => 1, 'lg' => 2])
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
                                    ->description('Datos geográficos asociados al prospecto.')
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
                        Tab::make('Seguimiento')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->schema([
                                Section::make('Tareas de gestión')
                                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                                    ->description('Historial de tareas asignadas al prospecto.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('prospect_agent_tasks')
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('ID')->width('6%'),
                                                        TableColumn::make('Tarea')->width('22%'),
                                                        TableColumn::make('Creado por')->width('10%'),
                                                        TableColumn::make('Responsable')->width('14%'),
                                                        TableColumn::make('Estatus')->width('10%'),
                                                        TableColumn::make('Resuelto por')->width('12%'),
                                                        TableColumn::make('Creado')->width('12%'),
                                                        TableColumn::make('Antigüedad')->width('14%'),
                                                        TableColumn::make('Actualizado')->width('12%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('id')
                                                            ->prefix('#')
                                                            ->weight('medium'),
                                                        TextEntry::make('task')
                                                            ->limit(80)
                                                            ->tooltip(fn ($record): ?string => is_string($record->task ?? null) ? $record->task : null),
                                                        TextEntry::make('created_by')
                                                            ->placeholder('—'),
                                                        TextEntry::make('rrhh_colaborador.fullName')
                                                            ->placeholder('—'),
                                                        TextEntry::make('status')
                                                            ->badge()
                                                            ->color(fn (?string $state): string => match ($state) {
                                                                'PENDIENTE' => 'warning',
                                                                'RESUELTA' => 'success',
                                                                default => 'gray',
                                                            })
                                                            ->icon(fn (?string $state): string => match ($state) {
                                                                'PENDIENTE' => 'heroicon-o-clock',
                                                                'RESUELTA' => 'heroicon-o-check-circle',
                                                                default => 'heroicon-o-minus-circle',
                                                            }),
                                                        TextEntry::make('resolved_by')
                                                            ->badge()
                                                            ->color(fn ($state): string => filled($state) ? 'success' : 'gray')
                                                            ->icon(Heroicon::OutlinedCheckCircle)
                                                            ->formatStateUsing(fn ($state): string => filled($state) ? (string) $state : 'Pendiente')
                                                            ->placeholder('—'),
                                                        TextEntry::make('created_at')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                        TextEntry::make('elapsed')
                                                            ->label('Antigüedad')
                                                            ->getStateUsing(fn ($record): ?string => $record->created_at?->diffForHumans()),
                                                        TextEntry::make('updated_at')
                                                            ->dateTime('d/m/Y H:i')
                                                            ->placeholder('—'),
                                                    ])
                                                    ->columnSpanFull(),
                                            ]),
                                    ]),
                                Section::make('Observaciones')
                                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                                    ->description('Notas y seguimiento vinculados a tareas.')
                                    ->extraAttributes([
                                        'class' => self::IOS_SECTION_CLASS,
                                    ])
                                    ->schema([
                                        Grid::make(1)
                                            ->extraAttributes([
                                                'class' => self::IOS_INNER_CLASS,
                                            ])
                                            ->schema([
                                                RepeatableEntry::make('prospect_agent_observations')
                                                    ->hiddenLabel()
                                                    ->table([
                                                        TableColumn::make('ID tarea')->width('10%'),
                                                        TableColumn::make('Nota')->width('50%'),
                                                        TableColumn::make('Autor')->width('15%'),
                                                        TableColumn::make('Fecha')->width('25%'),
                                                    ])
                                                    ->schema([
                                                        TextEntry::make('prospect_agent_task_id')
                                                            ->prefix('#')
                                                            ->placeholder('—'),
                                                        TextEntry::make('observation')
                                                            ->limit(120)
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
                                                            ->label('Fecha de registro')
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
