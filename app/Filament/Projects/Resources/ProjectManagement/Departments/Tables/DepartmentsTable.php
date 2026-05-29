<?php

namespace App\Filament\Projects\Resources\ProjectManagement\Departments\Tables;

use App\Models\ProjectManagement\Department;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class DepartmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Departamentos')
            ->description('Estructura organizacional para asignaciones y ejecución de actividades.')
            ->emptyStateHeading('No hay departamentos registrados')
            ->emptyStateDescription('Crea el primero con «Crear Departamento» para comenzar a organizar equipos.')
            ->recordTitle(fn (Department $record): string => $record->name)
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->icon('heroicon-m-hashtag')
                    ->badge()
                    ->color('gray')
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('name')
                    ->label('Nombre')
                    ->icon('heroicon-m-building-office-2')
                    ->searchable()
                    ->sortable()
                    ->weight('600')
                    ->wrap(),
                TextColumn::make('description')
                    ->label('Descripción')
                    ->icon('heroicon-m-document-text')
                    ->limit(60)
                    ->placeholder('—')
                    ->tooltip(fn (Department $record): ?string => $record->description),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->icon('heroicon-m-calendar-days')
                    ->dateTime('d/m/Y H:i')
                    ->description(fn (Department $record): string => $record->created_at->diffForHumans())
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->icon('heroicon-m-arrow-path')
                    ->dateTime('d/m/Y H:i')
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
                Filter::make('creado_hoy')
                    ->label('Creados hoy')
                    ->query(fn (Builder $query): Builder => $query->whereDate('created_at', Carbon::today())),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-m-eye'),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-m-pencil-square'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}
