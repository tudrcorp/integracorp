<?php

namespace App\Filament\Administration\Resources\Commissions\Tables;

use Carbon\Carbon;
use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\BulkAction;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ExportBulkAction;
use Filament\Tables\Columns\TextColumn;
use App\Tables\Columns\CommissionMaster;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ColumnGroup;
use App\Tables\Columns\CommissionGeneral;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;
use App\Filament\Exports\CommissionExporter;
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
                TextColumn::make('created_at')
                    ->label('Fecha de calculo')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('code')
                    ->badge()
                    ->icon('heroicon-s-document-text')
                    ->label('Nro. Venta')
                    ->searchable(),
                TextColumn::make('agency.name_corporative')
                    ->label('Agencia')
                    ->badge()
                    ->default(fn($record): string => $record->code_agency == 'TDG-100' ? 'TUDRENCASA' : '-----')
                    ->color('success')
                    ->sortable()
                    ->searchable(),
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
                    TextColumn::make('payment_method')
                        ->label('Metodo de pago')->badge()->color('info')
                        ->searchable(),
                ]),
                
                //ESTRUCTURA DE COMISIONES COMPLETA EN USD
                ColumnGroup::make('COMISIONES MASTER USD - VES')->columns([
                    TextColumn::make('porcent_agency_master')
                        ->default(fn($record): string => $record->porcent_agency_master == 0 || $record->porcent_agency_master == NULL ? 0 : $record->porcent_agency_master)
                        ->label('%')
                        ->badge()
                        ->color('info')
                        ->suffix('%')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('commission_agency_master_usd')
                        ->label('Pago USD')
                        ->width('20%')
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal USD'))
                            ->suffix(' US$')
                            ->numeric()),
                    TextColumn::make('commission_agency_master_ves')
                        ->label('Pago VES')
                        ->width('20%')
                        ->badge()
                        ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal VES'))
                            ->suffix(' VES')
                            ->numeric()),
                ]),

                ColumnGroup::make('COMISIONES GENERAL USD - VES')->columns([
                    TextColumn::make('porcent_agency_general')
                        ->default(fn($record): string => $record->porcent_agency_general == 0 || $record->porcent_agency_general == NULL ? 0 : $record->porcent_agency_general)
                        ->label('%')
                        ->badge()
                        ->color('info')
                        ->suffix('%')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('commission_agency_general_usd')
                        ->label('Pago USD')
                        ->grow()
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal USD'))
                            ->suffix(' US$')
                            ->numeric()),
                    TextColumn::make('commission_agency_general_ves')
                        ->label('Pago VES')
                        ->grow()
                        ->badge()
                        ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal VES'))
                            ->suffix(' VES')
                            ->numeric()),
                ]),

                ColumnGroup::make('COMISIONES AGENTE USD - VES')->columns([
                    TextColumn::make('porcent_agente')
                        ->default(fn($record): string => $record->porcent_agente == 0 || $record->porcent_agente == NULL ? 0 : $record->porcent_agente)
                        ->label('%')
                        ->badge()
                        ->color('info')
                        ->suffix('%')
                        ->numeric()
                        ->sortable(),
                    TextColumn::make('commission_agent_usd')
                        ->label('Pago USD')
                        ->grow()
                        ->badge()
                        ->color('success')
                        ->suffix(' US$')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal USD'))
                            ->suffix(' US$')
                            ->numeric()),
                    TextColumn::make('commission_agent_ves')
                        ->label('Pago VES')
                        ->grow()
                        ->badge()
                        ->money('VES')
                        // ->suffix(' VES')
                        ->numeric()
                        ->sortable()
                        ->summarize(Sum::make()
                            ->label(('Subtotal VES'))
                            ->suffix(' VES')
                            ->numeric()),
                ]),

                ColumnGroup::make('TOTAL COMISIONES USD - VES')->columns([
                    CommissionMaster::make('commision_master')
                        ->label('Total USD')
                        ->alignCenter(),
                    CommissionGeneral::make('commision_general')
                        ->label('Total VES')
                        ->alignCenter(),
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
                    ExportBulkAction::make()->exporter(CommissionExporter::class)->label('Exportar XLS')->color('warning')->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}