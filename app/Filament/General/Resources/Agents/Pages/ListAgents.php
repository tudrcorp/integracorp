<?php

namespace App\Filament\General\Resources\Agents\Pages;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Utilities\Get;
use App\Http\Controllers\NotificationController;
use App\Filament\General\Resources\Agents\AgentResource;

class ListAgents extends ListRecords
{
    protected static string $resource = AgentResource::class;

    protected static ?string $title = 'Table de cotizaciones individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar nuevo agente')
                ->icon('heroicon-s-user-plus'),
            Action::make('send_link')
                ->label('Enviar link')
                ->icon('heroicon-m-link')
                ->requiresConfirmation()
                ->form([
                    Section::make()
                        ->heading('Informacion')
                        ->description('El link puede sera enviado por email y/o telefono!')
                        ->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->email(),
                            Grid::make(2)->schema([
                                Select::make('country_code')
                                    ->label('Código de país')
                                    ->options([
                                        '+1'   => '🇺🇸 +1 (Estados Unidos)',
                                        '+44'  => '🇬🇧 +44 (Reino Unido)',
                                        '+49'  => '🇩🇪 +49 (Alemania)',
                                        '+33'  => '🇫🇷 +33 (Francia)',
                                        '+34'  => '🇪🇸 +34 (España)',
                                        '+39'  => '🇮🇹 +39 (Italia)',
                                        '+7'   => '🇷🇺 +7 (Rusia)',
                                        '+55'  => '🇧🇷 +55 (Brasil)',
                                        '+91'  => '🇮🇳 +91 (India)',
                                        '+86'  => '🇨🇳 +86 (China)',
                                        '+81'  => '🇯🇵 +81 (Japón)',
                                        '+82'  => '🇰🇷 +82 (Corea del Sur)',
                                        '+52'  => '🇲🇽 +52 (México)',
                                        '+58'  => '🇻🇪 +58 (Venezuela)',
                                        '+57'  => '🇨🇴 +57 (Colombia)',
                                        '+54'  => '🇦🇷 +54 (Argentina)',
                                        '+56'  => '🇨🇱 +56 (Chile)',
                                        '+51'  => '🇵🇪 +51 (Perú)',
                                        '+502' => '🇬🇹 +502 (Guatemala)',
                                        '+503' => '🇸🇻 +503 (El Salvador)',
                                        '+504' => '🇭🇳 +504 (Honduras)',
                                        '+505' => '🇳🇮 +505 (Nicaragua)',
                                        '+506' => '🇨🇷 +506 (Costa Rica)',
                                        '+507' => '🇵🇦 +507 (Panamá)',
                                        '+593' => '🇪🇨 +593 (Ecuador)',
                                        '+592' => '🇬🇾 +592 (Guyana)',
                                        '+591' => '🇧🇴 +591 (Bolivia)',
                                        '+598' => '🇺🇾 +598 (Uruguay)',
                                        '+20'  => '🇪🇬 +20 (Egipto)',
                                        '+27'  => '🇿🇦 +27 (Sudáfrica)',
                                        '+234' => '🇳🇬 +234 (Nigeria)',
                                        '+212' => '🇲🇦 +212 (Marruecos)',
                                        '+971' => '🇦🇪 +971 (Emiratos Árabes)',
                                        '+92'  => '🇵🇰 +92 (Pakistán)',
                                        '+880' => '🇧🇩 +880 (Bangladesh)',
                                        '+62'  => '🇮🇩 +62 (Indonesia)',
                                        '+63'  => '🇵🇭 +63 (Filipinas)',
                                        '+66'  => '🇹🇭 +66 (Tailandia)',
                                        '+60'  => '🇲🇾 +60 (Malasia)',
                                        '+65'  => '🇸🇬 +65 (Singapur)',
                                        '+61'  => '🇦🇺 +61 (Australia)',
                                        '+64'  => '🇳🇿 +64 (Nueva Zelanda)',
                                        '+90'  => '🇹🇷 +90 (Turquía)',
                                        '+375' => '🇧🇾 +375 (Bielorrusia)',
                                        '+372' => '🇪🇪 +372 (Estonia)',
                                        '+371' => '🇱🇻 +371 (Letonia)',
                                        '+370' => '🇱🇹 +370 (Lituania)',
                                        '+48'  => '🇵🇱 +48 (Polonia)',
                                        '+40'  => '🇷🇴 +40 (Rumania)',
                                        '+46'  => '🇸🇪 +46 (Suecia)',
                                        '+47'  => '🇳🇴 +47 (Noruega)',
                                        '+45'  => '🇩🇰 +45 (Dinamarca)',
                                        '+41'  => '🇨🇭 +41 (Suiza)',
                                        '+43'  => '🇦🇹 +43 (Austria)',
                                        '+31'  => '🇳🇱 +31 (Países Bajos)',
                                        '+32'  => '🇧🇪 +32 (Bélgica)',
                                        '+353' => '🇮🇪 +353 (Irlanda)',
                                        '+375' => '🇧🇾 +375 (Bielorrusia)',
                                        '+380' => '🇺🇦 +380 (Ucrania)',
                                        '+994' => '🇦🇿 +994 (Azerbaiyán)',
                                        '+995' => '🇬🇪 +995 (Georgia)',
                                        '+976' => '🇲🇳 +976 (Mongolia)',
                                        '+998' => '🇺🇿 +998 (Uzbekistán)',
                                        '+84'  => '🇻🇳 +84 (Vietnam)',
                                        '+856' => '🇱🇦 +856 (Laos)',
                                        '+374' => '🇦🇲 +374 (Armenia)',
                                        '+965' => '🇰🇼 +965 (Kuwait)',
                                        '+966' => '🇸🇦 +966 (Arabia Saudita)',
                                        '+972' => '🇮🇱 +972 (Israel)',
                                        '+963' => '🇸🇾 +963 (Siria)',
                                        '+961' => '🇱🇧 +961 (Líbano)',
                                        '+960' => '🇲🇻 +960 (Maldivas)',
                                        '+992' => '🇹🇯 +992 (Tayikistán)',
                                    ])
                                    ->searchable()
                                    ->default('+58')
                                    ->required()
                                    ->live(onBlur: true)
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ]),
                                TextInput::make('phone')
                                    ->prefixIcon('heroicon-s-phone')
                                    ->tel()
                                    ->label('Número de teléfono')
                                    ->required()
                                    ->validationMessages([
                                        'required'  => 'Campo Requerido',
                                    ])
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function ($state, callable $set, Get $get) {
                                        $countryCode = $get('country_code');
                                        if ($countryCode) {
                                            $cleanNumber = ltrim(preg_replace('/[^0-9]/', '', $state), '0');
                                            $set('phone', $countryCode . $cleanNumber);
                                        }
                                    }),

                            ])
                        ])
                ])
                ->action(function (array $data) {

                    $link           = Auth::user()->link_agency;
                    $sendEmail      = NotificationController::send_email_agency_register($link, $data['email']);
                    $sendWp         = NotificationController::send_link_agency_register_wp($link, $data['phone']);

                    if ($sendEmail == true) {
                        LogController::log(Auth::user()->id, 'ENVIAO DE LINK PARA REGISTRO DE AGENCIA', 'ListAgencies::getHeaderActions:action()', 'NOTIFICACION ENVIADA');
                        Notification::make()
                            ->title('NOTIFICACION ENVIADA')
                            ->body('La notificación via email fue enviada con exito.')
                            ->icon('heroicon-c-shield-check')
                            ->color('success')
                            ->send();
                    } else {
                        Notification::make()
                            ->title('ENVIO FALLIDO')
                            ->body('La notificación via email NO fue enviada con exito.')
                            ->icon('heroicon-c-shield-check')
                            ->color('danger')
                            ->send();
                    }

                    if ($sendWp['success']) {
                        LogController::log(Auth::user()->id, 'ENVIAO DE LINK PARA REGISTRO DE AGENCIA', 'ListAgencies::getHeaderActions:action()', 'NOTIFICACION ENVIADA');
                        Notification::make()
                            ->title('NOTIFICACION ENVIADA')
                            ->body('La notificación via whatsapp fue enviada con exito.')
                            ->icon('heroicon-c-shield-check')
                            ->color('success')
                            ->send();
                    } else {
                        Notification::make()
                            ->title('ENVIO FALLIDO')
                            ->body('La notificación via email NO fue enviada con exito.')
                            ->icon('heroicon-c-shield-check')
                            ->color('danger')
                            ->send();
                    }
                })
        ];
    }
}