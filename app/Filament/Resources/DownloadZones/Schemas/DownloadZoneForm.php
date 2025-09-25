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
                                    ->disk('public')
                                    ->directory('download-zone')
                                    ->preserveFilenames()
                                    ->maxSize(10240) // 10 MB
                                    ->acceptedFileTypes([
                                        'application/pdf',
                                        'application/msword',
                                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                        'application/vnd.ms-excel',
                                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                        'application/vnd.ms-powerpoint',
                                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                        'text/plain',
                                        'image/jpeg',
                                        'image/png',
                                        'image/gif',
                                        'image/svg+xml',
                                        'application/zip',
                                        'application/x-rar-compressed',
                                    ])
                                 ->visibility('public')
                                ->required(),
                            FileUpload::make('image_icon')
                                ->label('previsualizacion')
                                ->image()
                                ->disk('public')
                                ->directory('download-zone')
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