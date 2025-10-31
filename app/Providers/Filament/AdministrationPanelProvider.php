<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;


class AdministrationPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('administration')
            ->path('administration')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/imagotipo.png'))
            ->discoverResources(in: app_path('Filament/Administration/Resources'), for: 'App\Filament\Administration\Resources')
            ->discoverPages(in: app_path('Filament/Administration/Pages'), for: 'App\Filament\Administration\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Administration/Widgets'), for: 'App\Filament\Administration\Widgets')
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
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Profile'),
                // ...
                'logout' => fn(Action $action) => $action
                    ->label('Cerrar Sesión')
                    ->color('danger')
                    ->url(route('internal')),
            ])
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundAgentPanelLogin')
                    ),
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}