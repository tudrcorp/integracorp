<?php

namespace App\Filament\Master\Resources\Affiliations\RelationManagers;

use Filament\Tables\Table;
use Filament\Schemas\Schema;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\ImageColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Master\Resources\Affiliations\AffiliationResource;
use BackedEnum;

class AffiliatesRelationManager extends RelationManager
{
    protected static string $relationship = 'affiliates';

    protected static ?string $title = 'Familiares asociados';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-s-user';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('FAMILIAR')
                    ->description('Fomulario de familiar.')
                    ->icon('heroicon-s-user')
                    ->schema([
                        Repeater::make('affiliates')
                            ->label('Datos del afiliado:')
                            ->schema([
                                TextInput::make('full_name')
                                    ->label('Nombre completo')
                                    ->maxLength(255),
                                TextInput::make('nro_identificacion')
                                    ->label('C.I.')
                                    ->numeric(),
                                Select::make('sex')
                                    ->label('Sexo')
                                    ->options([
                                        'MASCULINO' => 'MASCULINO',
                                        'FEMENINO' => 'FEMENINO',
                                    ]),
                                DatePicker::make('birth_date')
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
                            ])
                            // ->defaultItems(6)
                            ->addActionLabel('Agregar afiliado')
                            ->columns(5)
                            ->columnSpan('full'),
                    ])->columnSpanFull(),
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
                TextColumn::make('age')
                    ->label('Edad')
                    ->suffix(' años'),
                TextColumn::make('sex')
                    ->label('Genero'),
                TextColumn::make('relationship')
                    ->label('Parentesco'),
                ImageColumn::make('document')
                    ->alignCenter()
                    ->imageHeight(80)
                    ->label('Documento de Identidad'),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Agregar Familiar')
                    ->icon('heroicon-s-user-plus')
                    ->modalHeading('Agregar Familiar')
                    ->action(function (array $data) {
                        try {
                            for ($i = 0; $i < count($data['affiliates']); $i++) {
                                $this->getOwnerRecord()->affiliates()->create([
                                    'affiliation_id' => $this->getOwnerRecord()->id,
                                    'full_name' => $data['affiliates'][$i]['full_name'],
                                    'nro_identificacion' => $data['affiliates'][$i]['nro_identificacion'],
                                    'birth_date' => $data['affiliates'][$i]['birth_date'],
                                    'sex' => $data['affiliates'][$i]['sex'],
                                    'relationship' => $data['affiliates'][$i]['relationship'],
                                ]);
                            }
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('EXCEPTION')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })

            ])
            ->striped()
            ->defaultSort('created_at', 'desc');
    }
}