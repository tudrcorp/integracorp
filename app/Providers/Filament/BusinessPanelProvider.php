<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Http\Middleware\DuplicatedSession;
use App\Support\Filament\BusinessPanelNavigationGroups;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
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
                'primary' => '#4c566a',
            ])
            ->navigationGroups(BusinessPanelNavigationGroups::definitions())
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->databaseNotifications(livewireComponent: null, isLazy: false)
            ->databaseNotificationsPolling('10s')
            ->databaseTransactions()
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverResources(in: app_path('Filament/Business/Resources'), for: 'App\Filament\Business\Resources')
            ->discoverClusters(in: app_path('Filament/Business/Clusters'), for: 'App\Filament\Business\Clusters')
            ->discoverPages(in: app_path('Filament/Business/Pages'), for: 'App\Filament\Business\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Business/Widgets'), for: 'App\Filament\Business\Widgets')
            ->widgets([
                WelcomeUserLiquidGlassWidget::class,
            ])
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('PERFIL DE USUARIO'),
                // ...
                'logout' => fn (Action $action) => $action
                    ->label('CERRAR SESIÓN')
                    ->color('danger')
                    ->url(route('internal')),
                Action::make('Administracion')
                    ->label('ADMINISTRACIÓN')
                    ->icon('heroicon-o-currency-dollar')
                    ->color('warning')
                    ->hidden(function () {
                        $user = auth()->user()->departament;
                        if (in_array('SUPERADMIN', $user)) {
                            return false;
                        }

                        return true;
                    })
                    ->action(fn () => redirect(route('filament.administration.pages.dashboard'))),
                Action::make('Operaciones')
                    ->label('OPERACIONES')
                    ->icon('heroicon-c-server-stack')
                    ->color('success')
                    ->hidden(function () {
                        $user = auth()->user()->departament;
                        if (in_array('SUPERADMIN', $user)) {
                            return false;
                        }

                        return true;
                    })
                    ->action(fn () => redirect(route('filament.operations.pages.dashboard'))),
                Action::make('Marketing')
                    ->label('MARKETING')
                    ->icon('heroicon-o-cursor-arrow-rays')
                    ->color('info')
                    ->hidden(function () {
                        $user = auth()->user()->departament;
                        if (in_array('SUPERADMIN', $user)) {
                            return false;
                        }

                        return true;
                    })
                    ->action(fn () => redirect(route('filament.marketing.pages.dashboard'))),
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
                DuplicatedSession::class,
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
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.panels.internal-quick-nav')
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn () => view('filament.hooks.business-helpdesk-tickets-ticker-wrapper', [
                    'fullWidth' => true,
                ])
            )
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn () => view('filament.business.partials.sidebar-navigation-accordion-script')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.business.partials.affiliation-documents-panel-script')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.business.helpdesks.helpdesk-tour-script')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.business.partials.database-notifications-alert')
            );
        // ->defaultThemeMode(ThemeMode::Light);
    }
}
