<?php

namespace App\Filament\Business\Resources\Helpdesks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HelpdeskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make("Información General")
                ->description("Formulario para crear un nuevo ticket de soporte o requerimiento de sistemas")
                ->schema([
                    Grid::make(3)->schema([
                        FileUpload::make('image')
                        ->directory('helpdesks-images')
                        ->visibility('public')
                        ->image(),
                    ])->columnSpan('full'),
                    Textarea::make('description')
                        ->label('Descripción')
                        ->required(),
                    Select::make('helpdesk_category_id')
                        ->relationship('help_desk_category', 'description')
                        ->label('Categoría')
                        ->required(),
                    Hidden::make('module')
                        ->default('NEGOCIOS'),
                    TextInput::make('status')
                        ->label('Estado')
                        ->required()
                        ->default('abierto'),
                    Hidden::make('created_by')
                        ->default(auth()->user()->id),
                    Textarea::make('observations')
                        ->label('Observaciones'),
                ])->columns(3)->columnSpan('full'),
            ]);
    }
}
