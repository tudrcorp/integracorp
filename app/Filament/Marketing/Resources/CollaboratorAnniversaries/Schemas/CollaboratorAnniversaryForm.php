<?php

namespace App\Filament\Marketing\Resources\CollaboratorAnniversaries\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class CollaboratorAnniversaryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aniversario del colaborador')
                    ->description('Registre el colaborador y la imagen para celebrar su aniversario')
                    ->icon('heroicon-o-cake')
                    ->schema([
                        Fieldset::make('Datos del aniversario')
                            ->schema([
                                Grid::make(1)
                                    ->schema([
                                        Select::make('rrhh_colaborador_id')
                                            ->label('Colaborador')
                                            ->relationship('rrhhColaborador', 'fullName', fn ($query) => $query->orderBy('fullName'))
                                            ->searchable()
                                            ->preload()
                                            ->required()
                                            ->helperText('Busque y seleccione al colaborador que celebra aniversario'),
                                        FileUpload::make('image')
                                            ->label('Imagen del aniversario')
                                            ->image()
                                            ->directory('collaborator-anniversaries-images')
                                            ->visibility('public')
                                            ->imagePreviewHeight('200')
                                            ->maxSize(2048)
                                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                            ->required()
                                            ->helperText('Formatos: JPG, PNG o WebP. Máximo 2 MB.'),
                                    ])
                                    ->columnSpanFull(),
                            ])
                            ->columnSpanFull(),

                        Hidden::make('created_by')
                            ->default(Auth::user()->name)
                            ->hiddenOn('edit'),
                        Hidden::make('updated_by')
                            ->default(Auth::user()->name)
                            ->hiddenOn('create'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
