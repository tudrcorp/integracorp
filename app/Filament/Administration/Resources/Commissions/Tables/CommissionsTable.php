<?php

namespace App\Filament\Administration\Resources\Commissions\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\Filter;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;

use App\Http\Controllers\CommissionController;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('COMISIONES')
            ->description('Registro de pagos(ventas) de afiliaciones activas. Detallado por agencias y agentes')
            ->defaultSort('created_at', 'desc')
            ->columns([
                // TextColumn::make('date_payment_affiliate')
                //     ->label('Pago del afiliado')
                //     ->badge()
                //     ->icon('heroicon-s-calendar-days')
                //     ->searchable(),
                TextColumn::make('code')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Factura')
                    ->searchable(),
                TextColumn::make('code_agency')
                    ->label('Agencia')
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->searchable(),
                // Tables\Columns\TextColumn::make('owner_code')
                //     ->label('Master')
                //     ->badge()
                //     ->icon('heroicon-s-building-library')
                //     ->searchable(),
                TextColumn::make('agent.name')
                    ->label('Agente')
                    ->badge()
                    ->icon('heroicon-s-user')
                    ->numeric()
                    ->sortable(),
                ColumnGroup::make('Información de la Afiliación')->columns([
                    TextColumn::make('affiliation_code')
                        ->label('Nro. de Afiliación')->badge()->color('info')
                        ->searchable(),
                    TextColumn::make('affiliate_full_name')
                        ->label('Afiliado')
                        ->searchable(),
                    TextColumn::make('plan.description')
                        ->badge()
                        ->icon('heroicon-s-cube')
                        ->color('verde')
                        ->label('Plan')
                        ->numeric()
                        ->searchable(),
                    TextColumn::make('coverage.price')
                        ->badge()
                        ->icon('heroicon-s-cube')
                        ->color('verde')
                        ->label('Cobertura')
                        ->suffix('US$')
                        ->numeric()
                        ->searchable(),
                    TextColumn::make('amount')
                        ->label('Importe')
                        ->money('USD')
                        ->sortable(),
                    TextColumn::make('veto')
                        ->label('Veto')->badge()->color('info')
                        ->searchable(),
                    TextColumn::make('payment_frequency')
                        ->label('Frecuencia de pago')->badge()->color('info')
                        ->searchable(),
                ]),
                
                TextColumn::make('date_payment_commission')
                    ->label('Pagado el:')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),

                ColumnGroup::make('Estructura de Comisiones en Bolivares(VES)')->columns([
                    TextColumn::make('commission_agency_master_usd')
                        ->label('Pago Agencia Master')
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agencia Master'))
                            ->suffix(' US$')
                            ->numeric()),
                    TextColumn::make('commission_agency_general_usd')
                        ->label('Pago Agencia General')
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agencia General'))
                            ->suffix(' US$')
                            ->numeric()),
                    TextColumn::make('commission_agent_usd')
                        ->label('Pago Agente')
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agentes'))
                            ->suffix(' US$')
                            ->numeric()),
                ]),

                ColumnGroup::make('Estructura de Comisiones en Dolares(US$)')->columns([
                    TextColumn::make('commission_agency_master_ves')
                        ->label('Pago Agencia Master')
                        ->badge()
                        ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agencia Master'))
                            ->suffix(' VES')
                            ->numeric()),
                    TextColumn::make('commission_agency_general_ves')
                        ->label('Pago Agencia General')
                        ->badge()
                        ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agencia General'))
                            ->suffix(' VES')
                            ->numeric()),
                    TextColumn::make('commission_agent_ves')
                        ->label('Pago Agente')
                        ->badge()
                        ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal Agentes'))
                            ->suffix(' VES')
                            ->numeric()),
                ]),

                TextColumn::make('created_at')
                    ->label('Fecha de calculo')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // CommissionMaster::make('commision_master')
                //     ->label('Comisión agencia master')
                //     ->alignCenter(),
                // CommissionGeneral::make('commision_general')
                //     ->label('Comisión agencia general')
                //     ->alignCenter(),
                // CommissionAgent::make('commision_agent')
                //     ->label('Comisión Agente')
                //     ->alignCenter(),
                // PaymentInfo::make('payment_info')
                //     ->label('Información de pago')
                //     ->alignCenter(),
                // AmountInfo::make('amount_info')
                //     ->label('Información de importe')
                //     ->alignCenter(),
                // DetailCommissionMaster::make('detail_commission_master')
                //     ->label('Detalle comisión agencia master')
                //     ->alignCenter(),
                // DetailCommissionGeneral::make('detail_commission_general')
                //     ->label('Detalle comisión agencia general')
                //     ->alignCenter(),
                // DetailCommissionAgent::make('detail_commission_agent')
                //     ->label('Detalle comisión agente')
                //     ->alignCenter(),

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
                //     ->options([
                //         'ANUAL'      => 'ANUAL',
                //         'SEMESTRAL'  => 'SEMESTRAL',
                //         'TRIMESTRAL' => 'TRIMESTRAL',
                //         'MENSUAL'    => 'MENSUAL',
                //     ])
                //     ->label('Frecuencia de Pago'),
                // SelectFilter::make('plan_id')
                //     ->relationship('plan', 'description')
                //     ->label('Planes'),
                // SelectFilter::make('payment_method')
                //     ->options([
                //         'EFECTIVO US$'      => 'EFECTIVO US$',
                //         'ZELLE'             => 'ZELLE',
                //         'PAGO MOVIL VES'    => 'PAGO MOVIL VES',
                //         'TRANSFERENCIA VES' => 'TRANSFERENCIA VES'
                //     ])
                //     ->label('Metodo de Pago'),
                // SelectFilter::make('bank')
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
            ->recordActions([
                // ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('generate_payroll')
                        ->label('Totalizar comisiones')
                        ->color('success')
                        ->icon('heroicon-s-check-circle')
                        ->deselectRecordsAfterCompletion()
                        ->action(function (EloquentCollection $records) {

                            $dataArray = $records->toArray();

                            $calculo = CommissionController::calculateCommission($dataArray);

                            if ($calculo) {
                                Notification::make()
                                    ->body('NOTIFICACION')
                                    ->title('El calculo de comisiones se ha realizado con éxito')
                                    ->icon('heroicon-s-check-circle')
                                    ->success()
                                    ->send();
                            } else {
                                Notification::make()
                                    ->body('EXCEPTION')
                                    ->title('Error de calculo')
                                    ->icon('heroicon-s-x-circle')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}