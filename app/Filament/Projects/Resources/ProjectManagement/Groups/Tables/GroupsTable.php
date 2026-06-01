<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Groups\Tables;

use App\Filament\Projects\Resources\ProjectManagement\Groups\GroupResource;
use App\Models\ProjectManagement\Group;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class GroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Equipos de trabajo')
            ->description('Equipos operativos con integrantes, carga de actividades asignadas y seguimiento de cierre.')
            ->emptyStateHeading('No hay equipos registrados')
            ->emptyStateDescription('Crea el primer equipo para organizar colaboradores y asignar actividades en bloque.')
            ->emptyStateIcon('heroicon-o-user-group')
            ->recordTitle(fn (Group $record): string => $record->name)
            ->recordUrl(
                fn (Group $record): string => GroupResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount([
                    'executedActivities',
                    'executedActivities as executed_activities_done_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', 'done'),
                    'executedActivities as executed_activities_open_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', '!=', 'done'),
                ]),
            )
            ->columns([
                ViewColumn::make('group_identity')
                    ->label('Equipo')
                    ->view('filament.projects.tables.columns.group-identity')
                    ->extraCellAttributes([
                        'class' => 'min-w-0 max-w-2xl align-middle !whitespace-normal',
                    ])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%");
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('name', $direction);
                    }),
                ViewColumn::make('team_members')
                    ->label('Integrantes')
                    ->view('filament.projects.tables.columns.group-members')
                    ->extraCellAttributes([
                        'class' => 'fi-projects-groups-members-cell min-w-[12rem] align-middle !whitespace-normal',
                    ])
                    ->extraHeaderAttributes([
                        'class' => 'fi-projects-groups-members-cell min-w-[12rem]',
                    ]),
                ViewColumn::make('team_workload')
                    ->label('Carga')
                    ->view('filament.projects.tables.columns.group-workload')
                    ->extraCellAttributes([
                        'class' => 'fi-projects-groups-workload-cell min-w-[12rem] max-w-[14rem] align-middle !whitespace-normal',
                    ])
                    ->extraHeaderAttributes([
                        'class' => 'fi-projects-groups-workload-cell min-w-[12rem]',
                    ])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('executed_activities_count', $direction);
                    }),
                TextColumn::make('executed_activities_count')
                    ->label('Actividades')
                    ->counts('executedActivities')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 10 => 'warning',
                        $state > 0 => 'success',
                        default => 'gray',
                    })
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->description(fn (Group $record): string => $record->created_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->description(fn (Group $record): string => $record->updated_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('con_descripcion')
                    ->label('Descripción')
                    ->placeholder('Todos')
                    ->trueLabel('Con descripción')
                    ->falseLabel('Sin descripción')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('description')->where('description', '!=', ''),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $nestedQuery): Builder => $nestedQuery
                            ->whereNull('description')
                            ->orWhere('description', '')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('con_integrantes')
                    ->label('Integrantes')
                    ->placeholder('Todos')
                    ->trueLabel('Con integrantes')
                    ->falseLabel('Sin integrantes')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereJsonLength('collaborator_ids', '>', 0),
                        false: fn (Builder $query): Builder => $query->where(fn (Builder $nestedQuery): Builder => $nestedQuery
                            ->whereNull('collaborator_ids')
                            ->orWhereJsonLength('collaborator_ids', 0)),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('con_actividades')
                    ->label('Actividades asignadas')
                    ->placeholder('Todos')
                    ->trueLabel('Con actividades')
                    ->falseLabel('Sin actividades')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('executedActivities'),
                        false: fn (Builder $query): Builder => $query->doesntHave('executedActivities'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('sin_cierre')
                    ->label('Sin actividades cerradas')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereHas('executedActivities', fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', '!=', 'done'))
                        ->whereDoesntHave('executedActivities', fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', 'done'))),
                Filter::make('creado_hoy')
                    ->label('Creados hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', Carbon::today())),
            ])
            ->filtersFormColumns(2)
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-m-eye')
                    ->color('gray'),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
