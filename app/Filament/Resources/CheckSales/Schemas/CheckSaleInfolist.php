<?php

namespace App\Filament\Resources\CheckSales\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CheckSaleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fecha'),
                TextEntry::make('agente'),
                TextEntry::make('contacto'),
                TextEntry::make('rif'),
                TextEntry::make('telefono'),
                TextEntry::make('email'),
                TextEntry::make('producto'),
                TextEntry::make('servicio'),
                TextEntry::make('cobertura'),
                TextEntry::make('poblacion'),
                TextEntry::make('enero'),
                TextEntry::make('febrero'),
                TextEntry::make('marzo'),
                TextEntry::make('abril'),
                TextEntry::make('mayo'),
                TextEntry::make('junio'),
                TextEntry::make('julio'),
                TextEntry::make('agosto'),
                TextEntry::make('septiembre'),
                TextEntry::make('octubre'),
                TextEntry::make('noviembre'),
                TextEntry::make('diciembre'),
                TextEntry::make('monto_pagado'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
                TextEntry::make('población')
                    ->numeric(),
            ]);
    }
}
