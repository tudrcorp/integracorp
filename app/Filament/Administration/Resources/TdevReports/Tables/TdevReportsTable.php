<?php

namespace App\Filament\Administration\Resources\TdevReports\Tables;

use App\Models\TdevReport;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TdevReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Movimientos TDEV')
            ->description('Vista resumida por defecto. Use el selector de columnas para ver importes, comisiones y el resto de campos del CSV. La búsqueda prioriza voucher, pasajero, documento y agencia.')
            ->defaultSort('id', 'desc')
            ->defaultPaginationPageOption(25)
            ->paginated([10, 25, 50, 100])
            ->striped()
            ->deferLoading()
            ->emptyStateHeading('Aún no hay filas importadas')
            ->emptyStateDescription('Suba un archivo CSV con el botón «Importar reporte CSV» en la parte superior de la página.')
            ->emptyStateIcon(Heroicon::ArrowUpTray)
            ->columns([
                TextColumn::make('fecha')
                    ->label('Fecha')
                    ->icon('heroicon-o-calendar-days')
                    ->formatStateUsing(function (?string $state): string {
                        if (blank($state)) {
                            return '—';
                        }
                        try {
                            return Carbon::parse($state)->format('d/m/Y');
                        } catch (\Throwable) {
                            return $state;
                        }
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('vaucher')
                    ->label('Voucher')
                    ->icon('heroicon-o-ticket')
                    ->weight(FontWeight::SemiBold)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable()
                    ->copyMessage('Voucher copiado')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('pasajero')
                    ->label('Pasajero')
                    ->icon('heroicon-o-user')
                    ->limit(40)
                    ->tooltip(fn (TdevReport $record): ?string => $record->pasajero)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('agencia')
                    ->label('Agencia')
                    ->icon('heroicon-o-building-office-2')
                    ->limit(28)
                    ->tooltip(fn (TdevReport $record): ?string => $record->agencia)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('agente')
                    ->label('Agente')
                    ->icon('heroicon-o-user-circle')
                    ->limit(24)
                    ->toggleable(),
                TextColumn::make('origen_del_viaje')
                    ->label('Origen')
                    ->icon('heroicon-o-map-pin')
                    ->limit(22)
                    ->toggleable(),
                TextColumn::make('destino')
                    ->label('Destino')
                    ->icon('heroicon-o-flag')
                    ->limit(22)
                    ->toggleable(),
                TextColumn::make('total_precio_venta')
                    ->label('Total venta')
                    ->icon('heroicon-o-banknotes')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('estatus_del_vaucher')
                    ->label('Estatus voucher')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'ACTIVO', 'VIGENTE', 'PAGADO' => 'success',
                        'ANULADO', 'CANCELADO' => 'danger',
                        'PENDIENTE' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status_report')
                    ->label('Estatus reporte')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'OK', 'PROCESADO', 'VALIDO' => 'success',
                        'ERROR', 'RECHAZADO' => 'danger',
                        'PENDIENTE' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('subagente')
                    ->label('Subagente')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('salida')
                    ->label('Salida')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('regreso')
                    ->label('Regreso')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_anulacion')
                    ->label('Fecha anulación')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nacionalidad')
                    ->label('Nacionalidad')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tipo_documento')
                    ->label('Tipo documento')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nro_documento')
                    ->label('Nº documento')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('categoria_del_plan')
                    ->label('Categoría del plan')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('descripcion_del_plan')
                    ->label('Descripción del plan')
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('nro_dias_de_servicio')
                    ->label('Días de servicio')
                    ->numeric(0)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('edad')
                    ->label('Edad')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referencia')
                    ->label('Referencia')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('plan_familiar')
                    ->label('Plan familiar')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('descuento')
                    ->label('Descuento')
                    ->numeric(2)
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('impuesto')
                    ->label('Impuesto')
                    ->numeric(2)
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('precio_upgrade')
                    ->label('Precio upgrade')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('precio_de_venta')
                    ->label('Precio de venta')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_pago_vaucher')
                    ->label('Fecha pago voucher')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('forma_de_pago')
                    ->label('Forma de pago')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('entidad_bancaria_receptora')
                    ->label('Entidad bancaria')
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referencia_bancaria')
                    ->label('Ref. bancaria')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('tasa_pago')
                    ->label('Tasa pago')
                    ->numeric(4)
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('monto_abonado_en_cuenta')
                    ->label('Monto abonado')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estatus_pago')
                    ->label('Estatus pago')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'PAGADO', 'ABONADO', 'CONFIRMADO' => 'success',
                        'PENDIENTE' => 'warning',
                        'RECHAZADO', 'ANULADO' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('dias_emision')
                    ->label('Días emisión')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('porcen_comision')
                    ->label('% comisión')
                    ->numeric(2)
                    ->suffix(' %')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('comision_agencia')
                    ->label('Comisión agencia')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('comision_agente')
                    ->label('Comisión agente')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('comision_subagente')
                    ->label('Comisión subagente')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('monto_comision')
                    ->label('Monto comisión')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('estatus_comision')
                    ->label('Estatus comisión')
                    ->badge()
                    ->color(fn (?string $state): string => match (mb_strtoupper((string) $state)) {
                        'PAGADA', 'LIQUIDADA' => 'success',
                        'PENDIENTE' => 'warning',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fecha_pago_comision')
                    ->label('Fecha pago comisión')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('referencia_bancaria_comision')
                    ->label('Ref. bancaria comisión')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('relacion_comision')
                    ->label('Relación comisión')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('observaciones')
                    ->label('Observaciones')
                    ->limit(50)
                    ->tooltip(fn (TdevReport $record): ?string => $record->observaciones)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('neto_del_servicio')
                    ->label('Neto del servicio')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('utilidad_tdev')
                    ->label('Utilidad TDEV')
                    ->money('USD')
                    ->alignment(Alignment::End)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Importado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status_report')
                    ->label('Estatus del reporte')
                    ->options(fn (): array => self::distinctOptions('status_report'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estatus_del_vaucher')
                    ->label('Estatus del voucher')
                    ->options(fn (): array => self::distinctOptions('estatus_del_vaucher'))
                    ->searchable()
                    ->preload(),
                SelectFilter::make('estatus_pago')
                    ->label('Estatus de pago')
                    ->options(fn (): array => self::distinctOptions('estatus_pago'))
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver'),
                EditAction::make()
                    ->label('Editar'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function distinctOptions(string $column): array
    {
        if (! in_array($column, (new TdevReport)->getFillable(), true)) {
            return [];
        }

        return TdevReport::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }
}
