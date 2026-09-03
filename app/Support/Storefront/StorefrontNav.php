<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\User;
use Throwable;

/**
 * Ítems de la hoja inferior (bottom sheet). Los módulos que todavía
 * no existen se muestran como «próximamente» para que la arquitectura
 * de la app ya se sienta completa.
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
            self::item('quote', 'Cotizar', 'Arma una cotización en minutos', 'quote', 'storefront.home'),
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

        $items[] = self::item('affiliations', 'Afiliaciones', 'Consulta y da de alta', 'affiliations', null, 'get', true);
        $items[] = self::item('payments', 'Pagos', 'Historial de cobros realizados', 'payments', null, 'get', true);
        $items[] = self::item('pending', 'Cobros pendientes', 'Lo que aún falta por cobrar', 'pending', null, 'get', true);

        return $items;
    }

    public static function title(?User $user = null): string
    {
        return 'Tu Dr En Casa';
    }

    public static function subtitle(?User $user = null): string
    {
        $resolved = func_num_args() === 0 ? StorefrontAuth::user() : $user;

        if (StorefrontAuth::isAgent($resolved)) {
            return 'Hola, '.StorefrontAuth::displayName($resolved);
        }

        return 'Planes de asistencia médica';
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
