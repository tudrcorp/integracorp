<?php

namespace App\Providers\Filament;

use App\Filament\Agents\Pages\ViewMyHierarchy;
use App\Filament\Agents\Resources\Agents\AgentResource;
use App\Filament\AvatarProviders\BoringAvatarsProvider;
use App\Models\Agent;
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
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Component;
use Swis\Filament\Backgrounds\FilamentBackgroundsPlugin;
use Swis\Filament\Backgrounds\ImageProviders\MyImages;

class AgentsPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('agents')
            ->path('agents')
            ->login()
            ->passwordReset()
            ->profile()
            ->spa()
            ->sidebarCollapsibleOnDesktop()
            ->topNavigation(function () {
                return Agent::where('id', Auth::user()->agent_id)->first()->conf_position_menu;
            })
            ->colors([
                'primary' => '#00DCCD',
            ])
            ->brandLogo(asset('image/logoNewTDG.png'))
            ->darkModeBrandLogo(asset('image/logoTDG.png'))
            ->brandLogoHeight('3rem')
            ->favicon(asset('image/ico_Android_IOS.png'))
            ->discoverResources(in: app_path('Filament/Agents/Resources'), for: 'App\Filament\Agents\Resources')
            ->discoverPages(in: app_path('Filament/Agents/Pages'), for: 'App\Filament\Agents\Pages')
            ->pages([
                Dashboard::class,
                ViewMyHierarchy::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Agents/Widgets'), for: 'App\Filament\Agents\Widgets')
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
            ->registerErrorNotification(
                title: 'ERROR DE EJECUCIÓN',
                body: 'Se produjo un error de ejecución, por favor contacte con el administrador.',
                statusCode: 404,
            )
            ->userMenuItems([
                'profile' => fn (Action $action) => $action->label('Perfil del Agente')
                    ->icon('heroicon-s-user-circle')
                    ->color('primary')
                    ->url(function (Component $livewire) {
                        return AgentResource::getUrl('edit', ['record' => Auth::user()->agent_id], panel: 'agents');
                    }),
                Action::make('viewHierarchy')
                    ->label('Ver Jerarquía')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('info')
                    ->url(fn (): string => url('/agents/ver-jerarquia'))
                    ->visible(fn (): bool => filled(Auth::user()?->agent_id)),
                'logout' => fn (Action $action) => $action
                    ->label('Cerrar Sesión')
                    ->color('danger')
                    ->url(route('external')),
            ])
            ->breadcrumbs(false)
            // ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::Full)
            ->plugins([
                FilamentBackgroundsPlugin::make()
                    ->imageProvider(
                        MyImages::make()
                            ->directory('backgroundAgentPanelLogin')
                    ),
            ])
            ->defaultAvatarProvider(BoringAvatarsProvider::class)
            ->viteTheme('resources/css/filament/agents/theme.css');
    }
}
