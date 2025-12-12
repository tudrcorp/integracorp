<?php

namespace App\Filament\Operations\Resources\Suppliers\Schemas;

use Filament\Schemas\Schema;
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
                TextEntry::make('name')
                    ->label('Nombre del Proveedor')
                    ->placeholder('-'),
                
                // RepeatableEntry::make('supplierContactPrincipals')
                //     ->label('Tabla dinamica de Contactos Principales')
                //     ->table([
                //         TableColumn::make('Departamento'),
                //         TableColumn::make('Cargo'),
                //         TableColumn::make('Nombre y Apellido'),
                //         TableColumn::make('Correo Electrónico'),
                //         TableColumn::make('Teléfono Celular'),
                //         TableColumn::make('Teléfono Local'),
                //     ])
                //     ->schema([
                //         TextEntry::make('departament'),
                //         TextEntry::make('position'),
                //         TextEntry::make('name'),
                //         TextEntry::make('email'),
                //         TextEntry::make('personal_phone'),
                //         TextEntry::make('local_phone'),
                        
                //     ])->columnSpanFull(),

                // RepeatableEntry::make('supplierRedGlobals')
                //     ->label('Tabla dinámica de Información de Sucursales')
                //     ->table([
                //         TableColumn::make('Estado'),
                //         TableColumn::make('Ciudad'),
                //         TableColumn::make('Nombre y Apellido'),
                //         TableColumn::make('Correo Electrónico'),
                //         TableColumn::make('Teléfono Celular'),
                //         TableColumn::make('Teléfono Local'),
                //         TableColumn::make('Direccion de Ubicacion'),
                //     ])
                //     ->schema([
                //         TextEntry::make('state.definition'),
                //         TextEntry::make('city.definition'),
                //         TextEntry::make('name'),
                //         TextEntry::make('email'),
                //         TextEntry::make('personal_phone'),
                //         TextEntry::make('local_phone'),
                //         TextEntry::make('address'),

                //     ])->columnSpanFull(),

                // RepeatableEntry::make('supplierObservacions')
                //     ->label('Bitacora de Notas y/o Observaciones')
                //     ->table([
                //         TableColumn::make('Notas y/o Observacion'),
                //         TableColumn::make('Responsable de la Nota'),
                //         TableColumn::make('Fecha de la Nota'),
                //     ])
                //     ->schema([
                //         TextEntry::make('observation'),
                //         TextEntry::make('created_by'),
                //         TextEntry::make('created_at'),
                //     ])->columnSpanFull(),
            
            ]);
    }
}