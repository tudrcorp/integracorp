<?php

namespace App\Filament\Administration\Resources\RrhhCargos\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhCargoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Información General")
                    ->description("Formulario para la carga de los cargos de la Organizacion")
                    ->schema([
                        TextInput::make('description')
                            ->label('Nombre del Cargo')
                            ->required()
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('description', $state.toUpperCase());
                            JS),
                        Select::make('departamento_id')
                            ->label('Departamento')
                            ->relationship('departamento', 'description')
                            ->createOptionForm([
                                TextInput::make('description')
                                    ->label('Nombre del Departamento')
                                    ->required()
                                    ->afterStateUpdatedJs(<<<'JS'
                                        $set('description', $state.toUpperCase());
                                    JS),
                            ])
                            ->required(),
                        Hidden::make('created_by')->default(Auth::user()->name)->hiddenOn('edit'),
                        Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
            ]);
    }
}
