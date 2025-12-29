<?php

namespace App\Filament\Administration\Resources\CommissionPayrolls\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

use Carbon\Carbon;
use Filament\Actions\ViewAction;
use App\Models\CommissionPayroll;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Grouping\Group;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class CommissionPayrollsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('REPORTE DE COMISIONES')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('date_ini')
                    ->label('Desde')
                    ->searchable(),
                TextColumn::make('date_end')
                    ->label('Hasta')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('owner_name')
                    ->label('Nombre/Razón Social')
                    ->searchable(),
                TextColumn::make('total_commission')
                    ->label('Total comisiones')
                    ->numeric()
                    ->suffix('US$')
                    ->badge()
                    ->icon('heroicon-c-user-plus')
                    ->color(function (CommissionPayroll $record): string {
                        return $record->total_commission > 0 ? 'success' : 'danger';
                    })
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['hasta'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['desde'] ?? null) {
                            $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                        }
                        if ($data['hasta'] ?? null) {
                            $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                        }

                        return $indicators;
                    }),
                // SelectFilter::make('payment_frequency')
                //     ->native(false)
                //     ->options([
                //         'ANUAL'      => 'ANUAL',
                //         'SEMESTRAL'  => 'SEMESTRAL',
                //         'TRIMESTRAL' => 'TRIMESTRAL',
                //         'MENSUAL'    => 'MENSUAL',
                //     ])
                //     ->label('Frecuencia de Pago'),
                // SelectFilter::make('plan_id')
                //     ->native(false)
                //     ->relationship('plan', 'description')
                //     ->label('Planes'),
                // SelectFilter::make('payment_method')
                //     ->native(false)
                //     ->options([
                //         'EFECTIVO US$'      => 'EFECTIVO US$',
                //         'ZELLE'             => 'ZELLE',
                //         'PAGO MOVIL VES'    => 'PAGO MOVIL VES',
                //         'TRANSFERENCIA VES' => 'TRANSFERENCIA VES'
                //     ])
                //     ->label('Metodo de Pago'),
                // SelectFilter::make('bank')
                //     ->native(false)
                //     ->options([
                //         'CHASE BANK'                => 'CHASE BANK',
                //         'BANK OF AMERICA'           => 'BANK OF AMERICA',
                //         'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                //         'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                //         'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                //         'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                //         'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                //     ])
                //     ->label('Banco'),

            ])
            ->groups([
                Group::make('type')
                    ->label('Tipo'),
            ])
            ->recordActions([

            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
