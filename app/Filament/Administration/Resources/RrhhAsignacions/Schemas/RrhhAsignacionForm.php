<?php

namespace App\Filament\Administration\Resources\RrhhAsignacions\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class RrhhAsignacionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                //
                Section::make("Información General")
                ->description("Formulario para la carga de asignaciones, las cuales esta asociadas al cargo")
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre de la Asignacion')
                        ->required(),
                    TextInput::make('description')
                        ->label('Descripción de la Asignacion')
                        ->required(),
                    TextInput::make('monto')
                        ->label('Monto')
                        ->numeric()
                        ->required(),
                    Select::make('cargo_id')
                        ->label('Cargo')
                        ->relationship('cargo', 'description')
                        ->required(),
                    Hidden::make('created_by')->default(Auth::user()->name)->hiddenOn('edit'),
                    Hidden::make('updated_by')->default(Auth::user()->name)->hiddenOn('create'),
                ])
                ->columns(2)
                ->columnSpanFull()
            ]);
    }
}
