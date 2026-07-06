<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGenerators\Tables;

use App\Models\PlanGenerator;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PlanGeneratorsTable
{
    private static function statusColor(?string $state): string
    {
        return match (strtoupper((string) $state)) {
            'ACTIVO', 'ACTIVA', 'APROBADA', 'APROBADO' => 'success',
            'PRE-APROBADO' => 'warning',
            'INACTIVO', 'INACTIVA' => 'gray',
            default => 'gray',
        };
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Generador de planes')
            ->description('Planes comerciales con matrices de beneficios, tarifas por rango etario y totales grupales.')
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->deferFilters(false)
            ->recordTitleAttribute('name')
            ->emptyStateHeading('Sin planes generados')
            ->emptyStateDescription('Crea el primer plan o ajusta los filtros para ver resultados.')
            ->emptyStateIcon(Heroicon::OutlinedTableCells)
            ->columns([
                ColumnGroup::make('Plan', [
                    TextColumn::make('control_number')
                        ->label('Nro. Control')
                        ->icon(Heroicon::OutlinedHashtag)
                        ->badge()
                        ->color('gray')
                        ->searchable()
                        ->sortable()
                        ->placeholder('—'),
                    TextColumn::make('name')
                        ->label('Nombre del plan')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->weight('semibold')
                        ->searchable()
                        ->sortable()
                        ->limit(32)
                        ->tooltip(fn (PlanGenerator $record): string => (string) $record->name),
                    TextColumn::make('status')
                        ->label('Estatus')
                        ->badge()
                        ->color(fn (?string $state): string => self::statusColor($state))
                        ->sortable()
                        ->searchable(),
                ]),
                ColumnGroup::make('Propuesta comercial', [
                    TextColumn::make('client_data')
                        ->label('Cliente')
                        ->icon(Heroicon::OutlinedBuildingOffice2)
                        ->searchable()
                        ->limit(36)
                        ->tooltip(fn (PlanGenerator $record): ?string => filled($record->client_data) ? (string) $record->client_data : null)
                        ->placeholder('—'),
                    TextColumn::make('agent_name')
                        ->label('Agente')
                        ->icon(Heroicon::OutlinedUser)
                        ->searchable()
                        ->limit(28)
                        ->placeholder('—')
                        ->toggleable(),
                    TextColumn::make('population_summary')
                        ->label('Población')
                        ->icon(Heroicon::OutlinedUsers)
                        ->searchable()
                        ->badge()
                        ->color('info')
                        ->placeholder('—'),
                    TextColumn::make('issued_at')
                        ->label('Emisión')
                        ->icon(Heroicon::OutlinedCalendarDays)
                        ->date('d/m/Y')
                        ->sortable()
                        ->placeholder('—')
                        ->toggleable(),
                ]),
                ColumnGroup::make('Estructura', [
                    TextColumn::make('columns_count')
                        ->label('Columnas')
                        ->counts('columns')
                        ->badge()
                        ->color('blue')
                        ->alignCenter(),
                    TextColumn::make('rows_count')
                        ->label('Beneficios')
                        ->counts('rows')
                        ->badge()
                        ->color('gray')
                        ->alignCenter(),
                    TextColumn::make('rate_rows_count')
                        ->label('Rangos')
                        ->counts('rateRows')
                        ->badge()
                        ->color('amber')
                        ->alignCenter(),
                ]),
                ColumnGroup::make('Auditoría', [
                    TextColumn::make('created_by')
                        ->label('Creado por')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->searchable()
                        ->toggleable(isToggledHiddenByDefault: true)
                        ->placeholder('—'),
                    TextColumn::make('created_at')
                        ->label('Creado')
                        ->icon(Heroicon::OutlinedClock)
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(),
                    TextColumn::make('updated_at')
                        ->label('Actualizado')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->dateTime('d/m/Y H:i')
                        ->sortable()
                        ->toggleable(isToggledHiddenByDefault: true),
                ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'PRE-APROBADO' => 'PRE-APROBADO',
                        'APROBADA' => 'APROBADA',
                        'ACTIVO' => 'ACTIVO',
                        'INACTIVO' => 'INACTIVO',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver plan'),
                    EditAction::make()
                        ->label('Editar'),
                    DeleteAction::make()
                        ->label('Eliminar'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
