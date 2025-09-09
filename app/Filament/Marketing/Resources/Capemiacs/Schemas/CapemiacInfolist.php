<?php

namespace App\Filament\Marketing\Resources\Capemiacs\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CapemiacInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('cliente'),
                TextEntry::make('segmento'),
                TextEntry::make('rif'),
                TextEntry::make('telefonoUno'),
                TextEntry::make('telefonoDos'),
                TextEntry::make('telefonoTres'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('fecha_registro'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
