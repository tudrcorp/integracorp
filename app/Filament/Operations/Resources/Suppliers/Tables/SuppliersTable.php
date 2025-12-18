<?php

namespace App\Filament\Operations\Resources\Suppliers\Tables;

use Carbon\Carbon;
use App\Models\City;
use App\Models\State;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Filters\Filter;
use App\Models\SupplierClasificacion;
use Filament\Actions\BulkActionGroup;
use App\Models\SupplierEstatusSistema;
use App\Models\SupplierStatusConvenio;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Schemas\Components\Utilities\Get;

class SuppliersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Proveedores')
            ->description('Tabla de Proveedores')
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre del Proveedor')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->searchable(),
                TextColumn::make('razon_social')
                    ->label('Razon Social')
                    ->searchable(),
                TextColumn::make('status_convenio')
                    ->label('Estatus del Convenio')
                    ->searchable(),
                TextColumn::make('status_sistema')
                    ->label('Estatus del Sistema')
                    ->searchable(),
                TextColumn::make('supplierClasificacion.description')
                    ->label('Clasificacion')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('tipo_clinica')
                    ->label('Tipo de Clinica')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->wrap()
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city.definition')
                    ->label('Ciudad')
                    ->searchable(),
                
                TextColumn::make('tipo_servicio')
                    ->label('Tipo de Servicio')
                    ->badge()
                    ->color('success')
                    ->searchable(),
                TextColumn::make('state_services')
                    ->label('Prestan Servicios en:')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->wrap()
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


                IconColumn::make('densitometria_osea')
                    ->boolean()
                    ->label('Densitómetro'),
                IconColumn::make('dialisis')
                    ->boolean()
                    ->label('Equipo de Dialisis'),
                IconColumn::make('electrocardiograma_centro')
                    ->boolean()
                    ->label('Electrocardiógrafo'),
                IconColumn::make('equipos_especiales_oftalmologia')
                    ->boolean(),
                IconColumn::make('mamografia')
                    ->boolean()
                    ->label('Mamógrafo'),
                IconColumn::make('quirofanos')
                    ->boolean(),
                IconColumn::make('radioterapia_intraoperatoria')
                    ->boolean(),
                IconColumn::make('resonancia')
                    ->boolean()
                    ->label('Resonador'),
                IconColumn::make('tomografo')
                    ->boolean()
                    ->label('Tomógrafo'),
                IconColumn::make('uci_pediatrica')
                    ->boolean()
                    ->label('UCI Pediatrica(Unidad de Cuidados Intensivos)'),
                IconColumn::make('uci_adulto')
                    ->boolean()
                    ->label('UCI Adulto(Unidad de Cuidados Intensivos)'),
                IconColumn::make('estacionamiento_propio')
                    ->boolean(),
                IconColumn::make('ascensor')
                    ->boolean()
                    ->label('Ascensor Operativo'),
                IconColumn::make('robotica')
                    ->boolean()
                    ->label('Equipo de  Cirugía Robótica'),
                    
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
                SelectFilter::make('state_id')
                    ->label('Estado')
                    ->options(State::all()->pluck('definition', 'id')),
                SelectFilter::make('city_id')
                    ->label('Ciudad')
                    ->options(City::all()->pluck('definition', 'id')),
                SelectFilter::make('clasificacion')
                    ->label('Tipo de Servicio')
                    ->options(SupplierClasificacion::all()->pluck('description', 'id')),
                // Filter::make('created_at')
                //     ->form([
                //         DatePicker::make('desde'),
                //         DatePicker::make('hasta'),
                //     ])
                //     ->query(function (Builder $query, array $data): Builder {
                //         return $query
                //             ->when(
                //                 $data['desde'] ?? null,
                //                 fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                //             )
                //             ->when(
                //                 $data['hasta'] ?? null,
                //                 fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                //             );
                //     })
                //     ->indicateUsing(function (array $data): array {
                //         $indicators = [];
                //         if ($data['desde'] ?? null) {
                //             $indicators['desde'] = 'Venta desde ' . Carbon::parse($data['desde'])->toFormattedDateString();
                //         }
                //         if ($data['hasta'] ?? null) {
                //             $indicators['hasta'] = 'Venta hasta ' . Carbon::parse($data['hasta'])->toFormattedDateString();
                //         }

                //         return $indicators;
                //     }),
                

                    

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