<?php

namespace App\Filament\Marketing\Resources\ContactLists\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class ContactListForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del contacto')
                    ->description('Información principal del contacto en la lista')
                    ->icon('heroicon-o-user-circle')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('full_name')
                                            ->label('Nombre completo')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('full_name', $state.toUpperCase());
                                            JS)
                                            ->placeholder('Ej. Juan Pérez')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('email')
                                            ->label('Correo electrónico')
                                            ->email()
                                            ->placeholder('ejemplo@correo.com')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('phone')
                                            ->label('Teléfono')
                                            ->tel()
                                            ->placeholder('Ej. +58 412 1234567')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('group')
                                            ->label('Grupo')
                                            ->afterStateUpdatedJs(<<<'JS'
                                                $set('group', $state.toUpperCase());
                                            JS)
                                            ->placeholder('Ej. Prospectos, Clientes')
                                            ->required()
                                            ->maxLength(255)
                                            ->helperText('Agrupación o categoría del contacto')
                                            ->columnSpan(1),
                                        ColorPicker::make('group_color')
                                            ->label('Color del grupo')
                                            ->helperText('Color para identificar el grupo en listas')
                                            ->default('gray'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Datos del propietario')
                    ->description('Persona responsable o asignada del contacto')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Fieldset::make()
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('owner__full_name')
                                            ->label('Nombre del propietario')
                                            ->placeholder('Ej. María González')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('owner_phone')
                                            ->label('Teléfono del propietario')
                                            ->tel()
                                            ->placeholder('Ej. +58 414 7654321')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(1),
                                        TextInput::make('owner_email')
                                            ->label('Correo del propietario')
                                            ->email()
                                            ->placeholder('propietario@correo.com')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Hidden::make('created_by')
                                            ->default(fn(): string => Auth::user()?->name ?? '')
                                            ->dehydrated()
                                            ->hiddenOn('edit'),
                                        Hidden::make('updated_by')
                                            ->default(fn(): string => Auth::user()?->name ?? '')
                                            ->dehydrated()
                                            ->hiddenOn('create'),
                                        Hidden::make('status')
                                            ->default('ACTIVO')
                                            ->dehydrated()
                                            ->hiddenOn('create'),
                                    ]),
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
