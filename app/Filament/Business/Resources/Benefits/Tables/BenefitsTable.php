<?php

namespace App\Filament\Business\Resources\Benefits\Tables;

use App\Models\ConfigCostoBenefit;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\Filter;
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
            ->heading('BENEFICIOS')
            ->description('Lista de beneficios registrados en el sistema')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('description')
                    ->label('Definición')
                    ->badge()
                    ->color('verde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('limit.description')
                    ->label('Limite')
                    ->badge()
                    ->color('verde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                        };
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),

                ColumnGroup::make('Estructura de Costos')
                    ->columns([
                        TextInputColumn::make('neto')
                            ->label('Neto')
                            ->type('number')
                            ->placeholder('0.00')
                            ->prefix('US$')
                            ->searchable(),
                        TextInputColumn::make('porcentaje_incremento')
                            ->label('% Estructura de Costo')
                            ->type('number')
                            ->placeholder('0.00')
                            ->prefix('%')
                            ->afterStateUpdated(function ($state, $record) {
                                /** Calculo PVP */
                                $record->pvp = ($state * $record->neto) / 100;

                                /** Calculo Comision */
                                $porcenComicion = ConfigCostoBenefit::first();
                                $record->porcen_comision = Number::format($porcenComicion->porcen_comision * $record->pvp / 100, precision: 0);

                                /** Calculo Utilidad */
                                $record->porcen_utilidad = Number::format($porcenComicion->porcen_utilidad * $record->pvp / 100, precision: 0);

                                /** Calculo Acu. Adi. */
                                $record->porcen_acu_adi = Number::format($porcenComicion->porcen_acu_adi * $record->pvp / 100, precision: 0);

                                /** Actualizado por: */
                                $record->updated_by = Auth::user()->name;
                                $record->save();
                            })
                            ->searchable(),
                        TextInputColumn::make('pvp')
                            ->label('Precio')
                            ->prefix('PVP: US$')
                            ->disabled()
                            // ->numeric()
                            ->searchable(),
                        TextInputColumn::make('porcen_comision')
                            ->label('% de Comision')
                            ->prefix('US$')
                            ->disabled()
                            // ->numeric()
                            ->searchable(),
                        TextInputColumn::make('porcen_utilidad')
                            ->label('% de Utilidad')
                            ->prefix('US$')
                            ->disabled()
                            // ->numeric()
                            ->searchable(),
                        TextInputColumn::make('porcen_acu_adi')
                            ->label('% de Acu. Adi.')
                            ->prefix('US$')
                            ->disabled()
                            // ->numeric()
                            ->searchable(),
                    ]),

            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('desde'),
                        DatePicker::make('hasta'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['desde'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde '.Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta '.Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
            ])
            ->filtersTriggerAction(
                fn (Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
            ->recordActions([
                ActionGroup::make([
                    DeleteAction::make()
                        ->label('Eliminar')
                        ->icon('heroicon-o-trash')
                        ->requiresConfirmation()
                        ->color('danger'),

                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->striped();
    }
}
