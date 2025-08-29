<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\Schemas;

use App\Models\Agent;
use App\Models\Agency;
use App\Models\AgeRange;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use App\Http\Controllers\UtilsController;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class CorporateQuoteRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('SOLICITANTE')
                        ->schema([
                            Section::make('data_client')
                                ->heading('¡Bienvenido/a de nuevo! 👋 ')
                                ->description('Estás a punto de comenzar a crear una nueva cotización DRESS-TAYLOR, por favor ingresa la información del cliente para personalizarla. ¡Puede ver el avance del proceso en la barra de estatus!')
                                ->schema([
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('code')
                                                ->label('Nro. de solicitud')
                                                ->prefixIcon('heroicon-m-clipboard-document-check')
                                                ->default(function () {
                                                    if (CorporateQuoteRequest::max('id') == null) {
                                                        $parte_entera = 0;
                                                    } else {
                                                        $parte_entera = CorporateQuoteRequest::max('id');
                                                    }
                                                    return 'SOL-CORP-000' . $parte_entera + 1;
                                                })
                                                ->disabled()
                                                ->dehydrated()
                                                ->maxLength(255),
                                            TextInput::make('poblation')
                                                ->label('Población / Nro de personas')
                                                ->numeric()
                                                ->prefixIcon('heroicon-m-user')
                                        ])->columnSpanFull(),
                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('full_name')
                                                ->label('Nombre de la Empresa')
                                                ->prefixIcon('heroicon-m-user')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                                ->maxLength(255)->afterStateUpdated(function (Set $set, $state) {
                                                    $set('full_name', strtoupper($state));
                                                })
                                                ->live(onBlur: true),

                                            Select::make('country_code')
                                                ->label('Código de país')
                                                ->options(UtilsController::getCountries())
                                                ->searchable()
                                                ->default('+58')
                                                ->live(onBlur: true)
                                                ->validationMessages([
                                                    'required'  => 'Campo Requerido',
                                                ])
                                                ->hiddenOn('edit'),
                                            TextInput::make('phone')
                                                ->prefixIcon('heroicon-s-phone')
                                                ->tel()
                                                ->label('Número de teléfono')
                                                ->validationMessages([
                                                    'required'  => 'Campo Requerido',
                                                ])
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                                    $countryCode = $get('country_code');
                                                    if ($countryCode) {
                                                        $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                                        $set('phone', $countryCode . $cleanNumber);
                                                    }
                                                }),
                                            TextInput::make('email')
                                                ->label('Correo electrónico')
                                                ->prefixIcon('heroicon-m-user')
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                ])
                                                ->maxLength(255),
                                        ])->columnSpanFull(),
                                    Grid::make(1)
                                        ->schema([
                                            Textarea::make('observations')
                                                ->label('Especificaciones de la cotización')
                                                ->helperText('Por favor, describa las especificaciones de la cotización de forma detallada del tipo de plan, beneficios, coberturas y rango de edades que debe estar asociados a la solicitud.')
                                                ->required()
                                                ->autosize()
                                        ])->columnSpanFull(),
                                    Hidden::make('status')->default('PRE-APROBADA'),
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                    Hidden::make('agent_id')->default(Auth::user()->agent_id),
                                    Hidden::make('code_agency')->default(function () {
                                        $code_agency = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;
                                        return $code_agency;
                                    }),
                                    Hidden::make('owner_code')->default(function () {
                                        $owner      = Agent::select('owner_code', 'id')->where('id', Auth::user()->agent_id)->first()->owner_code;

                                        if ($owner == 'TDG-100') {
                                            /**
                                             * Cuando el agente pertenece a TDG-100
                                             * ------------------------------------------
                                             */
                                            return $owner;
                                        } else {
                                            /**
                                             * Cuando el agente pertenece a una agencia Master
                                             * ---------------------------------------------------------------------------------------------
                                             */
                                            $jerarquia  = Agency::select('code', 'owner_code')->where('code', $owner)->first()->owner_code;
                                            return $jerarquia;
                                        }

                                        /**
                                         * Cuando el agente pertenece a una AGENCIA GENERAL
                                         * ------------------------------------------------------
                                         */
                                        if ($owner != $jerarquia && $jerarquia != 'TDG-100') {
                                            return $jerarquia;
                                        }

                                        /**
                                         * Cuando el agente pertenece a una AGENCIA MASTER
                                         * ------------------------------------------------------
                                         */
                                        if ($owner != $jerarquia && $jerarquia == 'TDG-100') {
                                            return $owner;
                                        }
                                    }),
                                ])
                                ->columns(3)
                                ->columnSpanFull()
                        ]),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear cotización
                    </x-filament::button>
                BLADE)))
                ->hiddenOn('edit')
                ->columnSpanFull(),
            ]);
    }
}