<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Projects\Tables;

use App\Filament\Projects\Resources\ProjectManagement\Projects\ProjectResource;
use App\Models\ProjectManagement\Project;
use App\Support\Filament\ProjectManagement\ProjectManagementProjectTable;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class ProjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Proyectos')
            ->description('Tablero ejecutivo con identidad visual, avance de cronograma y carga operativa por proyecto.')
            ->emptyStateHeading('Aún no hay proyectos')
            ->emptyStateDescription('Crea el primer proyecto para activar subproyectos, actividades y accesos rápidos en el menú lateral.')
            ->emptyStateIcon('heroicon-o-folder-open')
            ->recordTitle(fn (Project $record): string => $record->name)
            ->recordUrl(
                fn (Project $record): string => ProjectResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query->withCount(['subprojects', 'activities']),
            )
            ->columns([
                ViewColumn::make('project_identity')
                    ->label('Proyecto')
                    ->view('filament.projects.tables.columns.project-identity')
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
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ProjectManagementProjectTable::statusMeta($state)['label'])
                    ->color(fn (string $state): string => ProjectManagementProjectTable::statusMeta($state)['color'])
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-m-bolt',
                        'on_hold' => 'heroicon-m-pause-circle',
                        'completed' => 'heroicon-m-check-circle',
                        default => 'heroicon-m-question-mark-circle',
                    })
                    ->sortable()
                    ->alignCenter(),
                ViewColumn::make('timeline')
                    ->label('Cronograma')
                    ->view('filament.projects.tables.columns.project-timeline'),
                TextColumn::make('subprojects_count')
                    ->label('Subproyectos')
                    ->counts('subprojects')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->alignCenter()
                    ->sortable(),
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
                TextColumn::make('delay_days')
                    ->label('Retraso')
                    ->state(fn (Project $record): ?int => ProjectManagementProjectTable::delayDays($record))
                    ->badge()
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : $state.' día'.($state === 1 ? '' : 's'))
                    ->color(fn (?int $state): string => match (true) {
                        $state === null => 'gray',
                        $state >= 15 => 'danger',
                        default => 'warning',
                    })
                    ->icon(fn (?int $state): ?string => $state === null ? null : 'heroicon-m-exclamation-triangle')
                    ->alignCenter()
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('end_date', $direction)),
                TextColumn::make('duration_days')
                    ->label('Duración')
                    ->state(fn (Project $record): string => $record->start_date && $record->end_date
                        ? $record->start_date->diffInDays($record->end_date).' días'
                        : '—')
                    ->icon('heroicon-m-clock')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->alignCenter(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->description(fn (Project $record): string => $record->updated_at->format('d/m/Y H:i'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'active' => 'Activo',
                        'on_hold' => 'En espera',
                        'completed' => 'Completado',
                    ])
                    ->multiple(),
                Filter::make('vencidos')
                    ->label('Plazo vencido')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', '!=', 'completed')
                        ->whereNotNull('end_date')
                        ->whereDate('end_date', '<', now()->toDateString())),
                TernaryFilter::make('sin_fecha_fin')
                    ->label('Fecha fin')
                    ->placeholder('Todos')
                    ->trueLabel('Sin fecha fin')
                    ->falseLabel('Con fecha fin')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNull('end_date'),
                        false: fn (Builder $query): Builder => $query->whereNotNull('end_date'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
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
                Action::make('update_status')
                    ->label('Actualizar estatus')
                    ->icon('heroicon-m-arrow-path-rounded-square')
                    ->color('warning')
                    ->modalIcon('heroicon-o-signal')
                    ->modalHeading(fn (Project $record): string => 'Actualizar estatus')
                    ->modalDescription(fn (Project $record): string => match (true) {
                        ProjectManagementProjectTable::delayDays($record) !== null => 'Proyecto con retraso de '.ProjectManagementProjectTable::delayDays($record).' día(s). Confirma el nuevo estatus operativo.',
                        $record->status === 'completed' => 'Este proyecto está completado. Puedes reabrirlo o mantenerlo cerrado.',
                        default => 'Define el estatus operativo sin salir del listado ejecutivo.',
                    })
                    ->modalWidth(Width::TwoExtraLarge)
                    ->modalSubmitActionLabel('Guardar cambio')
                    ->modalCancelActionLabel('Cerrar')
                    ->fillForm(fn (Project $record): array => [
                        'status' => $record->status,
                    ])
                    ->form([
                        Placeholder::make('project_context')
                            ->hiddenLabel()
                            ->content(fn (Project $record): HtmlString => new HtmlString(
                                view('filament.projects.actions.update-project-status-context', [
                                    'record' => $record,
                                ])->render(),
                            ))
                            ->columnSpanFull(),
                        ToggleButtons::make('status')
                            ->label('Nuevo estatus del proyecto')
                            ->helperText('Selecciona cómo debe reflejarse el proyecto en tableros, cronograma y seguimiento.')
                            ->options(ProjectManagementProjectTable::statusOptions())
                            ->icons([
                                'active' => Heroicon::OutlinedBolt,
                                'on_hold' => Heroicon::OutlinedPauseCircle,
                                'completed' => Heroicon::OutlinedCheckCircle,
                            ])
                            ->colors([
                                'active' => 'success',
                                'on_hold' => 'warning',
                                'completed' => 'gray',
                            ])
                            ->inline()
                            ->required()
                            ->live()
                            ->columnSpanFull(),
                    ])
                    ->action(function (Project $record, array $data): void {
                        $record->update([
                            'status' => (string) $data['status'],
                        ]);
                    })
                    ->successNotification(function (Project $record): Notification {
                        $label = ProjectManagementProjectTable::statusMeta((string) $record->status)['label'];

                        return Notification::make()
                            ->success()
                            ->title('Estatus actualizado')
                            ->body("El proyecto «{$record->name}» quedó en estatus {$label}.");
                    }),
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
