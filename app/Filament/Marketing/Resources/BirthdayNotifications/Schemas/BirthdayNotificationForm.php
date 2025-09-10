<?php

namespace App\Filament\Marketing\Resources\BirthdayNotifications\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class BirthdayNotificationForm
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
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'El titulo de la notificación es obligatorio.',
                                        ]),
                                    Select::make('data_type')
                                        ->options([
                                            'users'         => 'Usuarios',
                                            'affiliations'  => 'Afilliados Individuales',
                                            'capemiacs'     => 'CAPEMIAC',
                                            'agents'        => 'Agentes',
                                        ])
                                        ->required()
                                        ->multiple()
                                        ->searchable()
                                        ->label('Destinatarios')
                                        ->helperText('Selecciona los destinatarios de la notificación.'),
                                    Textarea::make('content')
                                        ->label('Contenido de la notificación(copy)')
                                        ->columnSpanFull()
                                        ->minLength(2)
                                        ->maxLength(1024)
                                        ->helperText('Aquí puedes escribir el contenido de la notificación que se enviará a los usuarios. Puedes incluir texto y emojis, enlaces y otros elementos según sea necesario. MÁXIMO 1024 CARACTERES')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'El contenido de la notificación es obligatorio.',
                                            'max' => 'El contenido de la notificación no puede exceder los 1024 caracteres.',
                                            'min' => 'El contenido de la notificación debe tener al menos 2 caracteres.',
                                        ])
                                ])->columnSpanFull()->columns(4),
                        ]),
                    Step::make('Carga de Image o Video')
                        ->schema([
                            Section::make('image')
                                ->heading('Imagen')
                                ->schema([
                                    Radio::make('is_personalized')
                                        ->label('¿Que tipo de archivo desea cargar?')
                                        ->options([
                                            'image' => 'Imagen (jpg, jpeg, gif, png, webp, bmp)',
                                            'video' => 'Video (MP4)',
                                            'link' => 'Link (URL externa)',
                                        ])
                                        ->inline()
                                        ->live()
                                        ->label('¿La notificación tendrá un encabezado personalizado?')
                                        ->required()
                                        ->columnSpanFull(),
                                    FileUpload::make('image')
                                        ->image()
                                        ->visibility('public')
                                        ->hidden(fn(Get $get) => $get('is_personalized') != 'image')
                                        ->helperText('El tamaño máximo de la imagen debe ser 16MB. Si la imagen es mayor a 16MB no sera cargado correctamente.'),
                                    FileUpload::make('video')
                                        ->previewable(false)
                                        ->visibility('public')
                                        ->hidden(fn(Get $get) => $get('is_personalized') != 'video')
                                        ->helperText('El tamaño máximo del video debe ser 32MB. Si el video es mayor a 32MB no sera cargado correctamente.'),
                                    TextInput::make('link')
                                        ->label('Link Externo')
                                        ->placeholder('https://examplo.com')
                                        ->url()
                                        ->validationMessages([
                                            'url' => 'El valor ingresado no es una URL válida. Asegúrate de ingresar una URL que comience con "https://".',
                                        ])
                                        ->hidden(fn(Get $get) => $get('is_personalized') != 'link')
                                        ->helperText('En este campo puedes ingresar una URL externa que deseas que los usuarios puedan visitar al interactuar con la notificación. Asegúrate de ingresar una URL válida, comenzando con "https://".'),
                                ])->columnSpanFull()->columns(3),
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