<?php

namespace App\Filament\Resources\BusinessLines\Schemas;

use App\Models\BusinessLine;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class BusinessLineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('LÍNEA DE NEGOCIO')
                ->description('Formulario para el registro de la línea de negocio. Campo Requerido(*)')
                ->icon('heroicon-o-arrow-trending-up')
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (BusinessLine::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = BusinessLine::max('id');
                                }
                                return 'TDEC-LN-000' . $parte_entera + 1;
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
                    Select::make('business_unit_id')
                        ->label('Unidad de Negocio')
                        ->relationship('businessUnit', 'definition')
                        ->prefixIcon('heroicon-m-pencil')
                        ->preload()
                        ->required(),
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