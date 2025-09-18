<?php

namespace App\Filament\Resources\Sales\Tables;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Collection;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Tables\Filters\Filter;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\TextColumn;
use App\Http\Controllers\SaleController;
use Filament\Notifications\Notification;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;

use App\Filament\Resources\Commissions\CommissionResource;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;


class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('VENTAS')
            ->description('Registro de pagos(ventas) de afiliaciones activas')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Recibo de Pago')
                    ->searchable(),
                TextColumn::make('affiliation_code')
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->label('Agencia')
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),
                TextColumn::make('agent.name')
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->label('Agente')
                    ->numeric()
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
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->label('CI/RIF')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                    ->label('Email')
                    ->searchable(),

                // TextColumn::make('service')
                //     ->searchable(),
                TextColumn::make('persons')
                    ->label('Población')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Forma de pago')
                    ->badge()
                    // ->description(function (Sale $record) {
                    //     return $record->total_amount_ves != null ? $record->total_amount_ves : 0.00 . ' VES';
                    // })
                    ->searchable(),
                TextColumn::make('payment_method_usd')
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: ' . $record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),

                TextColumn::make('pay_amount_usd')
                    ->label('Pago registrado')
                    ->suffix(' US$')
                    ->description(function ($record) {
                        return $record->pay_amount_ves != 'N/A' ? number_format($record->pay_amount_ves, 2, ',', '.') . ' VES' : 'N/A';
                    }),

                TextColumn::make('bank_usd')
                    ->searchable()
                    ->label('Banco')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->bank_ves != 'N/A' ? 'VES: ' . $record->bank_ves : 'VES: N/A';
                    }),
                TextColumn::make('status_payment_commission')
                    ->label('Comision de venta')
                    ->badge()
                    ->color(function (Sale $record) {
                        return $record->status_payment_commission == 'COMISION PAGADA' ? 'success' : 'warning';
                    })
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Monto Total')
                    ->money('USD')
                    ->summarize(Sum::make()
                        ->label(('Total de Venta'))
                        ->money('USD'))
                    ->alignCenter()
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
                SelectFilter::make('payment_frequency')
                    ->native(false)
                    ->options([
                        'ANUAL'      => 'ANUAL',
                        'SEMESTRAL'  => 'SEMESTRAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'MENSUAL'    => 'MENSUAL',
                    ])
                    ->label('Frecuencia de Pago'),
                SelectFilter::make('plan_id')
                    ->native(false)
                    ->relationship('plan', 'description')
                    ->label('Planes'),
                SelectFilter::make('payment_method')
                    ->native(false)
                    ->options([
                        'EFECTIVO US$'      => 'EFECTIVO US$',
                        'ZELLE'             => 'ZELLE',
                        'PAGO MOVIL VES'    => 'PAGO MOVIL VES',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA VES'
                    ])
                    ->label('Metodo de Pago'),
                SelectFilter::make('bank')
                    ->native(false)
                    ->options([
                        'CHASE BANK'                => 'CHASE BANK',
                        'BANK OF AMERICA'           => 'BANK OF AMERICA',
                        'BANESCO, S.A-US$'          => 'BANESCO, S.A - US$',
                        'BANCAMIGA - US$'           => 'BANCAMIGA - US$',
                        'BANCAMIGA - VES'           => 'BANCAMIGA - VES',
                        'BANCO DE VENEZUELA - US$'  => 'BANCO DE VENEZUELA - US$',
                        'BANCO DE VENEZUELA - VES'  => 'BANCO DE VENEZUELA - VES',
                    ])
                    ->label('Banco'),

            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('download_pdf')
                        ->label('Descargar PDF')
                        ->icon('heroicon-s-arrow-down-on-square-stack')
                        ->color('verde')
                        ->action(function (Sale $record) {
                            try {
                                /**
                                 * Descargar el documento asociado a la cotizacion
                                 * ruta: storage/
                                 */
                                $path = public_path('storage/reciboDePago/ADP-' . $record->invoice_number . '.pdf');
                                return response()->download($path);
                                /**
                                 * LOG
                                 */
                                LogController::log(Auth::user()->id, 'Descarga de documento', 'Modulo Cotizacion Individual', 'DESCARGAR');
                            } catch (\Throwable $th) {
                                LogController::log(Auth::user()->id, 'EXCEPTION', 'agents.IndividualQuoteResource.action.enit', $th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        })
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('calculate_commission')
                        ->label('Calcular Comisiones')
                        ->color('verde')
                        ->icon('heroicon-s-calculator')
                        ->action(function (EloquentCollection $records, $livewire) {

                            $desde = $livewire->getTableFilterState('created_at')['desde'];
                            $hasta = $livewire->getTableFilterState('created_at')['hasta'];

                            //El usuario debe seleccionar un periodo, de lo contrario no se realiza el calculo
                            if(!$desde || !$hasta){
                                Notification::make()
                                    ->title('ERROR')
                                    ->body('Debe seleccionar un periodo de fechas. Por favor intente nuevamente.')
                                    ->icon(Heroicon::ShieldExclamation)
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                                return;
                            }

                            $preCalculateCommissions = SaleController::preCalculateCommission($records, $desde, $hasta);

                            if ($preCalculateCommissions) {
                                
                                /**Notificacion de proceso realizado con boton para redireccionar al resultado */
                                $desde = $livewire->getTableFilterState('created_at')['desde'];
                                $hasta = $livewire->getTableFilterState('created_at')['hasta'];

                                Notification::make()
                                    ->title('CALCULO DE COMISIONES')
                                    ->body('El calculo de las comisiones del periodo: DESDE: ' . $desde . ' HASTA: ' . $hasta . ' se ha realizado con exito')
                                    ->icon('heroicon-m-user-plus')
                                    ->iconColor('success')
                                    ->success()
                                    ->seconds(10)
                                    ->actions([
                                        Action::make('view')
                                            ->label('Ver detalle de claculo')
                                            ->button()
                                            ->url(CommissionResource::getUrl(panel: 'admin') . '?tableFilters[created_at][desde]=' . $desde . '&tableFilters[created_at][hasta]=' . $hasta),
                                    ])
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}