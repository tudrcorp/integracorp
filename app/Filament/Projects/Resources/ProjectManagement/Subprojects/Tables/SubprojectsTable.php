<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Subprojects\Tables;

use App\Filament\Projects\Resources\ProjectManagement\Subprojects\SubprojectResource;
use App\Models\ProjectManagement\Subproject;
use App\Support\Filament\ProjectManagement\ProjectManagementSubprojectTable;
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

class SubprojectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Subproyectos')
            ->description('Fases y componentes del plan maestro, con avance por carga de actividades y vínculo al proyecto padre.')
            ->emptyStateHeading('No hay subproyectos registrados')
            ->emptyStateDescription('Crea el primero para desglosar fases o componentes y medir el avance operativo por bloque.')
            ->emptyStateIcon('heroicon-o-squares-2x2')
            ->recordTitle(fn (Subproject $record): string => $record->name)
            ->recordUrl(
                fn (Subproject $record): string => SubprojectResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('project')
                    ->withCount([
                        'activities',
                        'activities as activities_done_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', 'done'),
                        'activities as activities_open_count' => fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', '!=', 'done'),
                    ]),
            )
            ->columns([
                ViewColumn::make('subproject_identity')
                    ->label('Subproyecto')
                    ->view('filament.projects.tables.columns.subproject-identity')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $nestedQuery) use ($search): void {
                            $nestedQuery
                                ->where('name', 'like', "%{$search}%")
                                ->orWhere('description', 'like', "%{$search}%")
                                ->orWhereHas('project', fn (Builder $projectQuery): Builder => $projectQuery->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderBy('name', $direction);
                    }),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProjectManagementSubprojectTable::statusMeta($state)['label'])
                    ->color(fn (string $state): string => ProjectManagementSubprojectTable::statusMeta($state)['color'])
                    ->icon(fn (string $state): string => match ($state) {
                        'pending' => 'heroicon-m-clock',
                        'active' => 'heroicon-m-bolt',
                        'completed' => 'heroicon-m-check-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->sortable()
                    ->alignCenter(),
                ViewColumn::make('workload')
                    ->label('Avance')
                    ->view('filament.projects.tables.columns.subproject-workload'),
                TextColumn::make('activities_count')
                    ->label('Actividades')
                    ->counts('activities')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 10 => 'warning',
                        $state > 0 => 'success',
                        default => 'gray',
                    })
                    ->alignCenter()
                    ->sortable(),
                TextColumn::make('activities_open_count')
                    ->label('Abiertas')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->description(fn (Subproject $record): string => $record->updated_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'pending' => 'Pendiente',
                        'active' => 'Activo',
                        'completed' => 'Completado',
                    ])
                    ->multiple(),
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('con_actividades')
                    ->label('Actividades')
                    ->placeholder('Todos')
                    ->trueLabel('Con actividades')
                    ->falseLabel('Sin actividades')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->has('activities'),
                        false: fn (Builder $query): Builder => $query->doesntHave('activities'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('sin_avance')
                    ->label('Sin actividades cerradas')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', '!=', 'completed')
                        ->whereHas('activities', fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', '!=', 'done'))
                        ->whereDoesntHave('activities', fn (Builder $activitiesQuery): Builder => $activitiesQuery->where('status', 'done'))),
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
