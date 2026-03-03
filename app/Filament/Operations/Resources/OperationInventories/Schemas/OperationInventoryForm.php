<?php

namespace App\Filament\Operations\Resources\OperationInventories\Schemas;

use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class OperationInventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informacion del Medicamento')
                    ->description('Informacion principal del medicamento')
                    ->icon('heroicon-o-identification')
                    ->schema([
                        Grid::make(4)
                            ->schema([
                                TextInput::make('name')
                                    ->label('Descripcion del Medicamento')
                                    ->required(),
                                TextInput::make('unit')
                                    ->label('Unidad')
                                    ->required(),
                                TextInput::make('type')
                                    ->label('Tipo')
                                    ->required(),
                                TextInput::make('cost')
                                    ->label('Costo del Medicamento')
                                    ->description('Costo del medicamento en dolares, para uso de la parte administrativa')
                                    ->numeric()
                                    ->default(0.0)
                                    ->prefix('$'),
                                Hidden::make('created_by')
                                    ->default(Auth::user()->name),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }
}
