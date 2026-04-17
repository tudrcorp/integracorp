<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents\Schemas;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentLabels;
use App\Models\City;
use App\Models\Country;
use App\Models\State;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProspectAgentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Clasificación')
                    ->icon(Heroicon::OutlinedTag)
                    ->description('Define el tipo de prospecto y el canal por el que llegó.')
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                Select::make('type')
                                    ->label('Tipo de prospecto')
                                    ->options(ProspectAgentLabels::typeOptions())
                                    ->required()
                                    ->preload()
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('Selecciona un tipo'),
                                Select::make('reference_by')
                                    ->label('Referido por')
                                    ->options(ProspectAgentLabels::referenceOptions())
                                    ->required()
                                    ->preload()
                                    ->searchable()
                                    ->native(false)
                                    ->placeholder('Selecciona el origen'),
                            ]),
                    ]),
                Section::make('Datos del prospecto')
                    ->icon(Heroicon::OutlinedUser)
                    ->description('Nombre completo y canales de contacto.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre y apellido / Razon social')
                            ->required()
                            ->maxLength(255)
                            ->autocomplete('name')
                            ->placeholder('Ej. María Pérez')
                            ->columnSpanFull(),
                        Grid::make()
                            ->columns(['default' => 1, 'lg' => 2])
                            ->schema([
                                TextInput::make('phone_1')
                                    ->label('Teléfono principal')
                                    ->tel()
                                    ->required()
                                    ->regex('/^[0-9]+$/')
                                    ->validationMessages([
                                        'regex' => 'Solo números, sin espacios ni signos.',
                                    ])
                                    ->helperText('Ej. 04125678909 (solo dígitos).')
                                    ->placeholder('04125678909')
                                    ->autocomplete('tel-national'),
                                TextInput::make('phone_2')
                                    ->label('Teléfono alternativo')
                                    ->tel()
                                    ->regex('/^[0-9]*$/')
                                    ->validationMessages([
                                        'regex' => 'Solo números, sin espacios ni signos.',
                                    ])
                                    ->helperText('Opcional. Mismo formato que el principal.')
                                    ->placeholder('04145678909')
                                    ->autocomplete('tel-national'),
                            ]),
                        TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->rule('regex:/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i')
                            ->unique(
                                table: 'prospect_agents',
                                column: 'email',
                                ignoreRecord: true,
                            )
                            ->required()
                            ->maxLength(255)
                            ->placeholder('correo@ejemplo.com')
                            ->autocomplete('email')
                            ->columnSpanFull(),
                    ]),
                Section::make('Ubicación')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->description('País, estado y ciudad del prospecto.')
                    ->schema([
                        Grid::make()
                            ->columns(['default' => 1, 'md' => 3])
                            ->schema([
                                Select::make('country_id')
                                    ->label('País')
                                    ->live()
                                    ->options(
                                        Country::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                    )
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedGlobeAmericas)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona un país.',
                                    ])
                                    ->default(189)
                                    ->preload()
                                    ->native(false),
                                Select::make('state_id')
                                    ->label('Estado')
                                    ->options(function (Get $get): array {
                                        $countryId = $get('country_id');

                                        if (blank($countryId)) {
                                            return [];
                                        }

                                        return State::query()
                                            ->where('country_id', $countryId)
                                            ->orderBy('definition')
                                            ->pluck('definition', 'id')
                                            ->all();
                                    })
                                    ->live()
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedMap)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona un estado.',
                                    ])
                                    ->preload()
                                    ->native(false),
                                Select::make('city_id')
                                    ->label('Ciudad')
                                    ->options(function (Get $get): array {
                                        $countryId = $get('country_id');
                                        $stateId = $get('state_id');

                                        if (blank($countryId) || blank($stateId)) {
                                            return [];
                                        }

                                        return City::query()
                                            ->where('country_id', $countryId)
                                            ->where('state_id', $stateId)
                                            ->orderBy('definition')
                                            ->pluck('definition', 'id')
                                            ->all();
                                    })
                                    ->searchable()
                                    ->prefixIcon(Heroicon::OutlinedBuildingOffice2)
                                    ->required()
                                    ->validationMessages([
                                        'required' => 'Selecciona una ciudad.',
                                    ])
                                    ->preload()
                                    ->native(false),
                            ]),
                    ]),
                Section::make('Embudo comercial')
                    ->icon(Heroicon::OutlinedChartBar)
                    ->description('Etapa actual del prospecto en el proceso de captación.')
                    ->schema([
                        Select::make('status')
                            ->label('Estatus')
                            ->options(ProspectAgentLabels::statusOptions())
                            ->required()
                            ->preload()
                            ->searchable()
                            ->native(false)
                            ->placeholder('Selecciona el estatus')
                            ->columnSpanFull(),
                    ]),
                Hidden::make('created_by')
                    ->default(fn (): ?string => auth()->user()?->name)
                    ->hiddenOn('edit')
                    ->dehydrated()
                    ->required(),
            ]);
    }
}
