<?php

namespace App\Filament\Business\Resources\Benefits\Schemas;

use App\Models\Benefit;
use App\Models\Limit;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class BenefitForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos del beneficio')
                    ->description('Información principal del beneficio asociado a los planes. Los campos marcados con (*) son obligatorios.')
                    ->icon('heroicon-o-sparkles')
                    ->schema([
                        Fieldset::make('Identificación')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('code')
                                            ->label('Código')
                                            ->prefixIcon('heroicon-m-clipboard-document-list')
                                            ->default(function () {
                                                $maxId = Benefit::max('id') ?? 0;

                                                return 'TDEC-BN-'.str_pad((string) ($maxId + 1), 4, '0', STR_PAD_LEFT);
                                            })
                                            ->required()
                                            ->disabled()
                                            ->dehydrated()
                                            ->maxLength(255),
                                        TextInput::make('description')
                                            ->label('Definición')
                                            ->prefixIcon('heroicon-m-pencil-square')
                                            ->placeholder('Ej: Consulta médica general')
                                            ->afterStateUpdated(function (Set $set, $state) {
                                                $set('description', strtoupper($state));
                                            })
                                            ->live(onBlur: true)
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpan(2),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        Fieldset::make('Límite y estado')
                            ->schema([
                                Grid::make(4)
                                    ->schema([
                                        Select::make('limit_id')
                                            ->label('Límite de consumo')
                                            ->relationship('limit', 'description')
                                            ->searchable()
                                            ->preload()
                                            ->columnSpan(2)
                                            ->placeholder('Seleccione un límite')
                                            ->createOptionForm([
                                                Section::make('Nuevo límite')
                                                    ->description('Registro de límite asociado a beneficios.')
                                                    ->icon('heroicon-o-adjustments-horizontal')
                                                    ->schema([
                                                        Grid::make(3)->schema([
                                                            TextInput::make('code')
                                                                ->label('Código')
                                                                ->prefixIcon('heroicon-m-clipboard-document-list')
                                                                ->default(function () {
                                                                    $maxId = Limit::max('id') ?? 0;

                                                                    return 'TDEC-LIM-' . str_pad((string) ($maxId + 1), 4, '0', STR_PAD_LEFT);
                                                                })
                                                                ->required()
                                                                ->disabled()
                                                                ->dehydrated()
                                                                ->maxLength(255),
                                                            TextInput::make('description')
                                                                ->label('Definición')
                                                                ->prefixIcon('heroicon-m-pencil-square')
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
                                                                ->default('ACTIVO')
                                                                ->maxLength(255),
                                                            TextInput::make('created_by')
                                                                ->label('Creado por')
                                                                ->prefixIcon('heroicon-o-user')
                                                                ->disabled()
                                                                ->dehydrated()
                                                                ->default(Auth::user()->name)
                                                                ->maxLength(255),
                                                        ])->columnSpanFull(),
                                                    ])->columnSpanFull(),
                                                            ]),
                                        TextInput::make('status')
                                            ->label('Estatus')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default('ACTIVO')
                                            ->maxLength(255),
                                        TextInput::make('created_by')
                                            ->label('Creado por')
                                            ->prefixIcon('heroicon-o-user')
                                            ->disabled()
                                            ->dehydrated()
                                            ->default(Auth::user()->name)
                                            ->maxLength(255),
                                    ])->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Fieldset::make('Tipo de beneficio')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Toggle::make('is_upgrade')
                                            ->label('Beneficio upgrade')
                                            ->helperText('Activa esta opción si el beneficio es un upgrade (adicional al plan base).')
                                            ->default(false)
                                            ->inline(false),
                                    ]),
                            ])
                            ->columnSpanFull(),

                        
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
