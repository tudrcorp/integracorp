<?php

namespace App\Filament\Operations\Resources\Suppliers\Tables;

use Carbon\Carbon;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Illuminate\Database\Eloquent\Builder;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                ->label('Nombre del Proveedor')
                    ->searchable(),
                TextColumn::make('status_convenio')
                    ->label('Estatus del Convenio')
                    ->searchable(),
                TextColumn::make('tipo_clinica')
                    ->label('Tipo de Clinica')
                    ->searchable(),
                TextColumn::make('tipo_servicio')
                    ->label('Tipo de Servicio')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('clasificacion')
                    ->label('Clasificacion')
                    ->searchable(),
                TextColumn::make('status_sistema')
                    ->label('Estatus del Sistema')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable(),
                TextColumn::make('razon_social')
                    ->label('Razon Social')
                    ->searchable(),
                TextColumn::make('personal_phone')
                    ->label('Teléfono Celular')
                    ->searchable(),
                TextColumn::make('local_phone')
                    ->label('Teléfono Local')
                    ->searchable(),
                TextColumn::make('correo_principal')
                    ->label('Correo Principal')
                    ->searchable(),
                TextColumn::make('afiliacion_proveedor')
                    ->label('Afiliación Proveedor')
                    ->searchable(),
                
                TextColumn::make('ubicacion_principal')
                    ->label('Ubicación Principal')
                    ->searchable(),
                TextColumn::make('convenio_pago')
                    ->label('Convenio de Pago')
                    ->searchable(),
                TextColumn::make('tiempo_credito')
                    ->label('Tiempo de Credito')
                    ->searchable(),
                TextColumn::make('promedio_costo_proveedor')
                    ->label('Promedio Costo Proveedor')
                    ->searchable(),
                IconColumn::make('urgen_care')
                    ->boolean(),
                IconColumn::make('consulta_aps')
                    ->boolean(),
                IconColumn::make('amd')
                    ->label('AMD')    
                    ->boolean(),
                IconColumn::make('laboratorio_centro')
                    ->label('Laboratorio Centro')
                    ->boolean(),
                IconColumn::make('laboratorio_domicilio')
                    ->label('Laboratorio Domicilio')
                    ->boolean(),
                IconColumn::make('rx_centro')
                    ->label('RX Centro')
                    ->boolean(),
                IconColumn::make('rx_domicilio')
                    ->label('RX Domicilio')
                    ->boolean(),
                IconColumn::make('eco_abdominal_centro')
                    ->label('Eco Abdominal Centro')
                    ->boolean(),
                IconColumn::make('eco_abdominal_domicilio')
                    ->label('Eco Abdominal Domicilio')
                    ->boolean(),
                IconColumn::make('electrocardiograma_centro')
                    ->label('Electrocardiograma Centro')
                    ->boolean(),
                IconColumn::make('electrocardiograma_domicilio')
                    ->label('Electrocardiograma Domicilio')
                    ->boolean(),
                IconColumn::make('mamografia')
                    ->label('Mamografia')
                    ->boolean(),
                IconColumn::make('tomografo')
                    ->label('Tomografo')
                    ->boolean(),
                IconColumn::make('resonancia')
                    ->label('Resonancia')
                    ->boolean(),
                IconColumn::make('encologogia')
                    ->label('Encologogia')
                    ->boolean(),
                IconColumn::make('equipos_especiales_oftalmologia')
                    ->label('Equipos Especiales Oftalmologia')
                    ->boolean(),
                IconColumn::make('radioterapia_intraoperatoria')
                    ->label('Radioterapia Intraoperatoria')
                    ->boolean(),
                IconColumn::make('quirofanos')
                    ->label('Quirofanos')
                    ->boolean(),
                IconColumn::make('uci_uten')
                    ->label('UCI Uten')
                    ->boolean(),
                IconColumn::make('neonatal')
                    ->label('Neonatal')
                    ->boolean(),
                IconColumn::make('ambulancias')
                    ->label('Ambulancias')
                    ->boolean(),
                IconColumn::make('odontologia')
                    ->label('Odontologia')
                    ->boolean(),
                IconColumn::make('oftalmologia')
                    ->label('Oftalmologia')
                    ->boolean(),
                IconColumn::make('densitometria_osea')
                    ->label('Densitometria Osea')
                    ->boolean(),
                IconColumn::make('dialisis')
                    ->label('Dialisis')
                    ->boolean(),
                IconColumn::make('otras_unidades_especiales')
                    ->label('Otras Unidades Especiales')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('updated_by')
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
            ])
            ->filtersTriggerAction(
                fn(Action $action) => $action
                    ->button()
                    ->label('Filtros'),
            )
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