<?php

namespace App\Filament\Resources\Limits\Schemas;

use App\Models\Limit;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class LimitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('LIMITES')
                ->description('Formulario para el registro de los limites asociados a los beneficios de planes. Campo Requerido(*)')
                ->icon('heroicon-c-adjustments-horizontal')
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (Limit::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = Limit::max('id');
                                }
                                return 'TDEC-LT-000' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ])->columnSpanFull()->columns(3),
                    TextInput::make('description')
                        ->label('Definición')
                        ->prefixIcon('heroicon-m-pencil')
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('description', strtoupper($state));
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