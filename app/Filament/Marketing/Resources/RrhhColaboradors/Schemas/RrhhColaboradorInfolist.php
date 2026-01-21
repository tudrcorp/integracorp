<?php

namespace App\Filament\Marketing\Resources\RrhhColaboradors\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class RrhhColaboradorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('fullName')
                    ->placeholder('-'),
                TextEntry::make('departmento_id')
                    ->placeholder('-'),
                TextEntry::make('cargo_id')
                    ->placeholder('-'),
                TextEntry::make('cedula')
                    ->placeholder('-'),
                TextEntry::make('sexo')
                    ->placeholder('-'),
                TextEntry::make('fechaNacimiento')
                    ->placeholder('-'),
                TextEntry::make('fechaIngreso')
                    ->placeholder('-'),
                TextEntry::make('telefono')
                    ->placeholder('-'),
                TextEntry::make('telefonoCorporativo')
                    ->placeholder('-'),
                TextEntry::make('emailCorporativo')
                    ->placeholder('-'),
                TextEntry::make('emailAlternativo')
                    ->placeholder('-'),
                TextEntry::make('emailPersonal')
                    ->placeholder('-'),
                TextEntry::make('direccion')
                    ->placeholder('-'),
                TextEntry::make('nroHijos')
                    ->placeholder('-'),
                TextEntry::make('nroHijoDependiente')
                    ->placeholder('-'),
                TextEntry::make('tallaCamisa')
                    ->placeholder('-'),
                TextEntry::make('banck_id')
                    ->placeholder('-'),
                TextEntry::make('nroCta')
                    ->placeholder('-'),
                TextEntry::make('codigoCta')
                    ->placeholder('-'),
                TextEntry::make('tipoCta')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('created_by')
                    ->placeholder('-'),
                TextEntry::make('updated_by')
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('sueldo')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('user_id')
                    ->numeric()
                    ->placeholder('-'),
            ]);
    }
}
