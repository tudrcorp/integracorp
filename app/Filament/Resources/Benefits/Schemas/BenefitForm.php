<?php

namespace App\Filament\Resources\Benefits\Schemas;

use App\Models\Plan;
use App\Models\Limit;
use App\Models\Benefit;
use App\Models\Coverage;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Barryvdh\Debugbar\Facades\Debugbar;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

use Illuminate\Database\Eloquent\Builder;

class BenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('BENEFICIOS')
                ->description('Formulario para el registro de los beneficios asociados a los planes. Campo Requerido(*)')
                ->icon('heroicon-s-share')
                ->schema([
                    Grid::make()->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (Benefit::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = Benefit::max('id');
                                }
                                return 'TDEC-BN-000' . $parte_entera + 1;
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

                    Select::make('limit_id')
                        ->label('Límite')
                        ->relationship('limit', 'description')
                        ->searchable()
                        ->preload()
                        ->createOptionForm([
                            Section::make('LIMITES')
                                ->description('Formulario para el registro de los limites asociados a los beneficios de planes. Campo Requerido(*)')
                                ->icon('heroicon-c-adjustments-horizontal')
                                ->schema([
                                    Grid::make(3)->schema([
                                        TextInput::make('code')
                                            ->label('Código')
                                            ->prefixIcon('heroicon-m-clipboard-document-check')
                                            ->default(function () {
                                                if (Limit::max('id') == null) {
                                                    $parte_entera = 0;
                                                } else {
                                                    $parte_entera = Limit::max('id');
                                                }
                                                return 'TDEC-BN-000' . $parte_entera + 1;
                                            })
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255),
                                    ]),
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
                        ]),
                    TextInput::make('price')
                        ->label('Precio US$')
                        ->prefixIcon('heroicon-m-shield-check')
                        ->numeric()
                        ->placeholder('0,00 US$'),
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