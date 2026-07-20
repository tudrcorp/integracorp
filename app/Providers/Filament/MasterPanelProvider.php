<?php

namespace App\Providers\Filament;

use App\Filament\Master\Pages\ViewMyHierarchy;
use App\Filament\Master\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Enums\Width;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class MasterPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('master')
            ->path('master')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(function () {
                return Agency::where('code', Auth::user()->code_agency)->first()->conf_position_menu;
            })
            ->colors([
                'primary' => '#038C4C',
            ])
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverResources(in: app_path('Filament/Master/Resources'), for: 'App\Filament\Master\Resources')
            ->discoverPages(in: app_path('Filament/Master/Pages'), for: 'App\Filament\Master\Pages')
            ->pages([
                Dashboard::class,
                ViewMyHierarchy::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Master/Widgets'), for: 'App\Filament\Master\Widgets')
            ->widgets([
                AccountWidget::class,
                // FilamentInfoWidget::class,
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
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()
                    ->label('INDIVIDUALES')
                    ->icon('heroicon-s-swatch'),
                NavigationGroup::make()
                    ->label('CORPORATIVAS')
                    ->icon('heroicon-m-hand-raised'),
                NavigationGroup::make()
                    ->label('AFILIACIONES')
                    ->icon('heroicon-s-user-group'),
                NavigationGroup::make()
                    ->label('VENTAS')
                    ->icon('fontisto-wallet'),
                NavigationGroup::make()
                    ->label('ORGANIZACIÓN')
                    ->icon('heroicon-m-share'),
                NavigationGroup::make()
                    ->label('ZONA DE DESCARGA')
                    ->icon('heroicon-c-arrow-down-tray'),
            ])
            ->breadcrumbs(false)
            ->maxContentWidth(Width::Full)
            ->registerErrorNotification(
                title: 'ERROR DE EJECUCIÓN',
                body: 'Se produjo un error de ejecución, por favor contacte con el administrador.',
                statusCode: 404,
            )
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('Perfil Master')
                    ->icon('heroicon-o-user-circle')
                    ->url(AgencyResource::getUrl('edit', ['record' => DB::table('agencies')->select('id')->where('code', Auth::user()->code_agency)->first('id')->id], panel: 'master')),
                Action::make('viewHierarchy')
                    ->label('Ver Jerarquía')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->url(fn (): string => url('/master/ver-jerarquia'))
                    ->visible(fn (): bool => filled(Auth::user()?->code_agency)),
                'logout' => fn (Action $action) => $action
                    ->label('Cerrar Sesión')
                    ->color('danger')
                    ->url(route('external')),
            ])
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundMasterPanelLogin')
                    ),
            ])
            ->resourceEditPageRedirect('index')
            ->viteTheme('resources/css/filament/admin/theme.css');
    }
}
