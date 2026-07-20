<?php

declare(strict_types=1);

namespace App\Filament\Projects\Resources\ProjectManagement\Epics\Tables;

use App\Enums\ProjectManagement\EpicStatus;
use App\Filament\Projects\Resources\ProjectManagement\Epics\EpicResource;
use App\Models\ProjectManagement\Epic;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EpicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('order')
            ->heading('Épicas')
            ->description('Agrupaciones de valor del producto. Usa épicas para organizar el backlog sin saturar el tablero.')
            ->emptyStateHeading('No hay épicas')
            ->emptyStateDescription('Crea la primera épica para agrupar historias relacionadas.')
            ->emptyStateIcon('heroicon-o-bookmark-square')
            ->recordTitle(fn (Epic $record): string => $record->name)
            ->recordUrl(
                fn (Epic $record): string => EpicResource::getUrl('view', ['record' => $record], panel: 'projects'),
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $query
                    ->with('project:id,name')
                    ->withCount('activities')
                    ->withSum('activities', 'story_points'),
            )
            ->columns([
                TextColumn::make('order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Épica')
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
                    ->formatStateUsing(fn (EpicStatus|string $state): string => $state instanceof EpicStatus
                        ? $state->label()
                        : (EpicStatus::tryFrom($state)?->label() ?? $state))
                    ->color(fn (EpicStatus|string $state): string => match ($state instanceof EpicStatus ? $state : EpicStatus::tryFrom($state)) {
                        EpicStatus::Open => 'success',
                        EpicStatus::Done => 'gray',
                        default => 'gray',
                    })
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
                    ->options(EpicStatus::options()),
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
