<?php

namespace App\Filament\Business\Resources\DownloadZones\Schemas;

use App\Models\Zone;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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
                                ->helperText('Este archivo sera descargado por los agentes y agencias. Formatos aceptados: PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX, TXT, JPG, PNG, GIF, SVG, ZIP, RAR')
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
                                ->helperText('Esta imagen se mostrara como icono o imagen referencial del documento')
                                ->image()
                                ->disk('public')
                                ->directory('download-zone')
                                ->required(),
                        ])->columnSpanFull()->columns(4),
                        Select::make('zone_id')
                            ->label('Carpeta')
                            ->helperText('Selecciona la carpeta donde se guardara el documento')
                            ->options(Zone::all()->pluck('zone', 'id'))
                            ->searchable()
                            ->preload()
                            ->required(),
                        TextInput::make('description')
                            ->label('Descripcion')
                            ->helperText('Breve descripcion del documento visible para los agentes y agencias')
                            ->required()
                            ->maxLength(255)
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('description', $state.toUpperCase());
                            JS),
                        TextInput::make('status')
                            ->label('Estatus')
                            ->required()
                            ->maxLength(255)
                            ->default('ACTIVO'),
                    ])->columnSpanFull()->columns(3),
            ]);
    }
}
