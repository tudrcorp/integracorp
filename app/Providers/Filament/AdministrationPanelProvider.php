<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Http\Middleware\DuplicatedSession;
use App\Support\Filament\AdministrationPanelNavigationGroups;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

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
            ->favicon(asset('image/ico_Android_IOS.png'))
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
            ->navigationGroups(AdministrationPanelNavigationGroups::definitions())
            ->databaseNotifications()
            ->databaseTransactions()
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('PERFIL'),
                // ...
                'logout' => fn (Action $action) => $action
                    ->label('CERRAR SESIÓN')
                    ->color('danger')
                    ->url(route('internal')),
                Action::make('business')
                    ->label('NEGOCIOS')
                    ->icon('heroicon-o-building-office-2')
                    ->color('warning')
                    ->hidden(function () {
                        $departaments = (array) (Auth::user()?->departament ?? []);
                        if (in_array('SUPERADMIN', $departaments, true)) {
                            return false;
                        }

                        return true;
                    })
                    ->action(fn () => redirect(route('filament.business.pages.dashboard'))),
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
                DuplicatedSession::class,
            ])
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.panels.internal-quick-nav')
            )
            ->renderHook(
                PanelsRenderHook::SIDEBAR_NAV_END,
                fn () => view('filament.administration.partials.sidebar-navigation-accordion-script')
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.administration.partials.aviso-cobro-panel-script')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.administration.partials.recibo-pago-panel-script')->render(),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.administration.helpdesks.helpdesk-tour-script')
            )
            ->renderHook(
                PanelsRenderHook::CONTENT_START,
                fn () => view('filament.hooks.business-helpdesk-tickets-ticker-wrapper', [
                    'fullWidth' => true,
                ])
            )
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
