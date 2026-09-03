<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\User;
use Throwable;

/**
 * Ítems de la hoja inferior (bottom sheet). El menú es corto:
 * planes, cotizar, sesión de agente y contacto por WhatsApp.
 *
 * @phpstan-type NavItem array{
 *     key: string,
 *     label: string,
 *     hint: string,
 *     icon: string,
 *     route: string|null,
 *     method: string,
 *     soon: bool,
 *     url: string|null,
 *     external: bool
 * }
 */
final class StorefrontNav
{
    /**
     * @return list<NavItem>
     */
    public static function items(?User $user = null): array
    {
        $resolved = func_num_args() === 0 ? StorefrontAuth::user() : $user;
        $isAgent = StorefrontAuth::isAgent($resolved);

        $items = [
            self::item('home', 'Inicio', 'Planes listos para cotizar', 'home', 'storefront.home'),
            self::item('quote', 'Cotizar', 'Elige un plan y arma la cotización', 'quote', 'storefront.home'),
        ];

        if ($isAgent) {
            $items[] = self::item('logout', 'Cerrar sesión', 'Salir del modo agente', 'logout', 'storefront.logout', 'post');
        } else {
            $items[] = self::item('login', 'Soy agente', 'Entra con tu cuenta de IntegraCorp', 'login', 'storefront.login');
        }

        $items[] = self::whatsapp(
            'business_whatsapp',
            'Equipo de negocios',
            'Hola, quiero hablar con el equipo de negocios.',
            self::phone('business'),
        );
        $items[] = self::whatsapp(
            'quotes_whatsapp',
            'Equipo de cotizaciones',
            'Hola, quiero una cotización con Tu Dr En Casa.',
            self::phone('quotes'),
        );

        return $items;
    }

    public static function title(?User $user = null): string
    {
        return 'Tu Dr En Casa';
    }

    public static function subtitle(?User $user = null): string
    {
        try {
            $name = request()->route()?->getName();
        } catch (Throwable) {
            $name = null;
        }

        return match ($name) {
            'storefront.plan' => '',
            'storefront.quote.people' => 'Cotizar',
            'storefront.quote.details' => 'Tus datos',
            'storefront.quote.confirm' => 'Confirmar',
            'storefront.quote.result' => 'Cotización lista',
            'storefront.quote.proposal' => 'Propuesta',
            'storefront.login' => 'Entrar',
            default => self::homeSubtitle($user),
        };
    }

    /**
     * @return array{route: string, label: string}|null
     */
    public static function back(): ?array
    {
        try {
            $name = request()->route()?->getName();
        } catch (Throwable) {
            $name = null;
        }

        return match ($name) {
            'storefront.plan' => [
                'route' => 'storefront.home',
                'label' => 'Volver al catálogo',
            ],
            default => null,
        };
    }

    private static function homeSubtitle(?User $user = null): string
    {
        $resolved = $user ?? StorefrontAuth::user();

        if (StorefrontAuth::isAgent($resolved)) {
            return 'Hola, '.StorefrontAuth::displayName($resolved);
        }

        return 'Planes';
    }

    /**
     * @return NavItem
     */
    private static function whatsapp(string $key, string $label, string $message, string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        $hint = $digits === ''
            ? 'Escribe por WhatsApp'
            : StorefrontPlanNarrative::phoneLabel($digits).' · Escribe por WhatsApp';

        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'icon' => 'whatsapp',
            'route' => null,
            'method' => 'get',
            'soon' => false,
            'url' => $digits === '' ? null : 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
            'external' => true,
        ];
    }

    private static function phone(string $which): string
    {
        try {
            $raw = $which === 'quotes'
                ? (string) config('services.storefront.whatsapp.quotes', '584127018390')
                : (string) config('services.storefront.whatsapp.business', config('services.chat_agent_registration.business_whatsapp_phone', '584127018390'));
        } catch (Throwable) {
            $raw = '584127018390';
        }

        $digits = preg_replace('/\D+/', '', $raw) ?: '584127018390';

        return $digits;
    }

    /**
     * @return NavItem
     */
    private static function item(
        string $key,
        string $label,
        string $hint,
        string $icon,
        ?string $route,
        string $method = 'get',
        bool $soon = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'icon' => $icon,
            'route' => $route,
            'method' => $method,
            'soon' => $soon,
            'url' => null,
            'external' => false,
        ];
    }
}
