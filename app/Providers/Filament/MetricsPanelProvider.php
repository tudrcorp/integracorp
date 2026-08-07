<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Metrics\Widgets\VenezuelaActivityMapWidget;
use App\Support\Filament\MetricsPanelNavigationGroups;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class MetricsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('metrics')
            ->path('metrics')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->brandName('Métricas/KPI')
            ->colors([
                'primary' => Color::Teal,
            ])
            ->navigationGroups(MetricsPanelNavigationGroups::definitions())
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverClusters(in: app_path('Filament/Metrics/Clusters'), for: 'App\Filament\Metrics\Clusters')
            ->discoverResources(in: app_path('Filament/Metrics/Resources'), for: 'App\Filament\Metrics\Resources')
            ->discoverPages(in: app_path('Filament/Metrics/Pages'), for: 'App\Filament\Metrics\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Metrics/Widgets'), for: 'App\Filament\Metrics\Widgets')
            ->widgets([
                VenezuelaActivityMapWidget::class,
            ])
            ->databaseNotifications()
            ->databaseTransactions()
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundBusenissPanelLogin')
                    ),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('PERFIL DE USUARIO'),
                'logout' => fn (Action $action) => $action
                    ->label('CERRAR SESIÓN')
                    ->color('danger')
                    ->url(route('internal')),
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.panels.internal-quick-nav')
            );
    }
}
