<?php

namespace App\Filament\Administration\Resources\Sales\Tables;

use App\Filament\Exports\SaleExporter;
use App\Filament\Resources\Commissions\CommissionResource;
use App\Http\Controllers\LogController;
use App\Http\Controllers\SaleController;
use App\Jobs\CreateAvisoDeCobro;
use App\Jobs\SendAvisoDePago;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ExportBulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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
                TextColumn::make('type')
                    ->sortable()
                    ->label('Tipo')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'AFILIACION INDIVIDUAL' => 'primary',
                            'AFILIACION CORPORATIVA' => 'verdeOpaco',
                        };
                    })
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
                TextColumn::make('created_by')
                    ->sortable()
                    ->label('Aprobado por')
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
                    ->description(function (Sale $record) {
                        return $record->reference_payment != null ? 'REF#: ' .$record->reference_payment : 'N/A';
                    })
                    ->searchable(),
                TextColumn::make('payment_method_usd')
                    ->sortable()
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->searchable()
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: ' . $record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),

                TextColumn::make('pay_amount_usd')
                    ->label('Pago registrado')
                    ->sortable()
                    ->searchable()
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
                TextColumn::make('total_amount')
                    ->sortable()
                    ->label('Monto Total')
                    ->money('USD')
                    ->summarize(Sum::make()
                        ->label(('Total de Venta'))
                        ->money('USD'))
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('invoice_generated')
                    ->label('Nro. de Factura')
                    ->sortable()
                    ->searchable(),
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
                                DatePicker::make('desde')->format('d/m/Y'),
                                DatePicker::make('hasta')->format('d/m/Y'),
                            ])->columnSpanFull()->columns(2),                 
                        ])
                        ->action(function (Sale $record, array $data) {
                            try {
                                // dd($record);

                                if($record->type == 'AFILIACION INDIVIDUAL'){
                                    //Consultamo la collection
                                    $sale = Sale::where('id', $record->id)->first();

                                    $afiliacion = Affiliation::where('code', $sale->affiliation_code)->with('paid_memberships')->first();

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

                                    $regenerar = SaleController::regenerateAvisoDePago($data);

                                }

                                if($record->type == 'AFILIACION CORPORATIVA'){

                                    $sale = Sale::where('id', $record->id)->first();

                                    $afiliacion = AffiliationCorporate::where('code', $sale->affiliation_code)->with('paid_membership_corporates', 'affiliationCorporatePlans')->first()->toArray();
                                    // dd($afiliacion);
                                    /**Ejecutamos el Job para crea el aviso de cobro */
                                    $data = [
                                        'invoice_number' => $sale->invoice_number,
                                        'emission_date'  => $sale->created_at->format('d/m/Y'),
                                        'payment_method' => $sale->payment_method,
                                        'reference'      => $record->reference_payment,
                                        'full_name_ti'   => $sale->affiliate_full_name,
                                        'ci_rif_ti'      => $sale->affiliate_ci_rif,
                                        'address_ti'     => $afiliacion['address'],
                                        'phone_ti'       => $afiliacion['phone'],
                                        'email_ti'       => $afiliacion['email'],
                                        'total_amount'   => $sale->total_amount,
                                        'plan'           => $afiliacion['affiliation_corporate_plans'],
                                        'coverage'       => $sale->coverage->price ?? null,
                                        'reference'      => $record->reference_payment,
                                        'frequency'      => $sale->payment_frequency,
                                        'desde'          => $data['desde'],
                                        'hasta'          => $data['hasta'],
                                    ];

                                    $regenerar = SaleController::regenerateAvisoDePagoCorporate($data);
                                    
                                }

                                if ($regenerar) {
                                    Notification::make()
                                        ->title('¡REGENERADO CON EXITO!')
                                        ->body('El recibo de pago se ha regenerado exitosamente.')
                                        ->success()
                                        ->send();
                                } else {
                                    Notification::make()
                                        ->title('¡ERROR!')
                                        ->body('El recibo de pago no se ha regenerado.')
                                        ->danger()
                                        ->send();
                                }
                                
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
                    Action::make('print_invoice')
                        ->label('Generar Factura')
                        ->icon('heroicon-s-printer')
                        ->color('info')
                        ->modalWidth(Width::TwoExtraLarge)
                        ->form(fn(Sale $record): array => $record->invoice_generated != null ? [] : [
                            Section::make('Informacion de la Factura')
                            ->schema([
                                TextInput::make('invoice_number')
                                    ->label('Nro. de Factura')
                                    ->required(),
                                DatePicker::make('date')
                                    ->label('Fecha de Factura')
                                    ->required()
                                    ->format('d/m/Y'),
                                TextInput::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->numeric()   
                                    ->required()
                                    ->hidden(fn(Sale $record) => $record->pay_amount_usd == 0.00),
                            ])->columns(function (Sale $record) {
                                if($record->pay_amount_usd == 0.00){
                                    return 2;
                                }
                                return 3;
                            }),
                        ])
                        ->action(function (Sale $record, array $data) {

                            try {

                                if($record->invoice_generated != null){
                                    return response()->download(public_path('storage/facturas/FACT-' . $record->invoice_generated . '.pdf'));
                                }
                                
                                //Consultamo la collection
                                $sale = Sale::where('id', $record->id)->first();


                                $afiliacion = Affiliation::where('code', $sale->affiliation_code)->with('paid_memberships')->first();

                                if(isset($data['tasa_bcv'])){
                                    $calculo = $data['tasa_bcv'] * $sale->pay_amount_usd;
                                }else{
                                    $calculo = $sale->pay_amount_ves;
                                }

                                /**Ejecutamos el Job para crea el aviso de cobro */
                                $data_factura = [
                                    'invoice_number' => $data['invoice_number'],
                                    'emission_date'  => $data['date'],
                                    'payment_method' => $sale->payment_method,
                                    'reference'      => $record->reference_payment,
                                    'full_name_ti'   => $sale->affiliate_full_name,
                                    'ci_rif_ti'      => $sale->affiliate_ci_rif,
                                    'address_ti'     => $afiliacion['adress_ti'],
                                    'phone_ti'       => $afiliacion['phone_ti'],
                                    'email_ti'       => $afiliacion['email_ti'],
                                    'total_amount'   => $calculo,
                                    'plan'           => $sale->plan->description,
                                    'coverage'       => $sale->coverage->price ?? null,
                                    'reference'      => $record->reference_payment,
                                    'frequency'      => $sale->payment_frequency,
                                ];

                                ini_set('memory_limit', '2048M');

                                //Nombre del pdf
                                $name_pdf = 'FACT-' . $data['invoice_number'] . '.pdf';
                                
                                //Generamos el pdf
                                $pdf = Pdf::loadView('documents.factura', compact('data_factura'));

                                //Guardamos el pdf
                                $pdf->save(public_path('storage/facturas/' . $name_pdf));

                                //Actualizamos la factura
                                $record->invoice_generated = $data['invoice_number'];
                                $record->save();

                                //Descargamos el pdf
                                return response()->download(public_path('storage/facturas/' . $name_pdf));

                                
                            } catch (\Throwable $th) {
                                Log::info($th->getMessage());
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->deselectRecordsAfterCompletion()
                        ->requiresConfirmation()
                        ->color('danger')
                        ->icon('heroicon-m-trash')
                        ->label('Eliminar Registro(s)')
                        ->modalHeading('ELIMINAR REGISTRO DE VENTA(S)')
                        ->modalDescription('Esta accion eliminara los registros de venta seleccionados, asi como sus respectivas facturas y comisiones.')
                        ->action(function (Collection $records) {
                            
                            try {
                                
                                foreach ($records as $record) {
                                    
                                    //Eliminamos el registro de la venta
                                    $record->delete();
                                    
                                    //Elinimo el vaucher de pago cargado
                                    $record->paidMembershipIndividual()->delete();
                                    $record->paidMembershipCorporate()->delete();

                                    //Elimino la comision de la venta
                                    $record->commission()->delete();
                                }

                                Notification::make()
                                    ->title('¡ELIMINADO CON EXITO!')
                                    ->body('Los registros de venta se han eliminado exitosamente.')
                                    ->icon('heroicon-s-check-circle')
                                    ->iconColor('success')
                                    ->success()
                                    ->send();

                            } catch (\Throwable $th) {
                                Notification::make()
                                    ->title('ERROR')
                                    ->body($th->getMessage() . ' Linea: ' . $th->getLine() . ' Archivo: ' . $th->getFile())
                                    ->icon('heroicon-s-x-circle')
                                    ->iconColor('danger')
                                    ->danger()
                                    ->send();
                            }
                        }),
                    ExportBulkAction::make()->exporter(SaleExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}