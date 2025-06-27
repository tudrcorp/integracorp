<?php

namespace App\Filament\Master\Resources\Sales\Tables;

use App\Models\Sale;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\Summarizers\Sum;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
        ->defaultSort('created_at', 'desc')
            ->query(Sale::query()->whereIn('owner_code', [Auth::user()->code_agency, 'TDG-100']))
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Factura')
                    ->searchable(),
                TextColumn::make('affiliation_code')
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->label('Afiliación')
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->badge()
                    ->icon('heroicon-s-building-library')
                    ->label('Agencia')
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
                    ->label('Tipo afiliación')
                    ->badge()
                    ->searchable(),
                TextColumn::make('payment_method')
                    ->label('Metodo de Pago')
                    ->description(function (Sale $record) {
                        return $record->total_amount_ves != null ? $record->total_amount_ves : 0.00 . ' VES';
                    })
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia')
                    ->searchable(),
                TextColumn::make('bank')
                    ->label('Banco')
                    ->searchable(),
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
                //
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
                ])
                    ->icon('heroicon-c-ellipsis-vertical')
                    ->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}