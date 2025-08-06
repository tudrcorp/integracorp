<?php

namespace App\Filament\Resources\CheckAffiliations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CheckAffiliationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nro_afiliado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('fecha_emision')
                    ->searchable(),
                TextColumn::make('codigo_tdec')
                    ->searchable(),
                TextColumn::make('tipo_plan')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'Especial',
                        'warning' => 'Ideal',
                        'gray' => '',
                    ]),
                TextColumn::make('proveedor')
                    ->searchable(),
                TextColumn::make('nro_vaucher')
                    ->searchable(),
                TextColumn::make('cobertura')
                    ->searchable(),
                TextColumn::make('tomador')
                    ->searchable(),
                TextColumn::make('tipo_doc')
                    ->searchable(),
                TextColumn::make('nro_doc')
                    ->searchable(),
                TextColumn::make('afiliado')
                    ->searchable(),
                TextColumn::make('tipo_doc_dos')
                    ->searchable(),
                TextColumn::make('nro_doc_tres')
                    ->searchable(),
                TextColumn::make('sexo')
                    ->searchable(),
                TextColumn::make('fecha_nacimiento')
                    ->searchable(),
                TextColumn::make('edad')
                    ->searchable(),
                TextColumn::make('parentesco')
                    ->searchable(),
                TextColumn::make('telefono')
                    ->searchable(),
                TextColumn::make('correo')
                    ->searchable(),
                TextColumn::make('estado')
                    ->searchable(),
                TextColumn::make('ciudad')
                    ->searchable(),
                TextColumn::make('direccion')
                    ->searchable(),
                TextColumn::make('vigencia_desde')
                    ->searchable(),
                TextColumn::make('vigencia_hasta')
                    ->searchable(),
                TextColumn::make('agencia')
                    ->searchable(),
                TextColumn::make('agente')
                    ->searchable(),
                TextColumn::make('plan')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'INDIVIDUAL',
                        'warning' => 'CORPORATIVO',
                        'gray' => '',
                    ]),
                TextColumn::make('frecuencia_pago')
                    ->searchable(),
                TextColumn::make('forma_pago')
                    ->searchable(),
                TextColumn::make('monto_plan')
                    ->searchable(),
                TextColumn::make('monto_recibido')
                    ->searchable(),
                TextColumn::make('diferencia')
                    ->searchable(),
                TextColumn::make('estatus_pago')
                    ->searchable(),
                TextColumn::make('moneda')
                    ->searchable(),
                TextColumn::make('referencia')
                    ->searchable(),
                TextColumn::make('fecha_pago')
                    ->searchable(),
                TextColumn::make('pagado_desde')
                    ->searchable(),
                TextColumn::make('pagado_hasta')
                    ->searchable(),
                TextColumn::make('estatus_renovacion')
                    ->searchable(),
                TextColumn::make('estatus_afiliado')
                    ->searchable()
                    ->badge()
                    ->colors([
                        'success' => 'ACTIVO',
                        'danger' => 'INACTIVO',
                    ]),
                TextColumn::make('dias_para_vencer')
                    ->searchable(),
                TextColumn::make('estado_del_plan')
                    ->searchable(),
                TextColumn::make('pagado_ils_desde')
                    ->searchable(),
                TextColumn::make('pagado_ils_hasta')
                    ->searchable(),
                TextColumn::make('dia_vencimiento_ils')
                    ->searchable(),
                TextColumn::make('estado_plan_ils')
                    ->searchable(),
                TextColumn::make('fecha_egreso')
                    ->searchable(),
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