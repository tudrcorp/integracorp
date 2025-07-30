<?php

namespace App\Filament\Agents\Resources\Sales\Tables;

use Carbon\Carbon;
use App\Models\Sale;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;

class SalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('En esta sección se muestran todas las ventas realizadas de tipo Individual y Corporativa')
            ->query(Sale::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('date')
                    ->label('Fecha')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('invoice_number')
                    ->label('Nro. Factura')
                    ->badge()
                    ->icon('heroicon-s-receipt-refund')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliation_code')
                    ->label('Código Afiliación')
                    ->badge()
                    ->icon('heroicon-s-user-group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('affiliate_full_name')
                    ->label('Afiliado')
                    ->searchable(),
                TextColumn::make('affiliate_contact')
                    ->label('Contacto')
                    ->searchable(),
                TextColumn::make('affiliate_ci_rif')
                    ->label('CI / RIF')
                    ->searchable(),
                TextColumn::make('affiliate_phone')
                    ->label('Número de teléfono')
                    ->searchable(),
                TextColumn::make('affiliate_email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->searchable(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->badge()
                    ->icon('heroicon-s-cube')
                    ->numeric()
                    ->suffix('US$')
                    ->sortable(),
                TextColumn::make('persons')
                    ->label('Población')
                    ->suffix(' persona(s)')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('type')
                    ->label('Tipo Afiliación')
                    ->badge()
                    ->icon('heroicon-s-user')
                    ->searchable(),
                TextColumn::make('status_payment_commission')
                    ->label('Comisión de venta')
                    ->badge()
                    ->color(function (Sale $record) {
                        return $record->status_payment_commission == 'COMISION PAGADA' ? 'success' : 'warning';
                    })
                    ->searchable(),
                TextColumn::make('total_amount')
                    ->label('Monto Total')
                    ->money('USD')
                    ->summarize(Sum::make()
                        ->label(('Total Venta'))
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
                    ->options([
                        'ANUAL'      => 'ANUAL',
                        'SEMESTRAL'  => 'SEMESTRAL',
                        'TRIMESTRAL' => 'TRIMESTRAL',
                        'MENSUAL'    => 'MENSUAL',
                    ])
                    ->label('Frecuencia de Pago'),
                SelectFilter::make('plan_id')
                    ->relationship('plan', 'description')
                    ->label('Planes'),
                SelectFilter::make('payment_method')
                    ->options([
                        'EFECTIVO US$'      => 'EFECTIVO US$',
                        'ZELLE'             => 'ZELLE',
                        'PAGO MOVIL VES'    => 'PAGO MOVIL VES',
                        'TRANSFERENCIA VES' => 'TRANSFERENCIA VES'
                    ])
                    ->label('Metodo de Pago'),
                SelectFilter::make('status_payment_commission')
                    ->options([
                        'POR PAGAR'                => 'POR PAGAR',
                        'COMISION PAGADA'           => 'COMISION PAGADA',
                    ])
                    ->label('Estatus de comisión'),

            ])
            ->recordActions([
                // ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
