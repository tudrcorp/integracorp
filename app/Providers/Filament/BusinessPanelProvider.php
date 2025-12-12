<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Pages\Dashboard;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Illuminate\Support\HtmlString;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;
use Filament\Navigation\NavigationItem;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets\FilamentInfoWidget;
use App\Filament\Pages\AccountManagerDash;
use Filament\Http\Middleware\Authenticate;
use Filament\Navigation\NavigationBuilder;
use function Filament\Support\original_request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

use App\Filament\Business\Resources\Plans\PlanResource;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use SolutionForest\FilamentHeaderSelect\HeaderSelectPlugin;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use SolutionForest\FilamentHeaderSelect\Components\HeaderSelect;

class BusinessPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('business')
            ->path('business')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary'   => '#5196CE',
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('CONFIGURACIÓN')
                    ->icon('heroicon-o-cog-8-tooth')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('ESTRUCTURA COMERCIAL')
                    ->icon('heroicon-o-building-office-2'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-o-identification'),
                NavigationGroup::make()
                    ->label('SOLICITUDES')
                    ->icon('heroicon-o-square-3-stack-3d'),
                NavigationGroup::make()
                    ->label('ZONA DE DESCARGA')
                    ->icon('heroicon-o-cloud-arrow-down'),
                

            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->databaseNotifications()
            ->databaseTransactions()
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->favicon(asset('image/imagotipo.png'))
            ->discoverResources(in: app_path('Filament/Business/Resources'), for: 'App\Filament\Business\Resources')
            ->discoverPages(in: app_path('Filament/Business/Pages'), for: 'App\Filament\Business\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Business/Widgets'), for: 'App\Filament\Business\Widgets')
            ->widgets([
                // AccountWidget::class,
                // FilamentInfoWidget::class,
            ])
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Profile'),
                // ...
                'logout' => fn(Action $action) => $action
                    ->label('Cerrar Sesión')
                    ->color('danger')
                    ->url(route('internal')),
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->registerErrorNotification(
                title: 'ERROR DE EJECUCIÓN',
                body: 'Se produjo un error de ejecución, por favor contacte con el administrador.',
                statusCode: 404,
            )
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundBusenissPanelLogin')
                    ),
            ])
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn() => view('filament.name-user')
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn() => view('filament.menu-user')
            )
            ->defaultThemeMode(ThemeMode::Light);
    }
}