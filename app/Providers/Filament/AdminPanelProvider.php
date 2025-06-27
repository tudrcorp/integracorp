<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration()
            ->passwordReset()
            ->profile()
            ->colors([
                'primary' => '#052F60',
            ])
            ->brandLogo(asset('image/logo_new.png'))
            ->brandLogoHeight('3.2rem')
            ->favicon(asset('image/favicon.png'))
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
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
            // ->sidebarCollapsibleOnDesktop()
            // ->maxContentWidth(Width::ScreenTwoExtraLarge)
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('TDEC')
                    ->icon('heroicon-m-academic-cap'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-s-folder-plus'),
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-s-user-plus'),
                NavigationGroup::make()
                    ->label('ADMINISTRACIÓN')
                    ->icon('heroicon-s-calculator'),
                NavigationGroup::make()
                    ->label('CONFIGURACION')
                    ->icon('heroicon-m-cog-8-tooth'),
                NavigationGroup::make()
                    ->label('SISTEMA')
                    ->icon('heroicon-c-tv'),
            ])
            ->breadcrumbs(false)
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Profile'),
                // ...
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
            // ->renderHook(PanelsRenderHook::FOOTER, function () {
            //     return view('footer-panel-admin');
            // });
    }
}