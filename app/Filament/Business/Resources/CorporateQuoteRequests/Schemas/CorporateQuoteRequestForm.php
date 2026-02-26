<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Schemas;

use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\CorporateQuoteRequest;
use App\Models\Plan;
use App\Models\Region;
use App\Models\State;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

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
                                                ->label('Correo Electrónico')
                                                ->email()
                                                ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/')
                                                ->validationMessages([
                                                    'required' => 'Campo requerido',
                                                    'email' => 'El correo no es valido',
                                                    'regex' => 'El correo no debe contener mayúsculas, espacios, ñ, ni caracteres especiales no permitidos.',
                                                ]),
                                        ])->columnSpanFull(),
                                    Grid::make(1)
                                        ->schema([
                                            Textarea::make('observations')
                                                ->label('Especificaciones de la cotización')
                                                ->helperText('Por favor, describa las especificaciones de la cotización de forma detallada del tipo de plan, beneficios, coberturas y rango de edades que debe estar asociados a la solicitud.')
                                                ->required()
                                                ->autosize()
                                        ])->columnSpanFull(),
                                    Fieldset::make('Asociar Agencia y/o Agente')
                                        ->schema([
                                            Select::make('code_agency')
                                                ->label('Lista de Agencias')
                                                ->options(Agency::all()->pluck('name_corporative', 'code'))
                                                ->searchable()
                                                ->live()
                                                ->prefixIcon('heroicon-c-building-library')
                                                ->preload(),
                                            Select::make('agent_id')
                                                ->label('Agentes')
                                                ->options(function (Get $get) {
                                                    if ($get('code_agency') == null) {
                                                        return Agent::where('owner_code', 'TDG-100')->pluck('name', 'id');
                                                    }
                                                    return Agent::where('owner_code', $get('code_agency'))->pluck('name', 'id');
                                                })
                                                ->searchable()
                                                ->prefixIcon('fontisto-person')
                                                ->preload(),
                                        ])->columnSpanFull(),
                                    Hidden::make('created_by')->default(Auth::user()->name),
                                    Hidden::make('status')->default('PRE-APROBADA'),
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
                        Generar Solicitud
                    </x-filament::button>
                BLADE)))
                    // ->hiddenOn('edit')
                    ->columnSpanFull(),
            ]);
    }
}