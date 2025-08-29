<?php

namespace App\Filament\Resources\CheckAgentAgencies\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class CheckAgentAgencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('codificacion_agente'),
                TextEntry::make('codigo_agente'),
                TextEntry::make('nombre_agencia_agente'),
                TextEntry::make('nombre_representante'),
                TextEntry::make('nro_identificacion'),
                TextEntry::make('fecha_nacimiento'),
                TextEntry::make('fecha_ingreso'),
                TextEntry::make('estatus'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('telefono'),
                TextEntry::make('usuario_instagram'),
                TextEntry::make('pais'),
                TextEntry::make('estado'),
                TextEntry::make('ciudad'),
                TextEntry::make('tdec'),
                TextEntry::make('tdev'),
                TextEntry::make('tipo_agente'),
                TextEntry::make('agente_supervisor'),
                TextEntry::make('agencia_master'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
