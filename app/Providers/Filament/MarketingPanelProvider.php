<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Http\Middleware\DuplicatedSession;
use App\Support\Filament\MarketingPanelNavigationGroups;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
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

class MarketingPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('marketing')
            ->path('marketing')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => '#17335e',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverResources(in: app_path('Filament/Marketing/Resources'), for: 'App\Filament\Marketing\Resources')
            ->discoverPages(in: app_path('Filament/Marketing/Pages'), for: 'App\Filament\Marketing\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Marketing/Widgets'), for: 'App\Filament\Marketing\Widgets')
            ->widgets([
                WelcomeUserLiquidGlassWidget::class,
            ])
            ->databaseNotifications()
            ->databaseTransactions()
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
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->registerErrorNotification(
                title: 'ERROR DE EJECUCIÓN',
                body: 'Se produjo un error de ejecución, por favor contacte con el administrador.',
                statusCode: 404,
            )
            ->authMiddleware([
                Authenticate::class,
                DuplicatedSession::class,
            ])
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('PERFIL'),
                'logout' => fn (Action $action) => $action
                    ->label('CERRAR SESIÓN')
                    ->color('danger')
                    ->url(route('internal')),
                Action::make('business')
                    ->label('NEGOCIOS')
                    ->icon('heroicon-o-building-office-2')
                    ->color('warning')
                    ->hidden(function () {
                        $user = auth()->user()->departament;
                        if (in_array('SUPERADMIN', $user)) {
                            return false;
                        }

                        return true;
                    })
                    ->action(fn () => redirect(route('filament.business.pages.dashboard'))),
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.panels.internal-quick-nav')
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn () => view('filament.marketing.partials.sidebar-navigation-accordion-script')
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn () => view('filament.hooks.business-helpdesk-tickets-ticker-wrapper', [
                    'fullWidth' => true,
                ])
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.marketing.helpdesks.helpdesk-tour-script')
            )
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundMarketingPanelLogin')
                    ),
            ])
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups(MarketingPanelNavigationGroups::definitions());
    }
}
