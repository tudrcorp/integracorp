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
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Components\Utilities\Get;

class BirthdayNotificationForm
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
                                        
                                        ->validationMessages([
                                            'required' => 'El titulo de la notificación es obligatorio.',
                                        ])
                                        ->afterStateUpdatedJs(<<<'JS'
                                            $set('title', $state.toUpperCase());
                                        JS),
                                    Select::make('channels')
                                        ->options([
                                            'whatsapp' => 'WHATSAPP',
                                            'email' => 'CORREO ELECTRÓNICO',
                                            'sms' => 'SMS',
                                        ])
                                        ->required()
                                        ->helperText('Selecciona el canal de notificación. Este campo es de selección multiple, puedes seleccionar mas de un canal.')
                                        ->validationMessages([
                                            'required' => 'Debe seleccionar al menos un canal.',
                                        ])
                                        ->label('Canal de notificación')
                                        ->multiple(),
                                    Select::make('data_type')
                                        ->options([
                                            'rrhh_colaboradors'    => 'COLABORADORES/EMPLEADOS',
                                            'suppliers'            => 'PROVEEDORES',
                                            'affiliates'           => 'AFILIADOS INDIVIDUALES',
                                            'affiliate_corporates' => 'AFILIADOS CORPORATIVOS',
                                            'users'                => 'USUARIOS',
                                            'capemiacs'            => 'CAPEMIAC',
                                            'agents'               => 'AGENTES',
                                        ])
                                        ->required()
                                        ->searchable()
                                        ->label('Destinatarios')
                                        ->helperText('Selecciona los destinatarios de la notificación.'),
                                    Fieldset::make()
                                        ->schema([
                                            Radio::make('type')
                                                ->label('Tipo de archivo:')
                                                ->required()
                                                ->options([
                                                    'image' => 'IMAGEN',
                                                    'video' => 'VIDEO',
                                                    'url' => 'URL',
                                                ])
                                                ->descriptions([
                                                    'image' => 'La imagen debe ser de 16MB de tamaño. Formatos permitidos: png, jpg, jpeg, webp. Si la imagen es mayor a 16MB no sera cargado correctamente.',
                                                    'video' => 'El video debe ser de 32MB de tamaño. Formatos permitidos: mp4, 3gp , mov. Si el video es mayor a 32MB no sera cargado correctamente.',
                                                    'url' => 'El URL debe tener como prefijo http:// ó https://. Ejemplo: http://www.pagina.com, https://www.pagina.com, etc. El URL debe ser colocado en el contidenido de la notificación(copy).',
                                                ])
                                                
                                                ->live(),
                                        ])->columnSpanFull(),
                                    Fieldset::make('Encabezado de la notificación')
                                        ->schema([
                                            TextInput::make('header_title')
                                                ->label('Titulo de la notificación')
                                                ->validationMessages([
                                                    'required' => 'El titulo de la notificación es obligatorio.',
                                                ])
                                                ->helperText('Aquí puedes escribir el titulo de la notificación que se enviará a los usuarios. Ejemplo: Estimado(a):, Sr(a)., Amigo(a), etc.'),
                                        ])->columnSpanFull(),
                                    Fieldset::make('Archivo')
                                        ->hidden(fn(Get $get) => $get('type') == 'url')
                                        ->schema([
                                            FileUpload::make('file')
                                                ->label('Archivo de la notificación (Imagen o Video)')
                                                ->required()
                                                ->directory('birthday-notifications')
                                                ->validationMessages([
                                                    'required' => 'La imagen es obligatoria.',
                                                ])
                                                ->helperText('El tamaño máximo de la imagen debe ser 16MB. Si la imagen es mayor a 16MB no sera cargado correctamente.')
                                        ])->columnSpanFull(),
                                    Fieldset::make('Contenido de la notificación')
                                        ->schema([
                                            Textarea::make('content')
                                                ->label('Copy:')
                                                ->columnSpanFull()
                                                ->minLength(2)
                                                ->maxLength(1024)
                                                ->helperText('Aquí puedes escribir el contenido de la notificación que se enviará a los usuarios. Puedes incluir texto y emojis, enlaces y otros elementos según sea necesario. MÁXIMO 1024 CARACTERES')
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