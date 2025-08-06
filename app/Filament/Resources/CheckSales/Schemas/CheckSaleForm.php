<?php

namespace App\Filament\Resources\CheckSales\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CheckSaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('fecha'),
                Textarea::make('agencia')
                    ->columnSpanFull(),
                TextInput::make('agente'),
                Textarea::make('nro_factura')
                    ->columnSpanFull(),
                Textarea::make('codgio_afiliado')
                    ->columnSpanFull(),
                Textarea::make('cliente_afiliado')
                    ->columnSpanFull(),
                TextInput::make('contacto'),
                TextInput::make('rif'),
                TextInput::make('telefono')
                    ->tel(),
                TextInput::make('email')
                    ->email(),
                TextInput::make('producto'),
                TextInput::make('servicio'),
                TextInput::make('cobertura'),
                TextInput::make('poblacion'),
                TextInput::make('enero'),
                TextInput::make('febrero'),
                TextInput::make('marzo'),
                TextInput::make('abril'),
                TextInput::make('mayo'),
                TextInput::make('junio'),
                TextInput::make('julio'),
                TextInput::make('agosto'),
                TextInput::make('septiembre'),
                TextInput::make('octubre'),
                TextInput::make('noviembre'),
                TextInput::make('diciembre'),
                TextInput::make('monto_pagado'),
                Textarea::make('observaciones')
                    ->columnSpanFull(),
                TextInput::make('población')
                    ->numeric(),
            ]);
    }
}
