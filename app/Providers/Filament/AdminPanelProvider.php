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
use Illuminate\Support\Facades\Auth;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Blade;
use App\Http\Controllers\LogController;
use Filament\Forms\Components\TextInput;
use Filament\Navigation\NavigationGroup;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
use App\Http\Controllers\NotificationController;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
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
            ->passwordReset()
            ->profile()
            ->spa()
            ->colors([
                'primary' => '#052F60',
            ])
            ->sidebarCollapsibleOnDesktop()
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/imagotipo.png'))
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
                    ->icon('fontisto-doctor'),
                NavigationGroup::make()
                    ->label('TDEV')
                    ->icon('fontisto-plane'),
                NavigationGroup::make()
                    ->label('SOLICITUDES')
                    ->icon('heroicon-m-hand-raised'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-m-adjustments-vertical'),
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-m-user-group'),
                NavigationGroup::make()
                    ->label('ADMINISTRACIÓN')
                    ->icon('heroicon-s-calculator'),
                NavigationGroup::make()
                    ->label('TELEMEDICINA')
                    ->icon('healthicons-f-call-centre'),
                NavigationGroup::make()
                    ->label('MARKETING')
                    ->icon('heroicon-s-gift'),
                NavigationGroup::make()
                    ->label('CONFIGURACION')
                    ->icon('heroicon-m-cog-8-tooth'),
                NavigationGroup::make()
                    ->label('SISTEMA')
                    ->icon('heroicon-c-tv'),
                NavigationGroup::make()
                    ->label('HISTORICOS')
                    ->icon('fontisto-history'),
            ])
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Profile'),
                // ...
                'logout' => fn(Action $action) => $action
                    ->label('Cerrar Sesión')
                    ->color('danger')
                    ->url(route('internal')),
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
                            ->directory('backgroundAdminPanelLogin')
                    ),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->renderHook(
                PanelsRenderHook::FOOTER,
                fn() => view('footer-panel-admin')
            );
    }
}