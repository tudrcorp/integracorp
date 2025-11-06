<?php

namespace App\Filament\Administration\Resources\Sales\Tables;

use Carbon\Carbon;
use App\Models\Sale;
use App\Models\Collection;
use Filament\Tables\Table;
use App\Models\Affiliation;

use Filament\Actions\Action;
use App\Jobs\SendAvisoDePago;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\CreateAvisoDeCobro;
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
use Filament\Schemas\Components\Fieldset;
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
                TextColumn::make('created_at')
                    ->sortable()
                    ->label('Fecha')
                    ->dateTime()
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Recibo de Pago')
                    ->searchable(),
                TextColumn::make('affiliation_code')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->sortable()
                    ->label('Agencia')
                    ->badge()
                    ->color('verde')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),
                TextColumn::make('agent.name')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-m-user')
                    ->label('Agente')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->color('verde')
                    ->label('Plan')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->sortable()
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->color('verde')
                    ->label('Cobertura')
                    ->suffix('US$')
                    ->numeric()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                    ->sortable()
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                    ->sortable()
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->sortable()
                    ->label('CI/RIF')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->sortable()
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                    ->sortable()
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('persons')
                    ->sortable()
                    ->label('Población')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->sortable()
                    ->label('Aprobado por')
                    ->searchable(),
                TextColumn::make('type')
                    ->sortable()
                    ->label('Tipo')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->sortable()
                    ->label('Frecuencia')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->sortable()
                    ->label('Forma de pago')
                    ->badge()
                    // ->description(function (Sale $record) {
                    //     return $record->total_amount_ves != null ? $record->total_amount_ves : 0.00 . ' VES';
                    // })
                    ->searchable(),
                TextColumn::make('payment_method_usd')
                ->sortable()
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: ' . $record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),

                TextColumn::make('pay_amount_usd')
                    ->label('Pago registrado')
                    ->sortable()
                    ->suffix(' US$')
                    ->description(function ($record) {
                        return $record->pay_amount_ves != 'N/A' ? number_format($record->pay_amount_ves, 2, ',', '.') . ' VES' : 'N/A';
                    }),

                TextColumn::make('bank_usd')
                ->sortable()
                    ->searchable()
                    ->label('Banco')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->bank_ves != 'N/A' ? 'VES: ' . $record->bank_ves : 'VES: N/A';
                    }),
                TextColumn::make('status_payment_commission')
                ->sortable()
                    ->label('Comision de venta')
                    ->badge()
                    ->color(function (Sale $record) {
                        return $record->status_payment_commission == 'COMISION PAGADA' ? 'success' : 'warning';
                    })
                    ->searchable(),
                TextColumn::make('total_amount')
                ->sortable()
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
                                $path = public_path('storage/reciboDePago/RDP-' . $record->invoice_number . '.pdf');
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
                        }),
                    Action::make('regenate_pdf')
                        ->label('Regenerar PDF')
                        ->icon('heroicon-o-wrench-screwdriver')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->form([
                            Fieldset::make('Periodo de Vigencia')->schema([
                                DatePicker::make('desde')->required()->format('d/m/Y'),
                                DatePicker::make('hasta')->required()->format('d/m/Y'),
                            ])->columnSpanFull()->columns(2),                 
                        ])
                        ->action(function (Sale $record, array $data) {
                            try {
                                
                                //Consultamo la collection
                                $sale = Sale::where('id', $record->id)->first();
                                // dd($sale, $sale->created_at->format('d/m/Y'));

                                $afiliacion = Affiliation::where('code', $sale->affiliation_code)->with('paid_memberships')->first();
                                // dd($sale, $afiliacion->toArray());
                                
                                /**Ejecutamos el Job para crea el aviso de cobro */
                                $data = [
                                    'invoice_number' => $sale->invoice_number,
                                    'emission_date'  => $sale->created_at->format('d/m/Y'),
                                    'payment_method' => $sale->payment_method,
                                    'reference'      => $record->reference_payment,
                                    'full_name_ti'   => $sale->affiliate_full_name,
                                    'ci_rif_ti'      => $sale->affiliate_ci_rif,
                                    'address_ti'     => $afiliacion['adress_ti'],
                                    'phone_ti'       => $afiliacion['phone_ti'],
                                    'email_ti'       => $afiliacion['email_ti'],
                                    'total_amount'   => $sale->total_amount,
                                    'plan'           => $sale->plan->description,
                                    'coverage'       => $sale->coverage->price ?? null,
                                    'reference'      => $record->reference_payment,
                                    'frequency'      => $sale->payment_frequency,
                                    'desde'          => $data['desde'],
                                    'hasta'          => $data['hasta'],
                                ];

                                ini_set('memory_limit', '2048M');

                                $name_pdf = 'RDP-' . $data['invoice_number'] . '.pdf';

                                $pdf = Pdf::loadView('documents.regenerar-aviso-de-pago', compact('data'));
                                $pdf->save(public_path('storage/reciboDePago/' . $name_pdf));

                                Notification::make()
                                    ->title('¡REGENERADO CON EXITO!')
                                    ->body('El recibo de pago se ha regenerado exitosamente.')
                                    ->success()
                                    ->send();
                                
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