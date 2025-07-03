<?php

namespace App\Providers\Filament;

use Filament\Panel;
use App\Models\Agency;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationGroup;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Controllers\NotificationController;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

class MasterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('master')
            ->path('master')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => '#038C4C',
            ])
            ->brandLogo(asset('image/logo_new.png'))
            ->brandLogoHeight('2.8rem')
            ->favicon(asset('image/favicon.png'))
            ->discoverResources(in: app_path('Filament/Master/Resources'), for: 'App\Filament\Master\Resources')
            ->discoverPages(in: app_path('Filament/Master/Pages'), for: 'App\Filament\Master\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Master/Widgets'), for: 'App\Filament\Master\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->databaseNotifications()
            ->databaseTransactions()
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-s-user-group'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-s-swatch'),
                NavigationGroup::make()
                    ->label('ORGANIZACIÓN')
                    ->icon('heroicon-m-share'),
                NavigationGroup::make()
                    ->label('VENTAS')
                    ->icon('heroicon-s-calculator'),
            ])
            ->breadcrumbs(false)
            ->registerErrorNotification(
                title: 'Registro No Encontrado',
                body: 'El registro que intenta consultar no existe.',
                statusCode: 404,
            )
            ->userMenuItems([
                // // ...
                Action::make('send_link_agencies')
                    ->label('Link Agencias')
                    ->icon('heroicon-c-building-library') //heroicon-c-building-library
                    ->modalHeading('Envio de link para registro externo')
                    ->modalIcon('heroicon-m-link')
                    ->modalWidth(Width::ExtraLarge)
                    ->form([
                        Section::make()
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
                                        ->default('+58'),
                                    TextInput::make('phone')
                                        ->prefixIcon('heroicon-s-phone')
                                        ->tel()
                                        ->label('Número de teléfono')
                                ])
                            ])
                    ])
                    ->action(function (array $data) {

                        try {

                            if ($data['phone'] == null && $data['email'] == null) {
                                Notification::make()
                                    ->title('NOTIFICACION')
                                    ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                    ->icon('heroicon-c-shield-exclamation')
                                    ->color('warning')
                                    ->send();

                                return false;
                            }

                            if ($data['email'] != null) {

                                $link = Auth::user()->link_agency;
                                $sendEmail  = NotificationController::send_email_agency_register($link, $data['email']);
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
                            }

                            if ($data['phone'] != null) {

                                $link = Auth::user()->link_agency;
                                $phone = $data['country_code'] . ltrim(preg_replace('/[^0-9]/', '', $data['phone']), '0');
                                $sendWp     = NotificationController::send_link_agency_register_wp($link, $phone);
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
                            }
                        } catch (\Throwable $th) {
                            Notification::make()
                                ->title('ENVIO FALLIDO')
                                ->body($th->getMessage())
                                ->icon('heroicon-c-shield-check')
                                ->color('danger')
                                ->send();
                        }
                    }),
                // // ...
                Action::make('send_link_agents')
                    ->label('Link Agentes')
                    ->icon('heroicon-c-user-plus')
                    ->modalHeading('Envio de link para registro externo')
                    ->modalIcon('heroicon-m-link')
                    ->modalWidth(Width::ExtraLarge)
                    ->form([
                        Section::make()
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
                                        ->default('+58'),
                                    TextInput::make('phone')
                                        ->prefixIcon('heroicon-s-phone')
                                        ->tel()
                                        ->label('Número de teléfono'),
                                ])
                            ])
                    ])
                    ->action(function (array $data) {

                        try {

                            if ($data['phone'] == null && $data['email'] == null) {
                                Notification::make()
                                    ->title('NOTIFICACION')
                                    ->body('La notificacion no pudo ser enviada debido a que no se proporcionaron datos de contacto(Email y/o Teléfono).')
                                    ->icon('heroicon-c-shield-exclamation')
                                    ->color('warning')
                                    ->send();

                                return false;
                            }

                            if ($data['email'] != null) {

                                $link = config('parameters.register_agent');
                                $sendEmail  = NotificationController::send_email_agent_register($link, $data['email']);
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
                            }

                            if ($data['phone'] != null) {

                                $link = config('parameters.register_agent');
                                $phone = $data['country_code'] . ltrim(preg_replace('/[^0-9]/', '', $data['phone']), '0');
                                $sendWp     = NotificationController::send_link_agent_register_wp($link, $phone);
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
                            }
                        } catch (\Throwable $th) {
                            Notification::make()
                                ->title('ENVIO FALLIDO')
                                ->body($th->getMessage())
                                ->icon('heroicon-c-shield-check')
                                ->color('danger')
                                ->send();
                        }
                    })
            ]);
            // ->navigation(false);
    }
}