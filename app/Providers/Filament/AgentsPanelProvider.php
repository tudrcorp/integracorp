<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Filament\Actions\Action;
use Filament\Pages\Dashboard;
use Filament\Support\Enums\Width;
use Filament\Support\Colors\Color;
use Filament\Widgets\AccountWidget;
use Illuminate\Support\Facades\Auth;
use Filament\Navigation\NavigationGroup;
use Filament\Widgets\FilamentInfoWidget;
use Filament\Http\Middleware\Authenticate;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Filament\Http\Middleware\AuthenticateSession;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use App\Filament\Agents\Resources\Agents\AgentResource;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;

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
            ->topNavigation()
            ->colors([
                'primary' => '#00DCCD',
            ])
            ->brandLogo(asset('image/logo_new.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('image/favicon.png'))
            ->discoverResources(in: app_path('Filament/Agents/Resources'), for: 'App\Filament\Agents\Resources')
            ->discoverPages(in: app_path('Filament/Agents/Pages'), for: 'App\Filament\Agents\Pages')
            ->pages([
                Dashboard::class,
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
                    ->label('AFILIACIONES')
                    ->icon('heroicon-s-user-group'),
                NavigationGroup::make()
                    ->label('SOLICITUD')
                    ->icon('heroicon-m-hand-raised'),
                NavigationGroup::make()
                    ->label('COTIZACIONES')
                    ->icon('heroicon-s-swatch'),
                NavigationGroup::make()
                    ->label('ORGANIZACIÓN')
                    ->icon('heroicon-m-share'),
                NavigationGroup::make()
                    ->label('ZONA DE DESCARGA')
                    ->icon('heroicon-c-arrow-down-tray'),
                NavigationGroup::make()
                    ->label('VENTAS')
                    ->icon('heroicon-s-calculator'),
            ])
            ->registerErrorNotification(
                title: 'Registro No Encontrado',
                body: 'El registro que intenta consultar no existe.',
                statusCode: 404,
            )
            ->userMenuItems([
                'profile' => fn(Action $action) => $action->label('Perfil del Agente')
                    ->icon('heroicon-s-user-circle')
                    ->color('primary')
                    ->url(AgentResource::getUrl('edit', ['record' => Auth::user()->agent_id], panel: 'agents')),
                // // ...
                Action::make('send_message')
                    ->label('Enviar Notificación')
                    ->icon('heroicon-s-bell'),
                // // ...
            ])
            ->breadcrumbs(false)
            // ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth(Width::ScreenExtraLarge);
            // ->maxContentWidth(Width::Screen);


    }
}