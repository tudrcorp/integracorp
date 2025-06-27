<?php

namespace App\Filament\Resources\BusinessUnits\Schemas;

use App\Models\BusinessUnit;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class BusinessUnitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('UNIDAD DE NEGOCIO')
                ->description('Formulario para el registro de la unidad de negocio. Campo Requerido(*)')
                ->icon('heroicon-m-rectangle-group')
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (BusinessUnit::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = BusinessUnit::max('id');
                                }
                                return 'TDEC-UN-000' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ])->columnSpanFull()->columns(3),
                    TextInput::make('definition')
                        ->label('Definición')
                        ->prefixIcon('heroicon-m-pencil')
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('definition', strtoupper($state));
                        })
                        ->live(onBlur: true)
                        ->required()
                        ->maxLength(255),
                    TextInput::make('status')
                        ->label('Estatus')
                        ->prefixIcon('heroicon-m-shield-check')
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255)
                        ->default('ACTIVO'),
                    TextInput::make('created_by')
                        ->label('Creado Por:')
                        ->prefixIcon('heroicon-s-user-circle')
                        ->disabled()
                        ->dehydrated()
                        ->default(Auth::user()->name)
                        ->maxLength(255),
                ])->columnSpanFull()->columns(3),
            ]);
    }
}