<?php

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use App\Models\Supplier;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Forms\Components\Repeater\TableColumn;

class SupplierInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                Section::make('Proveedor')
                    ->icon('heroicon-o-identification')
                    ->description('Esta es la información general del Proveedor')
                    ->schema([
                        Fieldset::make('Deltalles del Proveedor')
                            ->schema([
                                TextEntry::make('name')
                                    ->label('Nombre del Proveedor:')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('rif')
                                    ->label('RIF:')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('razon_social')
                                    ->label('Razon Social:')
                                    ->placeholder('-'),
                                TextEntry::make('status_convenio')
                                    ->label('Estatus del Convenio:')
                                    ->placeholder('-'),
                                TextEntry::make('status_sistema')
                                    ->label('Estatus del Sistema:')
                                    ->placeholder('-'),
                                TextEntry::make('SupplierClasificacion.description')
                                    ->label('Clasificacion del Proveedor:')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('tipo_clinica')
                                    ->label('Tipo de Clinica:')
                                    ->badge()
                                    ->placeholder('-'),
                                TextEntry::make('type_service')
                                    ->label('Tipo de Servicio:')
                                    ->icon('heroicon-c-check')
                                    ->badge()
                                    ->color('success')
                                    ->listWithLineBreaks(),
                                TextEntry::make('state.definition')
                                    ->label('Estado:')
                                    ->placeholder('-'),
                                TextEntry::make('city.definition')
                                    ->label('Ciudad:')
                                    ->placeholder('-'),
                                TextEntry::make('tipo_servicio')
                                    ->label('Servicio:')
                                    ->placeholder('-'),
                                TextEntry::make('state_services')
                                    ->label('Presta Servicios en:')
                                    ->icon('heroicon-c-check')
                                    ->badge()
                                    ->color('success'),
                                // ->listWithLineBreaks(),
                                TextEntry::make('personal_phone')
                                    ->label('Teléfono Celular:')
                                    ->placeholder('-'),
                                TextEntry::make('local_phone')
                                    ->label('Telefono Local:')
                                    ->placeholder('-'),
                                TextEntry::make('correo_principal')
                                    ->label('Correo Electrónico:')
                                    ->placeholder('-'),
                                TextEntry::make('afiliacion_proveedor')
                                    ->label('Fecha de Afiliacion del Proveedor:')
                                    ->badge()
                                    ->icon('heroicon-o-calendar-date-range')
                                    ->placeholder('-'),
                                TextEntry::make('ubicacion_principal')
                                    ->label('Dirección Principal:')
                                    ->placeholder('-'),
                                TextEntry::make('convenio_pago')
                                    ->label('Convenio de Pago:')
                                    ->placeholder('-'),
                                TextEntry::make('tiempo_credito')
                                    ->label('Tiempo de Credito:')
                                    ->placeholder('-'),
                                TextEntry::make('promedio_costo_proveedor')
                                    ->label('Promedio de Costo del Proveedor:')
                                    ->placeholder('-'),

                            ])->columnSpanFull()->columns(5),

                        Fieldset::make('Características de la Infraestructura')
                            ->schema([

                                IconEntry::make('densitometria_osea')
                                    ->boolean()
                                    ->label('Densitómetro')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_densitometria_osea),
                                IconEntry::make('dialisis')
                                    ->boolean()
                                    ->label('Equipo de Dialisis')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_dialisis),
                                IconEntry::make('electrocardiograma_centro')
                                    ->boolean()
                                    ->label('Electrocardiógrafo')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_electrocardiograma_centro),
                                IconEntry::make('equipos_especiales_oftalmologia')
                                    ->boolean()
                                    ->label('Equipos Especiales de Oftalmología')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_equipos_especiales_oftalmologia),
                                IconEntry::make('mamografia')
                                    ->boolean()
                                    ->label('Mamógrafo')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_mamografia),
                                IconEntry::make('quirofanos')
                                    ->boolean()
                                    ->label('Quirofanos')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_quirofanos),
                                IconEntry::make('radioterapia_intraoperatoria')
                                    ->boolean()
                                    ->label('Radioterapia Intraoperatoria')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_radioterapia_intraoperatoria),


                                IconEntry::make('resonancia')
                                    ->boolean()
                                    ->label('Resonador')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_resonancia),
                                IconEntry::make('tomografo')
                                    ->boolean()
                                    ->label('Tomografo')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_tomografo),
                                IconEntry::make('uci_pediatrica')
                                    ->boolean()
                                    ->label('UCI Pediatrica(Unidad de Cuidados Intensivos)')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_uci_pediatrica),
                                IconEntry::make('uci_adulto')
                                    ->boolean()
                                    ->label('UCI Adulto(Unidad de Cuidados Intensivos)')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_uci_adulto),
                                IconEntry::make('estacionamiento_propio')
                                    ->boolean()
                                    ->label('Estacionamiento Propio?')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_estacionamiento_propio),
                                IconEntry::make('ascensor')
                                    ->boolean()
                                    ->label('Ascensor Operativo')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_ascensor),
                                IconEntry::make('robotica')
                                    ->boolean()
                                    ->label('Equipo de  Cirugía Robótica')
                                    ->belowContent(fn(Supplier $record) => 'Descripción: ' . $record->descripcion_robotica),

                            ])->columnSpanFull()->columns(4),

                        RepeatableEntry::make('supplierContactPrincipals')
                            ->placeholder('No posee contactos principales')
                            ->label('Contactos Principales')
                            ->table([
                                TableColumn::make('Departamento'),
                                TableColumn::make('Cargo'),
                                TableColumn::make('Nombre y Apellido'),
                                TableColumn::make('Correo Electrónico'),
                                TableColumn::make('Teléfono Celular'),
                                TableColumn::make('Teléfono Local'),
                                TableColumn::make('Extension(es)'),
                            ])
                            ->schema([
                                TextEntry::make('departament'),
                                TextEntry::make('position'),
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('personal_phone'),
                                TextEntry::make('local_phone'),
                                TextEntry::make('extensions'), 

                            ])->columnSpanFull(),

                        RepeatableEntry::make('supplierRedGlobals')
                            ->placeholder('No posee Sucursales')
                            ->label('Información de Sucursales')
                            ->table([
                                TableColumn::make('Estado'),
                                TableColumn::make('Ciudad'),
                                TableColumn::make('Nombre y Apellido'),
                                TableColumn::make('Correo Electrónico'),
                                TableColumn::make('Teléfono Celular'),
                                TableColumn::make('Teléfono Local'),
                                TableColumn::make('Direccion de Ubicacion'),
                            ])
                            ->schema([
                                TextEntry::make('state.definition'),
                                TextEntry::make('city.definition'),
                                TextEntry::make('name'),
                                TextEntry::make('email'),
                                TextEntry::make('personal_phone'),
                                TextEntry::make('local_phone'),
                                TextEntry::make('address'),

                            ])->columnSpanFull(),

                        RepeatableEntry::make('SupplierZonaCoberturas')
                            ->placeholder('No posee Zonas de Cobertura!')
                            ->label('Zonas de Cobertura')
                            ->table([
                                TableColumn::make('Clasificación del Proveedor'),
                                TableColumn::make('Tipo de Servicio'),
                                TableColumn::make('Estado'),
                                TableColumn::make('Ciudad'),
                            ])
                            ->schema([
                                TextEntry::make('supplierClasificacion.description'),
                                TextEntry::make('type_service'),
                                TextEntry::make('state.definition'),
                                TextEntry::make('city.definition'),
                            ])->columnSpanFull(),

                        RepeatableEntry::make('supplierObservacions')
                            ->placeholder('No posee Notas y/o Observaciones')
                            ->label('Bitacora de Notas y/o Observaciones')
                            ->table([
                                TableColumn::make('Notas y/o Observacion'),
                                TableColumn::make('Responsable de la Nota'),
                                TableColumn::make('Fecha de la Nota'),
                            ])
                            ->schema([
                                TextEntry::make('observation'),
                                TextEntry::make('created_by'),
                                TextEntry::make('created_at'),
                            ])->columnSpanFull(),
                        
                    ])->columnSpanFull(),
            
            ]);
    }
}