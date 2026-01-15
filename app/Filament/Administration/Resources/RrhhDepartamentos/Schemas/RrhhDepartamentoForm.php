<?php

namespace App\Filament\Administration\Resources\RrhhDepartamentos\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhDepartamentoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Información General")
                    ->description("Formulario para la carga de los departamentos de la Organizacion")
                    ->schema([
                        TextInput::make('description')
                            ->label('Nombre del Departamento')
                            ->required()
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('description', $state.toUpperCase());
                            JS),
                        Hidden::make('created_by')->default(Auth::user()->name)->hiddenOn('edit'),
                        Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                    ])
                    ->columns(3)
                    ->columnSpanFull()
            ]);
    }
}
