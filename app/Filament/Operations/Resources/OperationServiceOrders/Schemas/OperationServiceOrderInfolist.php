<?php

namespace App\Filament\Operations\Resources\OperationServiceOrders\Schemas;

use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class OperationServiceOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen de la orden')
                    ->description('Información general y trazabilidad de la orden de servicio.')
                    ->icon(Heroicon::OutlinedClipboardDocumentList)
                    ->schema([
                        Fieldset::make('Datos principales')
                            ->schema([
                                TextEntry::make('order_number')
                                    ->label('Nº de orden')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('service_type')
                                    ->label('Tipo de servicio')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('operation_coordination_service_id')
                                    ->label('ID coordinación')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('telemedicinePriority.name')
                                    ->label('Prioridad')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('operationInventoryUbication.name')
                                    ->label('Ubicación de despacho')
                                    ->placeholder('-'),
                                TextEntry::make('total_items')
                                    ->label('Total ítems')
                                    ->numeric()
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('total_items_unit')
                                    ->label('Total unidades')
                                    ->numeric()
                                    ->badge()
                                    ->placeholder('-'),
                            ])
                            ->columns(4),
                    ])
                    ->columnSpanFull(),

                Section::make('Proveedor y descripción')
                    ->description('Datos del proveedor interno/externo y detalle general del requerimiento.')
                    ->icon(Heroicon::OutlinedBuildingStorefront)
                    ->collapsed(true)
                    ->schema([
                        Fieldset::make('Información comercial')
                            ->schema([
                                TextEntry::make('supplier.name')
                                    ->label('Proveedor TDG')
                                    ->placeholder('-'),
                                TextEntry::make('supplier_external')
                                    ->label('Proveedor externo')
                                    ->placeholder('-'),
                                TextEntry::make('description')
                                    ->label('Descripción')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                                TextEntry::make('observations')
                                    ->label('Observaciones')
                                    ->placeholder('-')
                                    ->columnSpanFull(),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Montos y método de pago')
                    ->description('Resumen financiero de la orden.')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->collapsed(true)
                    ->schema([
                        Fieldset::make('Totales')
                            ->schema([
                                TextEntry::make('currency')
                                    ->label('Moneda')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('payment_method')
                                    ->label('Método de pago')
                                    ->placeholder('-'),
                                TextEntry::make('tasa_bcv')
                                    ->label('Tasa BCV')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('total_amount_usd')
                                    ->label('Total USD')
                                    ->money('USD')
                                    ->placeholder('-'),
                                TextEntry::make('total_amount_ves')
                                    ->label('Total VES')
                                    ->money('VES')
                                    ->placeholder('-'),
                            ])
                            ->columns(3),
                    ])
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->description('Control de creación y última actualización.')
                    ->icon(Heroicon::OutlinedClock)
                    ->collapsed(true)
                    ->schema([
                        Fieldset::make('Trazabilidad')
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('-'),
                                TextEntry::make('updated_by')
                                    ->label('Actualizado por')
                                    ->placeholder('-'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('-'),
                            ])
                            ->columns(2),
                    ])
                    ->columnSpanFull(),

                Section::make('Ítems asociados')
                    ->description('Detalle de medicamentos/servicios asociados a esta orden.')
                    ->icon(Heroicon::OutlinedQueueList)
                    ->schema([
                        RepeatableEntry::make('operationServiceOrderItems')
                            ->label('Detalle de ítems')
                            ->placeholder('La orden no posee ítems asociados.')
                            ->table([
                                TableColumn::make('Ítem'),
                                TableColumn::make('Categoría'),
                                TableColumn::make('Unidad'),
                                TableColumn::make('Cantidad'),
                                TableColumn::make('Indicaciones / dosis'),
                            ])
                            ->schema([
                                TextEntry::make('item_name')
                                    ->label('Ítem')
                                    ->placeholder('-'),
                                TextEntry::make('category')
                                    ->label('Categoría')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('item_unit')
                                    ->label('Unidad')
                                    ->placeholder('-'),
                                TextEntry::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric()
                                    ->placeholder('-'),
                                TextEntry::make('dosage_instruction')
                                    ->label('Indicaciones / dosis')
                                    ->placeholder('-'),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

            ]);
    }
}
