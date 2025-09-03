<?php

namespace App\Providers\Filament;

use Filament\Panel;
use App\Models\User;
use App\Models\Agent;
use Livewire\Component;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Pages\Dashboard;
use Filament\Resources\Resource;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Toggle;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Schemas\Components\Fieldset;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;
use App\Filament\AvatarProviders\BoringAvatarsProvider;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use App\Filament\Telemedicina\Pages\EjecutarAccionUsuario;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;

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
            ->favicon(asset('image/favicon.png'))
            ->sidebarCollapsibleOnDesktop()
            ->colors([
                'primary' => '#00cefd',
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
            ])
            ->registerErrorNotification(
                title: 'Registro No Encontrado',
                body: 'El registro que intenta consultar no existe.',
                statusCode: 404,
            )
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Perfil del Doctor')
                    ->icon('healthicons-f-doctor-male')
                    ->color('primary')
                    ->url(TelemedicineDoctorResource::getUrl('edit', ['record' => Auth::user()->doctor_id], panel: 'telemedicina')),
                 
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
            ->viteTheme('resources/css/filament/telemedicina/theme.css');
    }
}