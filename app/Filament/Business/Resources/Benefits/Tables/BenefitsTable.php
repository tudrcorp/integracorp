<?php

namespace App\Filament\Business\Resources\Benefits\Tables;

use App\Models\ConfigCostoBenefit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Number;

class BenefitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Beneficios')
            ->description('Gestión de beneficios asociados a los planes. Puedes editar costos directamente en la tabla.')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->icon('heroicon-m-clipboard-document-list')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('description')
                    ->label('Definición')
                    ->icon('heroicon-m-pencil-square')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                IconColumn::make('is_upgrade')
                    ->label('Upgrade')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-trending-up')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('limit.description')
                    ->label('Límite')
                    ->icon('heroicon-m-adjustments-horizontal')
                    ->badge()
                    ->color('gray')
                    ->placeholder('-')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon('heroicon-m-shield-check')
                    ->badge()
                    ->color(fn (mixed $state): string => match (strtoupper((string) $state)) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),

                ColumnGroup::make('Estructura de costos')
                    ->columns([
                        TextInputColumn::make('neto')
                            ->label('Neto')
                            ->type('number')
                            ->placeholder('0.00')
                            ->prefix('US$')
                            ->alignEnd()
                            ->searchable(),
                        TextInputColumn::make('porcentaje_incremento')
                            ->label('% Estructura')
                            ->type('number')
                            ->placeholder('0.00')
                            ->prefix('%')
                            ->alignEnd()
                            ->afterStateUpdated(function ($record, $state) {
                                $record->pvp = ($state * $record->neto) / 100;
                                $porcenComicion = ConfigCostoBenefit::first();
                                $record->porcen_comision = Number::format($porcenComicion->porcen_comision * $record->pvp / 100, precision: 0);
                                $record->porcen_utilidad = Number::format($porcenComicion->porcen_utilidad * $record->pvp / 100, precision: 0);
                                $record->porcen_acu_adi = Number::format($porcenComicion->porcen_acu_adi * $record->pvp / 100, precision: 0);
                                $record->updated_by = Auth::user()->name;
                                $record->save();
                            })
                            ->searchable(),
                        TextInputColumn::make('pvp')
                            ->label('PVP')
                            ->prefix('US$')
                            ->disabled()
                            ->alignEnd()
                            ->searchable(),
                        TextInputColumn::make('porcen_comision')
                            ->label('Comisión')
                            ->prefix('US$')
                            ->disabled()
                            ->alignEnd()
                            ->searchable(),
                        TextInputColumn::make('porcen_utilidad')
                            ->label('Utilidad')
                            ->prefix('US$')
                            ->disabled()
                            ->alignEnd()
                            ->searchable(),
                        TextInputColumn::make('porcen_acu_adi')
                            ->label('Acu. Adi.')
                            ->prefix('US$')
                            ->disabled()
                            ->alignEnd()
                            ->searchable(),
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                    ]),
                SelectFilter::make('is_upgrade')
                    ->label('Tipo')
                    ->options([
                        1 => 'Beneficio upgrade',
                        0 => 'Beneficio estándar',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Desde'),
                        DatePicker::make('hasta')
                            ->label('Hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $q, $date): Builder => $q->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if (! empty($data['desde'])) {
                            $indicators['desde'] = 'Desde '.Carbon::parse($data['desde'])->translatedFormat('d/m/Y');
                        }
                        if (! empty($data['hasta'])) {
                            $indicators['hasta'] = 'Hasta '.Carbon::parse($data['hasta'])->translatedFormat('d/m/Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-o-funnel'),
            )
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon('heroicon-o-eye'),
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square'),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar beneficio')
                        ->modalDescription('¿Estás seguro? Esta acción no se puede deshacer.')
                        ->color('danger'),
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->label('Más'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar beneficios seleccionados')
                        ->modalDescription('Los beneficios seleccionados se eliminarán de forma permanente.')
                        ->successNotificationTitle('Beneficios eliminados'),
                ]),
            ])
            ->emptyStateHeading('No hay beneficios')
            ->emptyStateDescription('Crea el primer beneficio para asociarlo a los planes.')
            ->emptyStateIcon('heroicon-o-sparkles')
            ->striped();
    }
}
