<?php

namespace App\Providers\Filament;

use App\Filament\Business\Resources\Plans\PlanResource;
use App\Filament\Pages\AccountManagerDash;
use App\Http\Middleware\DuplicatedSession;
use Filament\Actions\Action;
use Filament\Enums\ThemeMode;
use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use function Filament\Support\original_request;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;

use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use SolutionForest\FilamentHeaderSelect\Components\HeaderSelect;
use SolutionForest\FilamentHeaderSelect\HeaderSelectPlugin;
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
                'primary'   => '#4c566a',
            ])
            ->navigationGroups([
                
                NavigationGroup::make()
                    ->label('ESTRUCTURA COMERCIAL')
                    ->icon('heroicon-o-building-office-2'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-o-currency-dollar'),
                NavigationGroup::make()
                    ->label('SOLICITUDES')
                    ->icon('heroicon-o-square-3-stack-3d'),
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-o-identification'),
                NavigationGroup::make()
                    ->label('ZONA DE DESCARGA')
                    ->icon('heroicon-o-cloud-arrow-down'),
                NavigationGroup::make()
                    ->label('CONFIGURACIÓN')
                    ->icon('heroicon-o-cog-8-tooth')
                    ->collapsed(),

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
                'profile' => fn(Action $action) => $action->label('PERFIL DE USUARIO'),
                // ...
                'logout' => fn(Action $action) => $action
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
                PanelsRenderHook::TOPBAR_END,
                fn() => view('filament.name-user')
            )
            // ->renderHook(
            //     PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            //     fn() => view('filament.menu-user')
            // )
            ->viteTheme('resources/css/filament/admin/theme.css');
            // ->defaultThemeMode(ThemeMode::Light);
    }
}