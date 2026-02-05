<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BusinessAppointmentsForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                ->heading('Formulario de Citas')
                ->schema([
                    TextInput::make('legal_name')
                        ->label('Nombre')
                        ->required(),
                    TextInput::make('phone')
                        ->label('Telefono')
                        ->tel()
                        ->required(),
                    TextInput::make('email')
                        ->label('Email address')
                        ->email()
                        ->required(),
                    Select::make('country_id')
                        ->label('País')
                        ->live()
                        ->options(Country::all()->pluck('name', 'id'))
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->default(189)
                        ->preload(),
                    Select::make('state_id')
                        ->label('Estado')
                        ->options(function (Get $get) {
                            return State::where('country_id', $get('country_id'))->pluck('definition', 'id');
                        })
                        ->live()
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->preload(),
                    Select::make('city_id')
                        ->label('Ciudad')
                        ->options(function (Get $get) {
                            return City::where('country_id', $get('country_id'))->where('state_id', $get('state_id'))->pluck('definition', 'id');
                        })
                        ->searchable()
                        ->prefixIcon('heroicon-s-globe-europe-africa')
                        ->required()
                        ->validationMessages([
                            'required'  => 'Campo Requerido',
                        ])
                        ->preload(),
                    TextInput::make('status')
                        ->label('Estado')
                        ->required()
                        ->default('PENDIENTE'),
                    Hidden::make('created_by')
                        ->label('Creado por')
                        ->hiddenOn('edit')
                        ->default(auth()->user()->id),
                    Hidden::make('updated_by')
                        ->label('Actualizado por')
                        ->hiddenOn('create'),
                ])->columnSpanFull()->columns(4),
            ]);
    }
}
