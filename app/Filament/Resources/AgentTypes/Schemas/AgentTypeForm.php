<?php

namespace App\Filament\Resources\AgentTypes\Schemas;

use App\Models\AgentType;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class AgentTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('CLASIFICACIÓN DE AGENTES')
                ->description('Formulario para el registro de los tipos de agente. Campo Requerido(*)')
                ->icon('heroicon-s-user-group')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (AgentType::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = AgentType::max('id');
                                }
                                return 'TDEC-TAT-000' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ])->columnSpanFull(),
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