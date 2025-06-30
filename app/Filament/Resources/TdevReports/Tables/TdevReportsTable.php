<?php

namespace App\Filament\Resources\TdevReports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TdevReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fecha')
                    ->searchable(),
                TextColumn::make('vaucher')
                    ->searchable(),
                TextColumn::make('agente')
                    ->searchable(),
                TextColumn::make('subagente')
                    ->searchable(),
                TextColumn::make('salida')
                    ->searchable(),
                TextColumn::make('regreso')
                    ->searchable(),
                TextColumn::make('fecha_anulacion')
                    ->searchable(),
                TextColumn::make('pasajero')
                    ->searchable(),
                TextColumn::make('nacionalidad')
                    ->searchable(),
                TextColumn::make('tipo_documento')
                    ->searchable(),
                TextColumn::make('nro_documento')
                    ->searchable(),
                TextColumn::make('categoria_del_plan')
                    ->searchable(),
                TextColumn::make('descripcion_del_plan')
                    ->searchable(),
                TextColumn::make('origen_del_viaje')
                    ->searchable(),
                TextColumn::make('nro_dias_de_servicio')
                    ->searchable(),
                TextColumn::make('edad')
                    ->searchable(),
                TextColumn::make('estatus_del_vaucher')
                    ->searchable(),
                TextColumn::make('referencia')
                    ->searchable(),
                TextColumn::make('plan_familiar')
                    ->searchable(),
                TextColumn::make('descuento')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('impuesto')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('precio_upgrade')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('precio_de_venta')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('total_precio_venta')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fecha_pago_vaucher')
                    ->searchable(),
                TextColumn::make('forma_de_pago')
                    ->searchable(),
                TextColumn::make('entidad_bancaria_receptora')
                    ->searchable(),
                TextColumn::make('referencia_bancaria')
                    ->searchable(),
                TextColumn::make('tasa_pago')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monto_abonado_en_cuenta')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estatus_pago')
                    ->searchable(),
                TextColumn::make('dias_emision')
                    ->searchable(),
                TextColumn::make('porcen_comision')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comision_agencia')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comision_agente')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('comision_subagente')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monto_comision')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('estatus_comision')
                    ->searchable(),
                TextColumn::make('fecha_pago_comision')
                    ->searchable(),
                TextColumn::make('referencia_bancaria_comision')
                    ->searchable(),
                TextColumn::make('relacion_comision')
                    ->searchable(),
                TextColumn::make('observaciones')
                    ->searchable(),
                TextColumn::make('neto_del_servicio')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('utilidad_tdev')
                    ->numeric()
                    ->sortable(),
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
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
