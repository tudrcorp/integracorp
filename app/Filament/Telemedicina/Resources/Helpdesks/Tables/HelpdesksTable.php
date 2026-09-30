<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Resources\Helpdesks\Tables;

use App\Models\HelpDesk;
use App\Support\HelpdeskSla;
use App\Support\HelpdeskTableConfigurator;
use App\Support\HelpdeskTaskStatusOptions;
use App\Support\HelpdeskUnreadNoteTracker;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

final class HelpdesksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aún no ha creado tickets')
            ->emptyStateDescription('Use «Crear ticket» en la barra superior para pedir apoyo al equipo de Operaciones.')
            ->emptyStateIcon(Heroicon::OutlinedTicket)
            ->recordClasses(function (HelpDesk $record): array {
                $classes = in_array($record->status, HelpdeskTaskStatusOptions::terminalStatuses(), true)
                    ? []
                    : [HelpdeskTableConfigurator::recordPriorityRowClass($record->priority)];

                $unreadClass = HelpdeskUnreadNoteTracker::recordRowClass($record);

                if ($unreadClass !== '') {
                    $classes[] = $unreadClass;
                }

                return $classes;
            })
            ->columns([
                TextColumn::make('id')
                    ->label('N.º')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->limit(70)
                    ->tooltip(fn (HelpDesk $record): string => (string) $record->description)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'ALTA' => 'danger',
                        'MEDIA' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => HelpdeskTaskStatusOptions::all()[$state] ?? (string) $state)
                    ->color(fn (?string $state): string => match ($state) {
                        HelpdeskTaskStatusOptions::STATUS_DONE => 'success',
                        HelpdeskTaskStatusOptions::STATUS_CANCELLED, HelpdeskTaskStatusOptions::STATUS_REVERTED => 'danger',
                        HelpdeskTaskStatusOptions::STATUS_PENDING => 'gray',
                        default => 'info',
                    }),
                TextColumn::make('rrhhColaboradores.fullName')
                    ->label('Asignado a')
                    ->listWithLineBreaks()
                    ->placeholder('Sin asignar'),
                TextColumn::make('sla')
                    ->label('Plazo')
                    ->state(fn (HelpDesk $record): string => HelpdeskSla::badgeLabel($record) ?? '—')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'SLA vencido', 'SLA incumplido' => 'danger',
                        'SLA cumplido' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y h:i A')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Última actualización')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(HelpdeskTaskStatusOptions::all()),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'ALTA' => 'Alta',
                        'MEDIA' => 'Media',
                        'BAJA' => 'Baja',
                    ]),
            ])
            ->deferFilters(false)
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
            ]);
    }
}
