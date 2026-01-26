<?php

namespace App\Providers\Filament;

use App\Http\Middleware\DuplicatedSession;
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
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
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
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/imagotipo.png'))
            ->discoverResources(in: app_path('Filament/Marketing/Resources'), for: 'App\Filament\Marketing\Resources')
            ->discoverPages(in: app_path('Filament/Marketing/Pages'), for: 'App\Filament\Marketing\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Marketing/Widgets'), for: 'App\Filament\Marketing\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
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
                'logout' => fn(Action $action) => $action
                    ->label('Cerrar Sesión')
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
                    ->action(fn() => redirect(route('filament.business.pages.dashboard'))),
            ])
            // ->renderHook(
            //     PanelsRenderHook::GLOBAL_SEARCH_BEFORE,
            //     fn() => view('filament.return-modulo-negocios')
            // )
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundMarketingPanelLogin')
                    ),
                ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-o-user-group')
                    ->collapsed(),
                NavigationGroup::make()
                    ->label('ESTRUCTURA DE CORRETAJES')
                    ->icon('heroicon-o-building-office-2'),
                NavigationGroup::make()
                    ->label('ESTRUCTURA DE VIAJES')
                    ->icon('heroicon-o-paper-airplane'),
                NavigationGroup::make()
                    ->label('ADMINISTRACION/RRHH')
                    ->icon('heroicon-o-users'),
                NavigationGroup::make()
                    ->label('MARKETING')
                    ->icon('heroicon-m-cursor-arrow-rays'),
                NavigationGroup::make()
                    ->label('VENTAS DIRECTAS')
                    ->icon('heroicon-m-cursor-arrow-rays'),

            ]);
    }
}