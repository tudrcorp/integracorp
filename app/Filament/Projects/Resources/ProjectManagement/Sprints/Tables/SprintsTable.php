<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Sprints\Tables;

use App\Enums\ProjectManagement\SprintStatus;
use App\Filament\Projects\Resources\ProjectManagement\Sprints\SprintResource;
use App\Models\ProjectManagement\Sprint;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SprintsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->heading('Sprints')
            ->description('Iteraciones con objetivo, compromiso y ceremonias. Activa un solo sprint por proyecto.')
            ->emptyStateHeading('No hay sprints')
            ->emptyStateDescription('Crea el primer sprint para planificar el trabajo del equipo.')
            ->emptyStateIcon('heroicon-o-rocket-launch')
            ->recordTitle(fn (Sprint $record): string => $record->name)
            ->recordUrl(
                fn (Sprint $record): string => SprintResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('project:id,name')
                    ->withCount('activities')
                    ->withSum('activities', 'story_points'),
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Sprint')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('project.name')
                    ->label('Proyecto')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (SprintStatus|string $state): string => $state instanceof SprintStatus
                        ? $state->label()
                        : (SprintStatus::tryFrom($state)?->label() ?? $state))
                    ->color(fn (SprintStatus|string $state): string => match ($state instanceof SprintStatus ? $state : SprintStatus::tryFrom($state)) {
                        SprintStatus::Planned => 'gray',
                        SprintStatus::Active => 'success',
                        SprintStatus::Completed => 'info',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Fin')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('activities_count')
                    ->label('Historias')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                TextColumn::make('activities_sum_story_points')
                    ->label('Puntos')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->placeholder('0'),
            ])
            ->filters([
                SelectFilter::make('project_id')
                    ->label('Proyecto')
                    ->relationship('project', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(SprintStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
