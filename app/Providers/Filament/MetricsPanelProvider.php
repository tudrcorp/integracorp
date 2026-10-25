<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Metrics\Pages\Cartera;
use App\Filament\Metrics\Pages\Comercial;
use App\Filament\Metrics\Pages\Dashboard;
use App\Filament\Metrics\Pages\Finanzas;
use App\Filament\Metrics\Pages\OperacionesYSalud;
use App\Filament\Metrics\Pages\Organizacion;
use App\Filament\Shared\Auth\PanelAwareLogin;
use App\Support\Filament\MetricsPanelNavigationGroups;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationItem;
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
use Illuminate\Support\Facades\Auth;
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
            ->login(PanelAwareLogin::class)
            ->passwordReset()
            ->profile()
            ->spa()
            ->brandName('Métricas/KPI')
            ->colors([
                'primary' => Color::Teal,
            ])
            ->navigationGroups(MetricsPanelNavigationGroups::definitions())
            /** Sin barra de módulos: el regreso a Negocios va como primer elemento del menú, en ámbar. */
            ->navigationItems([
                NavigationItem::make('Volver a Negocios')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->url(fn (): string => route('filament.business.pages.dashboard'))
                    ->visible(fn (): bool => (bool) Auth::user()?->canAccessPanel(Filament::getPanel('business')))
                    ->sort(-10)
                    ->extraAttributes(['class' => 'corp-nav-back']),
            ])
            /** Navegación arriba: el panel corporativo se usa a pantalla completa y en presentaciones. */
            ->topNavigation()
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
            /** Sin campana de notificaciones: el panel es de consulta y las notificaciones se atienden en los paneles operativos. */
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
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.metrics.corporate.topbar-styles')
            )
            /** Panel corporativo: tokens de color, transiciones y modo presentación del Dashboard de Operaciones. */
            ->renderHook(
                PanelsRenderHook::PAGE_START,
                fn () => view('filament.operations.dashboard.page-start', ['branding' => true, 'brandingLabel' => 'Panel corporativo · Toma de decisiones']),
                scopes: [Dashboard::class, Finanzas::class, Comercial::class, Cartera::class, OperacionesYSalud::class, Organizacion::class],
            );
    }
}
