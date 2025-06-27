<?php

namespace App\Filament\Resources\Affiliations\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Resources\Affiliations\AffiliationResource;
use BackedEnum;

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
                            ->label('Nombre completo'),
                        TextInput::make('nro_identificacion')
                            ->label('C.I.')
                            ->numeric(),
                        Select::make('sex')
                            ->label('Genero')
                            ->options([
                                'MASCULINO' => 'MASCULINO',
                                'FEMENINO' => 'FEMENINO',
                            ]),
                        DatePicker::make('birth_date')
                            ->label('Fecha de Nacimiento')
                            ->format('d-m-Y'),
                        Select::make('relationship')
                            ->label('Parentesco')
                            ->options([
                                'MADRE'     => 'MADRE',
                                'PADRE'     => 'PADRE',
                                'ESPOSA'    => 'ESPOSA',
                                'ESPOSO'    => 'ESPOSO',
                                'HIJO'      => 'HIJO',
                                'HIJA'      => 'HIJA',
                            ]),

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
                TextColumn::make('full_name')
                    ->label('Nombre y Apellidos'),
                TextColumn::make('nro_identificacion')
                    ->label('Nro Identificacion'),
                TextColumn::make('birth_date')
                    ->label('Fecha de Nacimiento'),
                TextColumn::make('sex')
                    ->label('Genero'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Familiar')
                    ->icon('heroicon-s-user-plus')
                    //Actualizo el total de familiarles en la afiliacion
                    ->after(function () {
                        $record = $this->getOwnerRecord();
                        $record->family_members = $record->family_members + 1;
                        $record->save();
                    }),
            ]);
    }
}