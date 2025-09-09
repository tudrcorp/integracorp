<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

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
                                    DatePicker::make('date_programmed')
                                        ->label('Fecha programada'),
                                    Textarea::make('content')
                                        ->label('Contenido de la notificación(copy)')
                                        ->required()
                                        ->columnSpanFull(),
                                ])->columnSpanFull()->columns(2),
                        ]),
                    Step::make('Definicion del Encabezado')
                        ->schema([
                            Section::make('Status')
                                ->heading('Informacion Previa')
                                ->schema([
                                    Radio::make('is_personalized')
                                        ->options([
                                            'si' => 'Si',
                                            'no' => 'No',
                                        ])
                                        ->inline()
                                        ->live()
                                        ->label('¿La notificación tendrá un encabezado personalizado?')
                                        ->required()
                                        ->columnSpanFull(),
                                    TextInput::make('header_title')
                                        ->label('Titulo de la notificación')
                                        ->helperText('En este campo va el titulo del encabezado, si la notificación no es personalizada dejar en blanco. Ejemplo: "Hola, {nombre}", donde {nombre} será reemplazado por el nombre del usuario.')
                                        ->required()
                                        ->hidden(fn (Get $get) => $get('is_personalized') != 'si'),
                                ])->columnSpanFull()->columns(3),
                        ]),
                    Step::make('Carga de Image')
                        ->schema([
                            Section::make('image')
                                ->heading('Imagen')
                                ->schema([
                                    FileUpload::make('image')
                                        ->image()
                                        ->visibility('public'),
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