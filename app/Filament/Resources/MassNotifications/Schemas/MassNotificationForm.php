<?php

namespace App\Filament\Resources\MassNotifications\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Wizard\Step;

class MassNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Informacion Incial')
                        ->schema([
                            Section::make('Status')
                                ->heading('Informacion Previa')
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Titulo de la notificación')
                                        ->required(),
                                    Textarea::make('content')
                                        ->label('Contenido de la notificación(copy)')
                                        ->required()
                                        ->columnSpanFull(),
                                ])->columnSpanFull()->columns(2),
                        ]),
                    Step::make('Carga de Image')
                        ->schema([
                            Section::make('image')
                                ->heading('Imagen')
                                ->schema([
                                    FileUpload::make('image')
                                        ->image(),
                                ])
                        ]),
                ])
                ->submitAction(new HtmlString(Blade::render(<<<BLADE
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        Crear Notificación
                    </x-filament::button>
                BLADE)))
                ->columnSpanFull()
            ]);
    }
}