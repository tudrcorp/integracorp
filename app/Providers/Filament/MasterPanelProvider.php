<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Pages\Dashboard;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
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
            ->brandLogoHeight('3.7rem')
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
                    ->label('Afiliaciones')
                    ->icon('heroicon-s-user-group'),
                NavigationGroup::make()
                    ->label('Cotizaciones')
                    ->icon('heroicon-s-swatch'),
                NavigationGroup::make()
                    ->label('Organización')
                    ->icon('heroicon-m-share'),
                NavigationGroup::make()
                    ->label('Ventas')
                    ->icon('heroicon-s-calculator'),
        ]);
    }
}