<?php

namespace App\Filament\Agents\Resources\Commissions\Tables;

use Carbon\Carbon;
use App\Models\Commission;
use Filament\Tables\Table;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Illuminate\Support\Facades\Auth;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\Summarizers\Sum;

class CommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('El detalle puede ser ordenado de forma ascendente o descendente. Por defecto se ordena por la fecha de creación.')
            ->query(Commission::query()->where('agent_id', Auth::user()->agent_id))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('date_payment_affiliate')
                    ->label('Fecha del comprobante')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->datetime('d/m/Y')
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
                TextColumn::make('veto')
                    ->label('Período')
                    ->searchable(),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->searchable(),


                /**
                 * PORCENTAJES TDEC
                 * --------------------------------------------------------------
                 */
                TextColumn::make('invoice_number')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. de recibo o factura')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Monto pagado')
                    ->money('USD')
                    ->sortable(),
                TextColumn::make('payment_method')
                    ->label('Método de pago')
                    ->searchable(),
                TextColumn::make('commission_agent_tdec')
                    ->label('% Agente TDEC')
                    ->badge()
                    ->suffix('%')
                    ->color('warning')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('commission_agent')
                    ->label('Comisión')
                    ->badge()
                    ->suffix(' US$')
                    ->numeric()
                    ->sortable()
                    ->summarize(Sum::make()
                        ->label(('Subtotal Agentes'))
                        ->suffix(' US$')
                        ->numeric()),
                TextColumn::make('date_payment_commission')
                    ->label('Fecha de pago')
                    ->badge()
                    ->icon('heroicon-s-calendar-days')
                    ->searchable(),
                /**-------------------------------------------------------------- */

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
            ])
            ->recordActions([
                // ViewAction::make(),
                // EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),
                ]),
            ]);
    }
}