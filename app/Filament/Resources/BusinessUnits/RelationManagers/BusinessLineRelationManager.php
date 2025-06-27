<?php

namespace App\Filament\Resources\BusinessUnits\RelationManagers;

use Filament\Tables\Table;
use App\Models\BusinessLine;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\BusinessUnits\BusinessUnitResource;

class BusinessLineRelationManager extends RelationManager
{
    protected static string $relationship = 'businessLine';


    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
            Section::make('LÍNEA DE NEGOCIO')
                ->description('Formulario para el registro de la línea de negocio. Campo Requerido(*)')
                ->icon('heroicon-o-arrow-trending-up')
                ->schema([
                    Grid::make(3)->schema([
                        TextInput::make('code')
                            ->label('Código')
                            ->prefixIcon('heroicon-m-clipboard-document-check')
                            ->default(function () {
                                if (BusinessLine::max('id') == null) {
                                    $parte_entera = 0;
                                } else {
                                    $parte_entera = BusinessLine::max('id');
                                }
                                return 'TDEC-LN-000' . $parte_entera + 1;
                            })
                            ->required()
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255),
                    ]),
                    TextInput::make('definition')
                        ->label('Definición')
                        ->prefixIcon('heroicon-m-pencil')
                        ->afterStateUpdated(function (Set $set, $state) {
                            $set('definition', strtoupper($state));
                        })
                        ->live(onBlur: true)
                        ->required()
                        ->maxLength(255),
                    Select::make('business_unit_id')
                        ->label('Unidad de Negocio')
                        ->relationship('businessUnit', 'definition')
                        ->prefixIcon('heroicon-m-pencil')
                        ->preload()
                        ->required(),
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
                ])->columns(3),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
        ->heading('LÍNEAS DE SERVICIO')
            ->description('Lista de líneas de servicio asociadas a la unidad de negocio')
            ->recordTitleAttribute('business_unit_id')
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('definition')
                    ->label('Definición')
                    ->badge()
                    ->color('verde')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (mixed $state): string {
                        return match ($state) {
                            'ACTIVO' => 'success',
                            'INACTIVO' => 'danger',
                        };
                    })
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_by')
                    ->label('Creado Por:')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('created_at')
                    ->label('Fecha de Creación')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Fecha de Actualización')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Crear Línea de servicio')
                    ->icon('heroicon-o-plus'),
            ]);
    }
}