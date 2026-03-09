<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Tables;

use App\Models\OperationStatusService;
use App\Models\OperationTypeNegotiation;
use App\Models\OperationTypeService;
use App\Models\Supplier;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class OperationCoordinationServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table

            ->heading('Listado de Coordinacion de Servicios')
            ->description('Lista de servicios coordinados en el sistema para la telemedicina, RETAIL y otros servicios')
            ->defaultSort('date_solicitud', 'desc')
            ->columns([
                TextColumn::make('date_solicitud')
                    ->label('Fecha de Solicitud')
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('date_service')
                    ->label('Fecha de Servicio')
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('businessLine.definition')
                    ->label('Linea de Servicio')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('businessUnit.definition')
                    ->label('Unidad de Negocio')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('reference_number')
                    ->label('Número de Referencia')
                    ->searchable(),
                SelectColumn::make('status')
                    ->label('Estatus')
                    ->options(OperationStatusService::all()->pluck('description', 'description'))
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                TextColumn::make('holder')
                    ->label('Titular')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('ci_holder')
                    ->label('Cédula del Titular')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('ci_patient')
                    ->label('Cédula del Paciente')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('birth_date_patient')
                    ->label('Fecha de Nacimiento del Paciente')
                    ->icon('heroicon-m-calendar-days')
                    ->badge()
                    ->color('warning')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('relationship_patient')
                    ->label('Relación del Paciente')
                    ->searchable(),
                TextColumn::make('age_patient')
                    ->label('Edad del Paciente')
                    ->searchable(),
                TextColumn::make('contractor')
                    ->label('Contratante')
                    ->searchable(),
                TextColumn::make('state_id')
                    ->label('Estado')
                    ->searchable(),
                TextColumn::make('city_id')
                    ->label('Ciudad')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('phone_holder')
                    ->label('Teléfono del Titular')
                    ->searchable(),
                TextColumn::make('symptoms_diagnosis')
                    ->label('Síntomas y Diagnóstico')
                    ->searchable(),
                TextColumn::make('servicie')
                    ->label('Servicio')
                    ->searchable(),
                TextColumn::make('specific_service')
                    ->label('Servicio Específico')
                    ->searchable(),
                SelectColumn::make('type_service')
                    ->label('Tipo de Servicio')
                    ->options(OperationTypeService::all()->pluck('description', 'description'))
                    ->searchableOptions()
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                SelectColumn::make('supplier_service')
                    ->label('Proveedor de Servicio')
                    ->options(Supplier::all()->pluck('name', 'name'))
                    ->searchableOptions()
                    ->getOptionsSearchResultsUsing(fn (string $search): array => Supplier::query()
                        // prueba 502091882
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('rif', 'like', "%{$search}%")
                        ->limit(50)
                        ->pluck('name', 'name')
                        ->all()
                    )
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                TextColumn::make('farmadoc')
                    ->label('Farmadoc')
                    ->searchable(),
                SelectColumn::make('type_negotiation')
                    ->label('Tipo de Negociación')
                    ->options(OperationTypeNegotiation::all()->pluck('description', 'description'))
                    ->searchableOptions()
                    ->searchable(),
                TextInputColumn::make('status_negotiation')
                    ->label('Estatus de Negociación')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->status_negotiation = strtoupper($state);
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('neto')
                    ->label('Precio Neto')
                    ->type('number')
                    ->inputMode('decimal')
                    ->prefix('US$')
                    ->sortable(),
                TextInputColumn::make('porcen_tdec')
                    ->type('number')
                    ->inputMode('decimal')
                    ->prefix('%')
                    ->label('% TDEC')
                    ->afterStateUpdated(function ($record, $state) {
                        $record->quote_price = ($record->neto * $state / 100) + $record->neto;
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->sortable(),
                TextColumn::make('quote_price')
                    ->money()
                    ->badge()
                    ->color(fn ($record) => $record->quote_price > 0 ? 'success' : 'gray')
                    ->icon('heroicon-s-currency-dollar')
                    ->label('Precio de Cotización')
                    ->sortable(),
                SelectColumn::make('negotiation')
                    ->label('Negociación')
                    ->options(['SI' => 'SI', 'NO' => 'NO'])
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                TextInputColumn::make('porcen_discount')
                    ->type('number')
                    ->inputMode('decimal')
                    ->prefix('%')
                    ->label('Porcentaje de Descuento')
                    ->afterStateUpdated(function ($record, $state) {
                        $record->price_discount = ($record->quote_price * $state / 100);
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->sortable(),
                TextInputColumn::make('price_discount')
                    ->type('number')
                    ->inputMode('decimal')
                    ->prefix('US$')
                    ->label('Precio de Descuento')
                    ->sortable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('quote_number')
                    ->label('Número de Cotización')
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                TextInputColumn::make('approved_number')
                    ->label('Número de Aprobación')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('service_order_number')
                    ->label('Número Orden de Servicio')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('bill_number')
                    ->label('Número de Factura')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('bill_price')
                    ->type('number')
                    ->inputMode('decimal')
                    ->prefix('US$')
                    ->label('Precio de Factura')
                    ->sortable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                TextInputColumn::make('bill_date')
                    ->label('Fecha de Factura')
                    ->searchable()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    }),
                SelectColumn::make('incidence')
                    ->label('Incidencia')
                    ->options(['SI' => 'SI', 'NO' => 'NO'])
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                SelectColumn::make('negotiation_description')
                    ->label('Descripción de Negociación')
                    ->options(['SI' => 'SI', 'NO' => 'NO'])
                    ->searchableOptions()
                    ->afterStateUpdated(function ($record, $state) {
                        $record->updated_by = Auth::user()->name;
                        $record->save();
                    })
                    ->searchable(),
                TextColumn::make('qc_description')
                    ->label('Descripción de QC')
                    ->searchable(),
                TextColumn::make('observations')
                    ->label('Observaciones')
                    ->searchable(),
                TextColumn::make('created_by')
                    ->label('Creado Por')
                    ->searchable(),
                TextColumn::make('updated_by')
                    ->label('Actualizado Por')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Creado el')
                    ->description(fn ($record) => $record->created_at->diffForHumans())
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->description(fn ($record) => $record->updated_at->diffForHumans())
                    ->icon('heroicon-m-calendar-days')
                    ->datetime('d/m/Y')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                // ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
