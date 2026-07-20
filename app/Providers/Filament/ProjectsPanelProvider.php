<?php

namespace App\Providers\Filament;

use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;
use App\Support\Filament\ProjectManagement\RecentProjectsNavigation;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class ProjectsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('projects')
            ->path('projects')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => Color::Amber,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('GESTION DE PROYECTOS')
                    ->icon('heroicon-o-folder-open'),
                NavigationGroup::make()
                    ->label('PROYECTOS RECIENTES')
                    ->icon('heroicon-o-star'),
                NavigationGroup::make()
                    ->label('AYUDA')
                    ->icon('heroicon-o-question-mark-circle'),
            ])
            ->navigationItems(RecentProjectsNavigation::items())
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->databaseNotifications()
            ->databaseTransactions()
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverResources(in: app_path('Filament/Projects/Resources'), for: 'App\Filament\Projects\Resources')
            ->discoverPages(in: app_path('Filament/Projects/Pages'), for: 'App\Filament\Projects\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Projects/Widgets'), for: 'App\Filament\Projects\Widgets')
            ->widgets([
                WelcomeUserLiquidGlassWidget::class,
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
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.projects.partials.affiliation-documents-panel-script')
            )
            // ->renderHook(
            //     PanelsRenderHook::BODY_END,
            //     fn () => view('filament.projects.helpdesks.helpdesk-tour-script')
            // )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
                fn () => view('filament.panels.internal-quick-nav')
            );
        // ->renderHook(
        //     PanelsRenderHook::CONTENT_START,
        //     fn () => view('filament.hooks.projects-helpdesk-tickets-ticker-wrapper', [
        //         'fullWidth' => true,
        //     ])
        // );
    }
}
