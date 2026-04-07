<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class UserInfolist
{
    /**
     * Vista experimental «Liquid Glass»: mismas clases CSS que Marketing (theme.css).
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Informacion del Usuario')
                    ->description('Datos personales y departamento.')
                    ->icon(Heroicon::OutlinedUser)
                    ->iconColor('primary')
                    ->extraAttributes([
                        'class' => 'fi-capemiac-liquid-glass-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => 'fi-capemiac-liquid-glass-inset',
                            ])
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
                            ]),
                    ]),
                Section::make('Rol del Usuario')
                    ->description('Permisos y perfiles asignados.')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->iconColor('gray')
                    ->extraAttributes([
                        'class' => 'fi-capemiac-liquid-glass-section',
                    ])
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                            ->extraAttributes([
                                'class' => 'fi-capemiac-liquid-glass-inset',
                            ])
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
                            ]),
                    ]),
            ]);
    }
}
