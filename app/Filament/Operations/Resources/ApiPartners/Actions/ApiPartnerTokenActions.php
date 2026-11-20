<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Actions;

use App\Filament\Operations\Resources\ApiPartners\ApiPartnerResource;
use App\Models\ApiPartner;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

/**
 * Generar, rotar y revocar el token de un aliado. El token en claro viaja solo en el
 * modal que lo muestra (argumento de la acción): no se guarda, no se registra en logs
 * ni en notificaciones.
 */
final class ApiPartnerTokenActions
{
    public const SHOW_TOKEN_ACTION = 'showIssuedToken';

    /**
     * @param  callable(string): void  $showToken  abre el modal con el token recién generado
     */
    public static function issue(callable $showToken): Action
    {
        return Action::make('issueToken')
            ->label(fn (ApiPartner $record): string => $record->hasToken() ? 'Rotar token' : 'Generar token')
            ->icon(Heroicon::OutlinedKey)
            ->color('warning')
            ->authorize(fn (): bool => ApiPartnerResource::canAccess())
            ->requiresConfirmation()
            ->modalHeading(fn (ApiPartner $record): string => $record->hasToken() ? 'Rotar el token de '.$record->name : 'Generar token para '.$record->name)
            ->modalDescription(fn (ApiPartner $record): string => $record->hasToken()
                ? 'El token actual dejará de funcionar en el acto. El aliado deberá configurar el nuevo para seguir consultando.'
                : 'El token se mostrará una sola vez. Cópialo y entrégalo al aliado por un canal seguro.')
            ->modalSubmitActionLabel(fn (ApiPartner $record): string => $record->hasToken() ? 'Sí, rotar token' : 'Generar token')
            ->action(function (ApiPartner $record) use ($showToken): void {
                $hadToken = $record->hasToken();
                $token = $record->issueToken();

                SecurityAudit::log(
                    $hadToken ? 'AUDIT_API_PARTNER_TOKEN_ROTATED' : 'AUDIT_API_PARTNER_TOKEN_ISSUED',
                    'operations.api-partners.token.issue',
                    ['api_partner_id' => $record->getKey(), 'api_partner_name' => $record->name, 'token_prefix' => $record->token_prefix],
                );

                $showToken($token);
            });
    }

    public static function show(): Action
    {
        return Action::make(self::SHOW_TOKEN_ACTION)
            ->modalHeading('Token del aliado')
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalIconColor('warning')
            ->modalContent(fn (array $arguments): Htmlable => self::tokenContent((string) ($arguments['token'] ?? '')))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Ya lo copié')
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false);
    }

    public static function revoke(): Action
    {
        return Action::make('revokeToken')
            ->label('Revocar token')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (ApiPartner $record): bool => $record->hasToken())
            ->authorize(fn (): bool => ApiPartnerResource::canAccess())
            ->requiresConfirmation()
            ->modalHeading(fn (ApiPartner $record): string => 'Revocar el token de '.$record->name)
            ->modalDescription('El aliado dejará de poder consultar de inmediato. Para devolverle el acceso habrá que generar un token nuevo.')
            ->modalSubmitActionLabel('Sí, revocar')
            ->action(function (ApiPartner $record): void {
                $prefix = $record->token_prefix;
                $record->revokeToken();

                SecurityAudit::log('AUDIT_API_PARTNER_TOKEN_REVOKED', 'operations.api-partners.token.revoke', [
                    'api_partner_id' => $record->getKey(),
                    'api_partner_name' => $record->name,
                    'token_prefix' => $prefix,
                ]);

                Notification::make()
                    ->title('Token revocado')
                    ->body($record->name.' ya no puede consultar el API.')
                    ->success()
                    ->send();
            });
    }

    public static function tokenContent(string $token): Htmlable
    {
        $escaped = e($token);

        return new HtmlString(<<<HTML
            <div x-data="{ copied: false }" style="display:flex;flex-direction:column;gap:0.75rem;">
                <p style="font-size:0.875rem;line-height:1.4;">Cópialo ahora y entrégalo al aliado por un canal seguro. <strong>No se volverá a mostrar</strong>; si se pierde, genera uno nuevo.</p>
                <div style="display:flex;gap:0.5rem;align-items:stretch;">
                    <input type="text" readonly value="{$escaped}" x-ref="token" x-on:focus="\$event.target.select()" aria-label="Token del aliado"
                        style="flex:1 1 auto;min-width:0;font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:0.8rem;padding:0.5rem 0.75rem;border-radius:0.5rem;border:1px solid rgba(148,163,184,0.6);background:transparent;color:inherit;">
                    <button type="button"
                        x-on:click="navigator.clipboard.writeText(\$refs.token.value).then(() => { copied = true; setTimeout(() => copied = false, 2000) }, () => { \$refs.token.select() })"
                        style="flex:0 0 auto;padding:0.5rem 0.9rem;border-radius:0.5rem;font-size:0.8rem;font-weight:600;background:#d97706;color:#fff;">
                        <span x-show="! copied">Copiar</span>
                        <span x-show="copied" style="display:none;">Copiado</span>
                    </button>
                </div>
                <p style="font-size:0.75rem;opacity:0.75;">Uso: encabezado <code>Authorization: Bearer &lt;token&gt;</code> en cada petición.</p>
            </div>
        HTML);
    }
}
