<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Throwable;

/**
 * IP real y ubicación del usuario.
 *
 * Producción está detrás de Cloudflare: la IP del cliente llega en
 * `CF-Connecting-IP` y, si el dominio tiene activos los encabezados de
 * ubicación, también país, región y ciudad. Si faltan, se consulta la base
 * GeoLite2 local (sin llamadas externas). En desarrollo la IP es local.
 */
final class ClientLocation
{
    private static ?Reader $reader = null;

    private static bool $readerUnavailable = false;

    /**
     * @var array<string, array{country: string, country_code: string, region: string, city: string, source: string}>
     */
    private static array $cache = [];

    public static function ip(Request $request): string
    {
        if (config('live-presence.trust_cloudflare_headers', true)) {
            $cloudflare = trim((string) $request->header('CF-Connecting-IP'));

            if ($cloudflare !== '' && filter_var($cloudflare, FILTER_VALIDATE_IP) !== false) {
                return $cloudflare;
            }
        }

        return (string) $request->ip();
    }

    /**
     * @return array{country: string, country_code: string, region: string, city: string, source: string}
     */
    public static function locate(Request $request, string $ip): array
    {
        if (config('live-presence.trust_cloudflare_headers', true)) {
            $fromCloudflare = self::fromCloudflare($request);

            if ($fromCloudflare !== null && $fromCloudflare['city'] !== '') {
                return $fromCloudflare;
            }
        }

        return self::fromGeoIp($ip) ?? self::fromCloudflare($request) ?? self::unknown($ip);
    }

    /**
     * @return array{country: string, country_code: string, region: string, city: string, source: string}|null
     */
    private static function fromCloudflare(Request $request): ?array
    {
        $code = strtoupper(trim((string) $request->header('CF-IPCountry')));

        if ($code === '' || $code === 'XX' || $code === 'T1') {
            return null;
        }

        return [
            'country' => self::countryName($code),
            'country_code' => $code,
            'region' => trim((string) $request->header('CF-Region')),
            'city' => trim((string) $request->header('CF-IPCity')),
            'source' => 'cloudflare',
        ];
    }

    /**
     * @return array{country: string, country_code: string, region: string, city: string, source: string}|null
     */
    private static function fromGeoIp(string $ip): ?array
    {
        if (isset(self::$cache[$ip])) {
            return self::$cache[$ip];
        }

        if (self::isPrivate($ip)) {
            return self::remember($ip, [
                'country' => 'Red local',
                'country_code' => '',
                'region' => '',
                'city' => 'Red interna o desarrollo',
                'source' => 'local',
            ]);
        }

        $reader = self::reader();

        if ($reader === null) {
            return null;
        }

        try {
            $record = $reader->city($ip);
        } catch (Throwable) {
            return null;
        }

        return self::remember($ip, [
            'country' => (string) ($record->country->names['es'] ?? $record->country->name ?? ''),
            'country_code' => (string) ($record->country->isoCode ?? ''),
            'region' => (string) ($record->mostSpecificSubdivision->names['es'] ?? $record->mostSpecificSubdivision->name ?? ''),
            'city' => (string) ($record->city->names['es'] ?? $record->city->name ?? ''),
            'source' => 'geolite2',
        ]);
    }

    public static function geoIpAvailable(): bool
    {
        return self::reader() !== null;
    }

    private static function reader(): ?Reader
    {
        if (self::$reader !== null || self::$readerUnavailable) {
            return self::$reader;
        }

        $path = (string) config('live-presence.geoip.database', '');

        if ($path === '' || ! is_file($path) || ! is_readable($path)) {
            self::$readerUnavailable = true;

            return null;
        }

        try {
            return self::$reader = new Reader($path, ['es', 'en']);
        } catch (Throwable) {
            self::$readerUnavailable = true;

            return null;
        }
    }

    private static function isPrivate(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }

    /**
     * @return array{country: string, country_code: string, region: string, city: string, source: string}
     */
    private static function unknown(string $ip): array
    {
        return [
            'country' => '',
            'country_code' => '',
            'region' => '',
            'city' => self::geoIpAvailable() ? 'Ubicación no encontrada' : 'Falta la base GeoLite2',
            'source' => 'none',
        ];
    }

    /**
     * @param  array{country: string, country_code: string, region: string, city: string, source: string}  $location
     * @return array{country: string, country_code: string, region: string, city: string, source: string}
     */
    private static function remember(string $ip, array $location): array
    {
        if (count(self::$cache) > 500) {
            self::$cache = [];
        }

        return self::$cache[$ip] = $location;
    }

    private static function countryName(string $code): string
    {
        if (class_exists(\Locale::class)) {
            $name = \Locale::getDisplayRegion('-'.$code, 'es');

            if (is_string($name) && $name !== '' && $name !== $code) {
                return $name;
            }
        }

        return $code;
    }
}
