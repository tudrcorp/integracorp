<?php

namespace App\Filament\Business\Resources\Affiliations\RelationManagers;

use BackedEnum;
use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Plan;
use App\Models\AgeRange;
use App\Models\Coverage;
use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Illuminate\Support\Facades\Log;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Business\Resources\Affiliations\AffiliationResource;

class AffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliates';

    protected static ?string $title = 'FAMILIARES AFILIADOS';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAMILIAR')
                    ->description('Fomulario de familiar.')
                    ->icon('heroicon-s-user')
                    ->schema([
                        TextInput::make('full_name')
                            ->required()
                            ->label('Nombre completo'),
                        TextInput::make('nro_identificacion')
                            ->label('Numero de Identificacion')
                            ->required()
                            ->numeric(),
                        Select::make('sex')
                            ->label('Genero')
                            ->required()
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ]),
                        Grid::make()
                            ->schema([
                                DatePicker::make('birth_date')
                                    ->label('Fecha de Nacimiento')
                                    ->required()
                                    ->live()
                                    ->format('d/m/Y')
                                    ->afterStateUpdated(function (Set $set, $state) {
                                        $set('age', intval(Carbon::createFromFormat('d/m/Y', $state)->diffInYears(now())));
                                    }),
                                TextInput::make('age')
                                    ->label('Edad')
                                    ->required()
                                    ->live()
                                    ->numeric(),
                                Select::make('relationship')
                                    ->label('Parentesco')
                                    ->required()
                                    ->options([
                                        'MADRE'     => 'MADRE',
                                        'PADRE'     => 'PADRE',
                                        'ESPOSA'    => 'ESPOSA',
                                        'ESPOSO'    => 'ESPOSO',
                                        'HIJO'      => 'HIJO',
                                        'HIJA'      => 'HIJA',
                                    ]),
                                
                            ])->columnSpanFull()->columns(2),
                        Textarea::make('address')
                            ->label('Direccion')
                            ->columnSpanFull()
                            ->required()
                            ->autosize(),
                        Fieldset::make('Plan de afiliación')
                            ->schema([
                                Select::make('plan_id')
                                    ->options(function () {
                                        return Plan::all()->pluck('description', 'id');
                                    })
                                    ->label('Planes')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload()
                                    ->placeholder('Seleccione plan(es)'),

                                Select::make('age_range_id')
                                    ->label('Rango de edad')
                                    ->options(function (get $get, $state) {
                                        Log::info($state);
                                        return AgeRange::where('plan_id', $get('plan_id'))->get()->pluck('range', 'id');
                                    })
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('coverage_id')
                                    ->label('Cobertura')
                                    ->options(function (get $get) {
                                        if ($get('age_range_id') == 1 || $get('age_range_id') == NULL) {
                                            return [];
                                        }
                                        $arrayFee = AgeRange::where('plan_id', $get('plan_id'))->where('id', $get('age_range_id'))->with('fees')->get()->toArray();
                                        return collect($arrayFee[0]['fees'])->pluck('coverage', 'coverage_id');
                                    })
                                    ->searchable()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),

                                Select::make('fee')
                                    ->label('Tarifa Anual')
                                    ->options(function (get $get) {
                                        Log::info(Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price'));
                                        return Fee::where('age_range_id', $get('age_range_id'))->where('coverage_id', $get('coverage_id'))->get()->pluck('price', 'price');
                                    })
                                    ->live()
                                    ->searchable()
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->preload(),
                                Select::make('payment_frequency')
                                    ->label('Frecuencia de pago')
                                    ->live()
                                    ->options([
                                        'ANUAL'      => 'ANUAL',
                                        'SEMESTRAL'  => 'SEMESTRAL',
                                        'TRIMESTRAL' => 'TRIMESTRAL',
                                    ])
                                    ->searchable()
                                    ->live()
                                    ->prefixIcon('heroicon-s-globe-europe-africa')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Obligatorio',
                                    ])
                                    ->preload(),
                            ])->columnSpanFull()->columns(2),

                    ])->columnSpanFull()->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('CARGA FAMILIAR')
            ->description('Lista de familiares afiliados')
            ->recordTitleAttribute('affiliation_id')
            ->columns([
                TextInputColumn::make('full_name')
                    ->label('Nombre y Apellidos'),
                TextInputColumn::make('nro_identificacion')
                    ->label('Nro Identificacion'),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento'),
                TextColumn::make('age')
                    ->label('Edad'),
                TextColumn::make('sex')
                    ->label('Genero'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
                TextColumn::make('address')
                    ->label('Direccion Completa'),
                TextInputColumn::make('phone')
                    ->label('Numero de Telefono'),
                TextInputColumn::make('email')
                    ->label('Correo Electronico'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
                    
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge(),
                TextColumn::make('ageRange.range')
                    ->suffix(' Años')
                    ->label('Rango de Edad')
                    ->badge(),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->badge(),  
                TextColumn::make('status')
                    ->color(function (string $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                            default => 'primary',
                        };
                    })
                    ->badge()
                    ->label('Estatus')
            ])
            ->filters([
                //
            ])
            ->recordActions([
                DeleteAction::make()
                    ->label('Dar de Baja')
                    ->icon('heroicon-s-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
                    
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Familiar')
                    ->icon('heroicon-s-user-plus')
                    //Actualizo el total de familiarles en la afiliacion
                    ->after(function (array $data) {
                        $record = $this->getOwnerRecord();
                        if($record->payment_frequency == 'ANUAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + $data['fee'];
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                        if( $record->payment_frequency == 'SEMESTRAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + ($data['fee'] / 2);
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                        if( $record->payment_frequency == 'TRIMESTRAL'){
                            $record->fee_anual = $record->fee_anual + $data['fee'];
                            $record->total_amount = $record->total_amount + ($data['fee'] / 4);
                            $record->family_members = $record->family_members + 1;
                            $record->save();
                            return;
                        }
                    }),
            ]);
    }
}