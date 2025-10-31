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
                    // ->summarize(Sum::make()
                    //     ->label(('Total a pagar'))
                    //     ->money('USD'))
                    ->searchable(),
                // TextColumn::make('amount_commission_master_agency')
                //     ->label('Total comisiones(US$)')
                //     ->numeric()
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_master_agency > 0 ? 'success' : 'danger';
                //     })
                //     ->suffix('US$')
                //     ->sortable(),
                // TextColumn::make('amount_commission_general_agency')
                //     ->label('Total comisiones(US$)')
                //     ->numeric()
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_general_agency > 0 ? 'success' : 'danger';
                //     })
                //     ->suffix('US$')
                //     ->sortable(),
                // TextColumn::make('amount_commission_agent')
                //     ->label('Total comisiones(US$)')
                //     ->numeric()
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_agent > 0 ? 'success' : 'danger';
                //     })
                //     ->suffix('US$')
                //     ->sortable(),
                // TextColumn::make('amount_commission_master_agency_usd')
                //     ->label('Detalle Master(US$-VES)')
                //     ->suffix('US$')
                //     ->description(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_master_agency_ves . 'VES';
                //     })
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_master_agency_usd > 0 ? 'success' : 'danger';
                //     })
                //     ->icon(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_master_agency_usd > 0 ? 'heroicon-s-plus' : 'heroicon-s-minus';
                //     })
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_general_agency_usd')
                //     ->label('Detalle General(US$-VES)')
                //     ->suffix('US$')
                //     ->description(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_general_agency_ves . 'VES';
                //     })
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_general_agency_usd > 0 ? 'success' : 'danger';
                //     })
                //     ->icon(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_general_agency_usd > 0 ? 'heroicon-s-plus' : 'heroicon-s-minus';
                //     })
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_agent_usd')
                //     ->label('Detalle Agente(US$-VES)')
                //     ->suffix('US$')
                //     ->description(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_agent_ves . 'VES';
                //     })
                //     ->badge()
                //     ->color(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_agent_usd > 0 ? 'success' : 'danger';
                //     })
                //     ->icon(function (CommissionPayroll $record): string {
                //         return $record->amount_commission_agent_usd > 0 ? 'heroicon-s-plus' : 'heroicon-s-minus';
                //     })
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_master_agency_ves')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_general_agency')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_general_agency_usd')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_general_agency_ves')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_agent')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_agent_usd')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_agent_ves')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_subagent')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_subagent_usd')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('amount_commission_subagent_ves')
                //     ->numeric()
                //     ->sortable(),
                // TextColumn::make('created_by')
                //     ->searchable(),
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
