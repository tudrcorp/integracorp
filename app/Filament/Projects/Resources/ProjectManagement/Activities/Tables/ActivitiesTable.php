<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Activities\Tables;

use App\Filament\Projects\Resources\ProjectManagement\Activities\ActivityResource;
use App\Models\ProjectManagement\Activity;
use App\Support\Filament\ProjectManagement\ProjectManagementActivityTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Actividades')
            ->description('Tablero operativo con prioridad, plazo, asignación y contexto del proyecto.')
            ->emptyStateHeading('No hay actividades registradas')
            ->emptyStateDescription('Crea la primera actividad para activar seguimiento por plazo, ejecutor y subproyecto.')
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->recordTitle(fn (Activity $record): string => $record->title)
            ->recordUrl(
                fn (Activity $record): string => ActivityResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->with(['project', 'subproject', 'executor']),
            )
            ->columns([
                ViewColumn::make('activity_identity')
                    ->label('Actividad')
                    ->view('filament.projects.tables.columns.activity-identity')
                    ->grow()
                    ->extraCellAttributes([
                        'class' => 'min-w-0 max-w-2xl align-middle !whitespace-normal',
                    ])
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('title', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->where('name', 'like', "%{$search}%"))
                                ->orWhereHas('subproject', fn (Builder $subprojectQuery): Builder => $subprojectQuery->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('title', $direction);
                    }),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProjectManagementActivityTable::statusMeta($state)['label'])
                    ->color(fn (string $state): string => ProjectManagementActivityTable::statusMeta($state)['color'])
                    ->icon(fn (string $state): string => match ($state) {
                        'todo' => 'heroicon-m-queue-list',
                        'in_progress' => 'heroicon-m-arrow-path',
                        'review' => 'heroicon-m-eye',
                        'done' => 'heroicon-m-check-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProjectManagementActivityTable::priorityMeta($state)['label'])
                    ->color(fn (string $state): string => ProjectManagementActivityTable::priorityMeta($state)['color'])
                    ->icon(fn (string $state): string => match ($state) {
                        'high' => 'heroicon-m-fire',
                        'medium' => 'heroicon-m-bolt',
                        'low' => 'heroicon-m-arrow-down',
                        default => 'heroicon-m-minus',
                    })
                    ->sortable()
                    ->alignCenter(),
                ViewColumn::make('due_timeline')
                    ->label('Plazo')
                    ->view('filament.projects.tables.columns.activity-due')
                    ->extraCellAttributes([
                        'class' => 'fi-projects-activities-due-cell min-w-[18rem] w-[18rem] max-w-[20rem] align-middle !whitespace-normal',
                    ])
                    ->extraHeaderAttributes([
                        'class' => 'fi-projects-activities-due-cell min-w-[18rem] w-[18rem] max-w-[20rem]',
                    ])
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('due_date', $direction);
                    }),
                ViewColumn::make('assignment')
                    ->label('Asignación')
                    ->view('filament.projects.tables.columns.activity-assignment')
                    ->extraCellAttributes([
                        'class' => 'fi-projects-activities-assignment-cell align-middle',
                    ])
                    ->extraHeaderAttributes([
                        'class' => 'fi-projects-activities-assignment-cell ps-4',
                    ]),
                TextColumn::make('subproject.name')
                    ->label('Subproyecto')
                    ->placeholder('—')
                    ->icon('heroicon-m-rectangle-stack')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->description(fn (Activity $record): string => $record->updated_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'todo' => 'Por hacer',
                        'in_progress' => 'En progreso',
                        'review' => 'En revisión',
                        'done' => 'Finalizada',
                    ])
                    ->multiple(),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'low' => 'Baja',
                        'medium' => 'Media',
                        'high' => 'Alta',
                    ])
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('assignment_type')
                    ->label('Tipo de asignación')
                    ->options([
                        'collaborator' => 'Colaborador(es)',
                        'team' => 'Equipo',
                    ]),
                Filter::make('vencidas')
                    ->label('Plazo vencido')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', '!=', 'done')
                        ->whereNotNull('due_date')
                        ->whereDate('due_date', '<', now()->toDateString())),
                TernaryFilter::make('sin_fecha_limite')
                    ->label('Fecha límite')
                    ->placeholder('Todos')
                    ->trueLabel('Sin fecha límite')
                    ->falseLabel('Con fecha límite')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('due_date'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('due_date'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('sin_subproyecto')
                    ->label('Subproyecto')
                    ->placeholder('Todos')
                    ->trueLabel('Sin subproyecto')
                    ->falseLabel('Con subproyecto')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('subproject_id'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('subproject_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
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
