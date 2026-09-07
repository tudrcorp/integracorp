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
 *     soon_label: string|null,
 *     accent: bool,
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
        $isLoggedIn = $resolved instanceof User && StorefrontAuth::canAccessPwa($resolved);

        $items = [
            self::item('home', 'Inicio', 'Planes listos para cotizar', 'home', 'storefront.home'),
            self::item('quote', 'Cotizar', 'Elige un plan y arma la cotización', 'quote', 'storefront.home'),
            self::item('payments', 'Métodos de pago', 'Descarga o reenvía el documento', 'payments', 'storefront.payment-methods'),
        ];

        if ($isLoggedIn) {
            $items[] = self::item('quotes', 'Mis cotizaciones', 'Revisa las que generaste en la app', 'quotes', 'storefront.quotes', accent: true);
            $items[] = self::item('profile', 'Mi perfil', 'Actualiza cédula, teléfono o correo', 'login', 'storefront.profile');
            $items[] = self::item('logout', 'Cerrar sesión', 'Salir de la app', 'logout', 'storefront.logout', 'post');
        } else {
            $items[] = self::item('login', 'Entrar', 'Correo, teléfono o cédula', 'login', 'storefront.login');
            $items[] = self::item('register', 'Crear cuenta', 'Registro rápido para nuevos usuarios', 'affiliations', 'storefront.register');
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
            'storefront.quote.people' => '',
            'storefront.quote.details' => '',
            'storefront.quote.confirm' => '',
            'storefront.quote.result' => '',
            'storefront.quote.proposal' => '',
            'storefront.payment-methods' => '',
            'storefront.login' => 'Entrar',
            'storefront.register' => 'Registro',
            'storefront.profile' => 'Perfil',
            'storefront.quotes' => '',
            'storefront.quote.coverages' => '',
            'storefront.quote.frequency' => '',
            'storefront.quote.pay' => '',
            'storefront.quote.receipt' => '',
            'storefront.quote.receipt.success' => '',
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
            'storefront.payment-methods' => [
                'route' => 'storefront.home',
                'label' => 'Volver al catálogo',
            ],
            'storefront.quotes' => [
                'route' => 'storefront.home',
                'label' => 'Volver al catálogo',
            ],
            'storefront.quote.coverages' => [
                'route' => 'storefront.quotes',
                'label' => 'Volver a cotizaciones',
            ],
            'storefront.quote.frequency' => StorefrontQuoteCoverages::hasRequestSelection((string) (request()->route('code') ?? ''))
                ? [
                    'route' => 'storefront.quote.coverages',
                    'label' => 'Cambiar coberturas',
                    'params' => StorefrontQuoteCoverages::appendFromRequest([
                        'code' => (string) (request()->route('code') ?? ''),
                    ]),
                ]
                : [
                    'route' => 'storefront.quotes',
                    'label' => 'Volver a cotizaciones',
                ],
            'storefront.quote.pay' => [
                'route' => 'storefront.quote.frequency',
                'label' => 'Cambiar frecuencia',
                'params' => StorefrontQuoteCoverages::appendFromRequest([
                    'code' => (string) (request()->route('code') ?? ''),
                ]),
            ],
            'storefront.quote.receipt' => [
                'route' => 'storefront.quote.pay',
                'label' => 'Volver al pago',
                'params' => StorefrontQuoteCoverages::appendFromRequest([
                    'code' => (string) (request()->route('code') ?? ''),
                    'frequency' => (string) (request()->route('frequency') ?? StorefrontQuoteFrequency::Annual),
                ]),
            ],
            'storefront.quote.receipt.success' => [
                'route' => 'storefront.quotes',
                'label' => 'Volver a cotizaciones',
            ],
            default => null,
        };
    }

    private static function homeSubtitle(?User $user = null): string
    {
        $resolved = $user ?? StorefrontAuth::user();

        if ($resolved instanceof User && StorefrontAuth::canAccessPwa($resolved)) {
            return 'Hola, '.StorefrontAuth::displayName($resolved);
        }

        return '';
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
            'soon_label' => null,
            'accent' => false,
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
        ?string $soonLabel = null,
        bool $accent = false,
    ): array {
        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'icon' => $icon,
            'route' => $route,
            'method' => $method,
            'soon' => $soon,
            'soon_label' => $soonLabel,
            'accent' => $accent,
            'url' => null,
            'external' => false,
        ];
    }
}
