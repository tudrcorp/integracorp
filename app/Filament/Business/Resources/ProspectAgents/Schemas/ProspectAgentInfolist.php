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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProspectAgentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del prospecto')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->description('Identificación, tipo y origen del contacto.')
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
                                TextEntry::make('status')
                                    ->label('Estatus en el embudo')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => ProspectAgentLabels::statusLabel($state))
                                    ->color(fn (?string $state): string => ProspectAgentLabels::statusColor($state))
                                    ->columnSpan(['default' => 1, 'lg' => 2]),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Contacto')
                    ->icon(Heroicon::OutlinedPhone)
                    ->description('Teléfonos y correo (puedes copiar o abrir enlace).')
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
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->description('Datos geográficos asociados al prospecto.')
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
                    ])
                    ->columnSpanFull(),
                Section::make('Auditoría')
                    ->icon(Heroicon::OutlinedClock)
                    ->description('Registro de altas y últimas modificaciones.')
                    ->collapsed()
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
                    ])
                    ->columnSpanFull(),
                Section::make('Tareas de gestión')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->description('Historial de tareas asignadas al prospecto.')
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
                    ])
                    ->columnSpanFull(),
                Section::make('Observaciones')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->description('Notas y seguimiento vinculados a tareas.')
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
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
