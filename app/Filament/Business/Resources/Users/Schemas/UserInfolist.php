<?php

namespace App\Filament\Business\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Filament\Schemas\Components\Fieldset;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;

class UserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Fieldset::make('Informacion del Usuario')
                    ->schema([
                        TextEntry::make('name')
                        ->label('Nombre y Apellido del usuario'),
                        TextEntry::make('phone')
                            ->label('Numero de Telefono'),
                        TextEntry::make('birth_date')
                            ->label('Fecha de Nacimiento'),
                        TextEntry::make('email')
                            ->label('Correo Electrónico'),
                        TextEntry::make('created_at')
                            ->dateTime()
                            ->placeholder('-'),
                        TextEntry::make('departament')
                            ->label('Departamento'),
                    ])->columnSpanFull()->columns(4),
            Fieldset::make('Rol del Usuario')
                ->schema([
                    IconEntry::make('is_admin')
                        ->boolean()
                        ->label('Administrador'),
                    IconEntry::make('is_agent')
                        ->boolean()
                        ->label('Agente'),
                    IconEntry::make('is_subagent')
                        ->boolean()
                        ->label('Subagente'),
                    IconEntry::make('is_agency')
                        ->boolean()
                        ->label('Agencia'),
                    IconEntry::make('is_doctor')
                        ->boolean()
                        ->label('Doctor'),
                    IconEntry::make('is_designer')
                        ->boolean()
                        ->label('Diseñador y Marketing'),
                    IconEntry::make('is_accountManagers')
                        ->boolean()
                        ->label('Administrador de Cuentas'),
                    IconEntry::make('is_superAdmin')
                        ->boolean()
                        ->label('Super Administrador'),
                    IconEntry::make('is_business_admin')
                        ->boolean()
                        ->label('Administrador de Negocios'),
                ])->columnSpanFull()->columns(4),
            ]);
    }
}