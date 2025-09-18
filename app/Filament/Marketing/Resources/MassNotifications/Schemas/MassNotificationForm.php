<?php

namespace App\Filament\Marketing\Resources\MassNotifications\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Illuminate\Support\Facades\Blade;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Wizard;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Forms\Components\CheckboxList;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class MassNotificationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Wizard::make([
                    Step::make('Información Inicial')
                        ->schema([
                            Section::make('Status')
                                ->heading('Información Previa')
                                ->schema([
                                    TextInput::make('title')
                                        ->label('Titulo de la notificación')
                                        ->helperText('Este campo es necesario para identificar la notificación y ser visible para asociarla a los destinatarios.')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'El titulo de la notificación es obligatorio.',
                                        ]),
                                    Select::make('channels')
                                        ->options([
                                            'whatsapp' => 'WhatsApp',
                                            'email' => 'Correo electrónico',
                                            'sms' => 'SMS',
                                        ])
                                        ->helperText('Selecciona el canal de notificación. Este campo es de selección multiple, puedes seleccionar mas de un canal.')
                                        ->required()
                                        ->validationMessages([
                                            'required' => 'Debe seleccionar al menos un canal.',
                                        ])
                                        ->label('Canal de notificación')
                                        ->multiple(),
                                    DatePicker::make('date_programmed')
                                        ->label('Fecha programada')
                                        ->helperText('Selecciona la fecha y hora en la que deseas que se envíe la notificación. Esta sera enviada por el sistema.'),
                                    Fieldset::make('Tipo de Envio')
                                        ->schema([
                                            Radio::make('type')
                                                ->options([
                                                    'image' => 'Imagen',
                                                    'video' => 'Video',
                                                    'url' => 'URL',
                                                ])
                                                ->descriptions([
                                                    'image' => 'La imagen debe ser de 16MB de tamaño. Formatos permitidos: png, jpg, jpeg, webp. Si la imagen es mayor a 16MB no sera cargado correctamente.',
                                                    'video' => 'El video debe ser de 32MB de tamaño. Formatos permitidos: mp4, 3gp , mov. Si el video es mayor a 32MB no sera cargado correctamente.',
                                                    'url' => 'El URL debe tener como prefijo http:// ó https://.',
                                                ])
                                                ->helperText('Selecciona el estado de la notificación.')
                                                ->required()
                                                ->label('Status de la notificación'),
                                        ])->columnSpanFull(),
                                    Fieldset::make('Encabezado de la notificación')
                                        ->schema([
                                            TextInput::make('header_title')
                                                ->label('Titulo de la notificación')
                                                ->helperText('En este campo va el titulo del encabezado, si la notificación no es personalizada dejar en blanco. Ejemplo: "Hola, {nombre}", donde {nombre} será reemplazado por el nombre del usuario.'),
                                        ])->columnSpanFull(),
                                    Fieldset::make('Imagen de la notificación')
                                        ->schema([
                                            FileUpload::make('file')
                                                ->helperText('El tamaño máximo de la imagen debe ser 16MB. Si la imagen es mayor a 16MB no sera cargado correctamente.'),
                                        ])->columnSpanFull(),
                                    Fieldset::make('Contenido de la notificación')
                                        ->schema([
                                            Textarea::make('content')
                                                ->label('Copy:')
                                                ->columnSpanFull()
                                                ->minLength(2)
                                                ->maxLength(1024)
                                                ->helperText('Aquí puedes escribir el contenido de la notificación que se enviará a los usuarios. Puedes incluir texto y emojis, enlaces y otros elementos según sea necesario. MÁXIMO 1024 CARACTERES')
                                                ->required()
                                                ->validationMessages([
                                                    'required' => 'El contenido de la notificación es obligatorio.',
                                                    'max' => 'El contenido de la notificación no puede exceder los 1024 caracteres.',
                                                    'min' => 'El contenido de la notificación debe tener al menos 2 caracteres.',
                                                ]),
                                        
                                    ])->columnSpanFull(),
                                ])->columnSpanFull()->columns(3),
                        ])
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