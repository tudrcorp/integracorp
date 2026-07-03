<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\PlanGeneratorImages\Schemas;

use App\Support\PlanGenerators\PlanGeneratorImageGallery;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class PlanGeneratorImageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Imagen')
                    ->description('Las imágenes cargadas aquí también quedan disponibles en la galería del cuerpo de cotización.')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255),
                        FileUpload::make('image_path')
                            ->label('Archivo')
                            ->image()
                            ->disk('public')
                            ->directory('plan-generator-quotation')
                            ->visibility('public')
                            ->imageEditor()
                            ->required()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if (is_string($state) && filled($state)) {
                                    $set('name', PlanGeneratorImageGallery::nameFromPath($state));
                                }
                            }),
                        TextInput::make('created_by')
                            ->label('Cargada por')
                            ->default(fn (): ?string => Auth::user()?->name)
                            ->disabled()
                            ->dehydrated(),
                    ]),
            ]);
    }
}
