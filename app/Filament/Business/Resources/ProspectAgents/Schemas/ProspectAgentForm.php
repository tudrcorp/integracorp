<?php

namespace App\Filament\Business\Resources\ProspectAgents\Schemas;

use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ProspectAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                ->icon('heroicon-o-puzzle-piece')
                ->description('Selecciona e tipo de prospecto que va a registrar')
                ->heading("Tipo de Prospecto")
                    ->schema([
                        Select::make('type')
                                ->options([
                                    'agencia-corretaje'     => 'Agencia (corretaje)',
                                    'agente-corretaje'      => 'Agente (Corretaje)',
                                    'agencia-viajes'        => 'Agencia de Viajes',
                                    'mayorista-viajes'      => 'Mayorista de Viajes',
                                    'freelance'             => 'Freelance',
                                    'asesor-exclusivo'      => 'Asesor exclusivo',
                                    'cliente-individual'    => 'Cliente Individual',
                                    'cliente-corporativo'   => 'Cliente Corporativo',
                                    'ejecutivo'             => 'Ejecutivo',
                                    'otro'                  => 'Otro',
                                ])
                                ->required()
                                ->preload()
                                ->searchable()
                                ->label('Tipo'),
                        Select::make('reference_by')
                            ->options([
                                'directiva-TDG'         => 'Directiva TDG',
                                'gerencia-de-negocios'  => 'Gerencia de Negocios',
                                'whatsapp-comercial'    => 'Whatsapp Comercial',
                                'redes-sociales'        => 'Redes sociales',
                                'tercero'               => 'Tercero',
                                'otro'                  => 'Otro',
                            ])
                            ->required()
                            ->preload()
                            ->searchable()
                            ->label('Referido por'),
                    ])->columnSpan(1)->columns(2),
                Section::make()
                ->heading('Información del prospecto')
                ->description('Información principal y de contacto del prospecto')
                ->icon('heroicon-o-user')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->label('Nombre y Apellido'),
                    TextInput::make('phone_1')
                        ->tel()
                        ->regex('/^[0-9]*$/')
                        ->validationMessages([
                            'regex' => 'Este campo solo debe contener números, sin letras, espacios ni caracteres especiales.',
                        ])
                        ->helperText('Formato: 04125678909, 04145678909, 02125678909, sin signos de puntuación y sin espacios en blanco.')
                        ->required()
                        ->label('Teléfono Principal'),
                    TextInput::make('phone_2')
                        ->tel()
                        ->helperText('Formato: 04125678909, 04145678909, 02125678909, sin signos de puntuación y sin espacios en blanco.')
                        ->regex('/^[0-9]*$/')
                        ->validationMessages([
                            'regex' => 'Este campo solo debe contener números, sin letras, espacios ni caracteres especiales.',
                        ])
                        ->label('Teléfono Alternativo'),
                    TextInput::make('email')
                        ->label('Correo Electrónico')
                        ->email()
                        ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                        ->unique(
                            table: 'prospect_agents',
                            column: 'email',
                        )
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
                    //Estatus selección simple: Captación – Contacto inicial  – Prospecto –  Aliado Activo – Inactivo – En proceso – En negociación
                    Select::make('status')
                            ->options([
                                'captación'             => 'Captación',
                                'contacto-inicial'      => 'Contacto inicial',
                                'prospecto'             => 'Prospecto',
                                'aliado-activo'         => 'Aliado Activo',
                                'inactivo'              => 'Inactivo',
                                'en-proceso'            => 'En proceso',
                                'en-negociación'        => 'En negociación',
                            ])
                            ->required()
                            ->preload()
                            ->searchable()
                            ->label('Estatus'),
                    //En referido por selección simple: Directiva TDG – Gerencia de Negocios – Whatsapp Comercial – Redes sociales – Tercero – Otro.
                    
                    TextInput::make('created_by')
                        ->default(auth()->user()->name)
                        ->disabled()
                        ->dehydrated()
                        ->hiddenOn('edit')
                        ->required(),
                    
                ])->columnSpanFull()->columns(4),
            ]);
    }
}
