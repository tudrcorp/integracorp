<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Models\Service;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('SERVICIOS')
                ->description('Fomulario para el registro de servicios. Campo Requerido(*)')
                ->icon('heroicon-s-user-plus')
                ->schema([
                    TextInput::make('code')
                        ->label('Código')
                        ->prefixIcon('heroicon-m-clipboard-document-check')
                        ->default(function () {
                            if (Service::max('id') == null) {
                                $parte_entera = 0;
                            } else {
                                $parte_entera = Service::max('id');
                            }
                            return 'TDEC-SER-000' . $parte_entera + 1;
                        })
                        ->required()
                        ->disabled()
                        ->dehydrated()
                        ->maxLength(255),
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
                ])->columnSpanFull()->columns(2),
            ]);
    }
}