<?php

namespace App\Filament\Resources\Fees\Schemas;

use App\Models\Fee;
use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class FeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('TARIFAS')
                    ->description('Formulario para el registro de tarifas . Campo Requerido(*)')
                    ->icon('heroicon-m-book-open')
                    ->schema([
                        Grid::make()->schema([
                            TextInput::make('code')
                                ->label('Código')
                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                ->default(function () {
                                    if (Fee::max('id') == null) {
                                        $parte_entera = 0;
                                    } else {
                                        $parte_entera = Fee::max('id');
                                    }
                                    return 'TDEC-FA-000' . $parte_entera + 1;
                                })
                                ->required()
                                ->disabled()
                                ->dehydrated()
                                ->maxLength(255),
                        ])->columnSpanFull()->columns(4),
                        Select::make('age_range_id')
                            ->label('Rango de edad')
                            ->relationship('ageRange', 'range')
                            ->createOptionForm([
                                Section::make('RANGO DE EDADES')
                                    ->description('Formulario para el registro de los rangos dd edades. Campo Requerido(*)')
                                    ->icon('heroicon-s-adjustments-vertical')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (AgeRange::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = AgeRange::max('id');
                                                    }
                                                    return 'TDEC-RE-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                            Select::make('plan_id')
                                                ->relationship('plan', 'description')
                                                ->label('Plan')
                                                ->searchable()
                                                ->preload()
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                        ]),
                                        TextInput::make('range')
                                            ->label('Rango de edad')
                                            ->prefixIcon('heroicon-m-shield-check')
                                            ->maxLength(255),
                                        Hidden::make('status')->default('ACTIVO'),
                                        Hidden::make('created_by')->default(Auth::user()->name)
                                    ])->columnSpanFull()->columns(3),
                            ])
                            ->searchable()
                            ->preload()
                            ->required(),
                        Select::make('coverage_id')
                            ->label('Covertura')
                            ->options(Coverage::where('status', 'ACTIVO')->pluck('price', 'id'))
                            ->preload()
                            ->searchable()
                            ->createOptionForm([
                                Section::make('COBERTURA')
                                    ->description('Formulario para el registro de las coberturas asociadas a los beneficios. Campo Requerido(*)')
                                    ->icon('heroicon-s-document-currency-dollar')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('code')
                                                ->label('Código')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (Coverage::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = Coverage::max('id');
                                                    }
                                                    return 'TDEC-CO-000' . $parte_entera + 1;
                                                })
                                                ->required()
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                            TextInput::make('price')
                                                ->label('Precio US$')
                                                ->helperText('Precio de la cobertura en dólares estadounidenses. Utilice separador decimal(.)')
                                                ->prefix('US$')
                                                ->required()
                                                ->numeric()
                                                ->validationMessages([
                                                    'required' => 'El precio debe ser numérico.',
                                                ]),
                                        ]),

                                        hidden::make('status')->default('ACTIVO'),
                                        hidden::make('created_by')->default(Auth::user()->name)
                                    ])->columnSpanFull()->columns(3),
                            ]),

                        TextInput::make('price')
                            ->required()
                            ->numeric()
                            ->prefix('$'),
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