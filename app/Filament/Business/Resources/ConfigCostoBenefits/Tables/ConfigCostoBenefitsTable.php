<?php

namespace App\Filament\Business\Resources\ConfigCostoBenefits\Tables;

use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConfigCostoBenefitsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->heading('Configuración de costos (beneficios)')
            ->description('Porcentajes aplicados al cálculo de PVP, comisión, utilidad y acumulado adicional en beneficios.')
            ->columns([
                TextColumn::make('porcen_comision')
                    ->label('% Comisión')
                    ->icon('heroicon-m-percent-badge')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('porcen_utilidad')
                    ->label('% Utilidad')
                    ->icon('heroicon-m-chart-bar')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('porcen_acu_adi')
                    ->label('% Acum. Adicional')
                    ->icon('heroicon-m-banknotes')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->alignCenter()
                    ->sortable()
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->icon('heroicon-m-calendar')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->icon('heroicon-m-clock')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde')
                            ->label('Creado desde'),
                        DatePicker::make('hasta')
                            ->label('Creado hasta'),
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
                    }),
            ])
            ->filtersTriggerAction(
                fn ($action) => $action
                    ->button()
                    ->label('Filtros')
                    ->icon('heroicon-o-funnel'),
            )
            ->recordActions([
                EditAction::make()
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square'),
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->modalHeading('Eliminar configuración')
                        ->modalDescription('¿Eliminar esta configuración de costos? Esta acción no se puede deshacer.')
                        ->color('danger'),
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->label('Más'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar configuraciones seleccionadas')
                        ->modalDescription('Las configuraciones seleccionadas se eliminarán de forma permanente.')
                        ->successNotificationTitle('Configuraciones eliminadas'),
                ]),
            ])
            ->emptyStateHeading('No hay configuraciones de costo')
            ->emptyStateDescription('Crea la primera configuración para definir los porcentajes de comisión, utilidad y acumulado adicional.')
            ->emptyStateIcon('heroicon-o-calculator')
            ->striped();
    }
}
