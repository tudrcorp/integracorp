<?php

namespace App\Filament\Resources\Coverages\Schemas;

use App\Models\Coverage;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class CoverageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('COBERTURA')
                ->description('Formulario para el registro de las coberturas asociadas a los beneficios. Campo Requerido(*)')
                ->icon('heroicon-s-document-currency-dollar')
                ->schema([
                    Grid::make()->schema([
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
                    ])->columnSpanFull()->columns(4),
                    TextInput::make('price')
                        ->label('Precio US$')
                        ->helperText('Precio de la cobertura en dólares estadounidenses. Utilice separador decimal(.)')
                        ->prefix('US$')
                        ->required()
                        ->numeric()
                        ->validationMessages([
                            'required' => 'El precio debe ser numérico.',
                        ]),

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