<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

use GeoIp2\Database\Reader;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Throwable;

/**
 * IP real y ubicación del usuario.
 *
 * Hoy producción está en Cloudflare solo como DNS: la IP de la conexión ya es
 * la del usuario. Si algún día se activa el proxy, `CF-Connecting-IP` y los
 * encabezados de ubicación se aceptan solo desde rangos oficiales de
 * Cloudflare. La ubicación sale de la base local por IP si está instalada; es un
 * dato de apoyo, nunca la base para bloquear.
 *
 * La base es DB-IP «IP to City Lite» (formato MMDB, lo lee el mismo lector de
 * GeoIP2). Se instala y actualiza con `php artisan live-presence:geoip-update`.
 */
final class ClientLocation
{
    private static ?Reader $reader = null;

    private static bool $readerUnavailable = false;

    /**
     * @var array<string, array{country: string, country_code: string, region: string, city: string, source: string}>
     */
    private static array $cache = [];

    /**
     * IP real del cliente.
     *
     * `CF-Connecting-IP` solo se acepta si la conexión viene de verdad desde un
     * rango de Cloudflare: sin proxy, cualquiera podría enviar ese encabezado y
     * hacerse pasar por otra IP. Hoy (DNS only) siempre gana la IP de la conexión.
     */
    public static function ip(Request $request): string
    {
        $connection = (string) $request->server('REMOTE_ADDR', $request->ip());

        if (self::cameThroughCloudflare($connection)) {
            $cloudflare = trim((string) $request->header('CF-Connecting-IP'));

            if ($cloudflare !== '' && filter_var($cloudflare, FILTER_VALIDATE_IP) !== false) {
                return $cloudflare;
            }
        }

        return $connection !== '' ? $connection : (string) $request->ip();
    }

    public static function cameThroughCloudflare(string $connectionIp): bool
    {
        if (! config('live-presence.trust_cloudflare_headers', true) || $connectionIp === '') {
            return false;
        }

        return IpUtils::checkIp($connectionIp, (array) config('live-presence.security.cloudflare_ranges', []));
    }

    /**
     * @return array{country: string, country_code: string, region: string, city: string, source: string}
     */
    public static function locate(Request $request, string $ip): array
    {
        if (self::cameThroughCloudflare((string) $request->server('REMOTE_ADDR', ''))) {
            $fromCloudflare = self::fromCloudflare($request);

            if ($fromCloudflare !== null && $fromCloudflare['city'] !== '') {
                return $fromCloudflare;
            }
        }

        return self::fromGeoIp($ip)
            ?? (self::cameThroughCloudflare((string) $request->server('REMOTE_ADDR', '')) ? self::fromCloudflare($request) : null)
            ?? self::unknown($ip);
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
            'source' => 'dbip',
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
            'city' => self::geoIpAvailable() ? 'Ubicación no encontrada' : 'Falta la base de ubicación',
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
