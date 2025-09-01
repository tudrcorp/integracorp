<?php

namespace App\Filament\General\Resources\DownloadZones\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DownloadZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('zone_id')
                    ->required()
                    ->numeric(),
                TextInput::make('document')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('ACTIVO'),
                FileUpload::make('image_icon')
                    ->image(),
                TextInput::make('description'),
            ]);
    }
}
