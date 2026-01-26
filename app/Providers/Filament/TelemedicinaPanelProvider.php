<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Telemedicina\Pages\EjecutarAccionUsuario;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Http\Middleware\DuplicatedSession;
use App\Models\Agent;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Forms\Components\Toggle;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Component;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class TelemedicinaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('telemedicina')
            ->path('telemedicina')
            ->login()
            ->registration()
            ->passwordReset()
            ->profile()
            ->spa()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/imagotipo.png'))
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => '#005ca9',
            ])
            ->discoverResources(in: app_path('Filament/Telemedicina/Resources'), for: 'App\Filament\Telemedicina\Resources')
            ->discoverPages(in: app_path('Filament/Telemedicina/Pages'), for: 'App\Filament\Telemedicina\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Telemedicina/Widgets'), for: 'App\Filament\Telemedicina\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
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
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->authMiddleware([
                Authenticate::class,
            DuplicatedSession::class,
            ])
            ->registerErrorNotification(
                title: 'ERROR DE EJECUCIÓN',
                body: 'Se produjo un error de ejecución, por favor contacte con el administrador.',
                statusCode: 404,
            )
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('PERFIL DEL DOCTOR')
                    ->icon('healthicons-f-doctor-male')
                    ->color('no-urgente')
                    ->url(function () {
                        if(Auth::user()->doctor_id != NULL){
                            return TelemedicineDoctorResource::getUrl('view', ['record' => Auth::user()->doctor_id], panel: 'telemedicina');
                        }
                        return TelemedicineDoctorResource::getUrl('create', panel: 'telemedicina');
                    }),
                'logout' => fn(Action $action) => $action
                    ->label('CERRAR SESIÓN')
                    ->color('critico')
                    ->url(route('internal')),
            ])
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundTelemedicinaPanelLogin')
                    ),
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}