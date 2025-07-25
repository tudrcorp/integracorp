<?php

namespace App\Filament\Agents\Resources\CorporateQuoteRequests\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Validation\Rules\File;
use Filament\Tables\Columns\TextColumn;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Agents\Resources\CorporateQuoteRequests\CorporateQuoteRequestResource;
use BackedEnum;

class DetailsDataRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsData';

    protected static ?string $title = 'POBLACIÓN';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-plus';

    public function table(Table $table): Table
    {
        return $table
        ->description('Detalle de los afiliados')
            ->columns([
                TextColumn::make('first_name')
                    ->label('Nombre')
                    ->searchable(),
                TextColumn::make('last_name')
                    ->label('Apellido')
                    ->searchable(),
                TextColumn::make('nro_identificacion')
                    ->label('Cédula de Identidad')
                    ->searchable(),
                TextColumn::make('birth_date')
                    ->label('Fecha de nacimiento')
                    ->searchable(),
                TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable(),
                TextColumn::make('condition_medical')
                    ->label('Condición Médica')
                    ->searchable(),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso')
                    ->searchable(),
                TextColumn::make('position_company')
                    ->label('Cargo')
                    ->suffix(' años')
                    ->searchable(),

            ])
            ->headerActions([

            ]);
    }
}