<?php

namespace App\Filament\Resources\DownloadZones\Schemas;

use App\Models\Zone;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;

class DownloadZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General')
                    ->schema([
                        Grid::make()->schema([
                            FileUpload::make('document')
                                ->label('Documento')
                                ->required(),
                            FileUpload::make('image_icon')
                                ->label('previsualizacion')
                                ->required(),
                        ])->columnSpanFull()->columns(2),
                        Select::make('zone_id')
                            ->options(Zone::all()->pluck('zone', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('description')
                            ->label('Descripcion')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->required()
                            ->maxLength(255)
                            ->default('ACTIVO'),
                    ])->columnSpanFull()->columns(3),
            ]);
    }
}