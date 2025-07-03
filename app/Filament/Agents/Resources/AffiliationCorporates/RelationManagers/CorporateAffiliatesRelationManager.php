<?php

namespace App\Filament\Agents\Resources\AffiliationCorporates\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Validation\Rules\File;
use App\Http\Controllers\LogController;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\NotificationController;
use App\Filament\Imports\AffiliateCorporateImporter;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Agents\Resources\AffiliationCorporates\AffiliationCorporateResource;
use BackedEnum;

class CorporateAffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'corporateAffiliates';

    protected static ?string $title = 'Poblacion';

    protected static string|BackedEnum|null $icon = 'heroicon-s-user-group';


    public function table(Table $table): Table
    {
        return $table
            ->heading('EMPLEADOS ASOCIADOS')
            ->description('Lista de empleados afiliados')
            ->recordTitleAttribute('affiliation_corporate_id')
            ->columns([
                TextColumn::make('last_name')
                    ->label('Apellido'),
                TextColumn::make('first_name')
                    ->label('Nombre'),
                TextColumn::make('nro_identificacion')
                    ->label('C.I.'),
                TextColumn::make('email')
                    ->label('Email'),
                TextColumn::make('age')
                    ->label('Edad'),
                TextColumn::make('sex')
                    ->label('Sexo'),
                TextColumn::make('phone')
                    ->label('Telefono'),
                TextColumn::make('condition_medical')
                    ->label('Condicion Medica'),
                TextColumn::make('initial_date')
                    ->label('Fecha de Ingreso'),
                TextColumn::make('address')
                    ->label('Direccion'),
                TextColumn::make('full_name_emergency')
                    ->label('Contacto de Emergencia'),
                TextColumn::make('phone_emergency')
                    ->label('Telefono de Emergencia'),
            ])
            ->striped();
    }
}