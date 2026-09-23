<?php

declare(strict_types=1);

namespace App\Support\LivePresence;

/**
 * Navegador, sistema operativo y tipo de dispositivo a partir del User-Agent,
 * sin dependencias: se evalúa en cada petición, así que es una lista corta de
 * patrones en el orden correcto (Edge y Opera antes que Chrome, Chrome antes
 * que Safari).
 */
final class UserAgentSummary
{
    /**
     * @var array<string, array{browser: string, os: string, os_version: string, device: string, version: string}>
     */
    private static array $cache = [];

    /**
     * @return array{browser: string, version: string, os: string, os_version: string, device: string}
     */
    public static function parse(?string $userAgent): array
    {
        $userAgent = trim((string) $userAgent);

        if ($userAgent === '') {
            return ['browser' => 'Desconocido', 'version' => '', 'os' => 'Desconocido', 'os_version' => '', 'device' => 'desktop'];
        }

        $key = md5($userAgent);

        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        [$browser, $version] = self::browser($userAgent);
        [$os, $osVersion] = self::os($userAgent);

        if (count(self::$cache) > 200) {
            self::$cache = [];
        }

        return self::$cache[$key] = [
            'browser' => $browser,
            'version' => $version,
            'os' => $os,
            'os_version' => $osVersion,
            'device' => self::device($userAgent),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function browser(string $ua): array
    {
        $patterns = [
            'Edge' => '/Edg(?:e|A|iOS)?\/([\d.]+)/',
            'Opera' => '/(?:OPR|Opera)\/([\d.]+)/',
            'Samsung Internet' => '/SamsungBrowser\/([\d.]+)/',
            'Firefox' => '/(?:Firefox|FxiOS)\/([\d.]+)/',
            'Chrome' => '/(?:Chrome|CriOS)\/([\d.]+)/',
            'Safari' => '/Version\/([\d.]+).*Safari\//',
        ];

        foreach ($patterns as $name => $pattern) {
            if (preg_match($pattern, $ua, $match) === 1) {
                return [$name, self::majorMinor($match[1])];
            }
        }

        if (str_contains($ua, 'Safari/')) {
            return ['Safari', ''];
        }

        return ['Otro', ''];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function os(string $ua): array
    {
        if (preg_match('/(?:iPhone|iPad|iPod).*?OS ([\d_]+)/', $ua, $match) === 1) {
            return ['iOS', str_replace('_', '.', self::majorMinor(str_replace('_', '.', $match[1])))];
        }

        if (preg_match('/Android ([\d.]+)/', $ua, $match) === 1) {
            return ['Android', self::majorMinor($match[1])];
        }

        if (preg_match('/Windows NT ([\d.]+)/', $ua, $match) === 1) {
            return ['Windows', match ($match[1]) {
                '10.0' => '10/11',
                '6.3' => '8.1',
                '6.2' => '8',
                '6.1' => '7',
                default => $match[1],
            }];
        }

        if (preg_match('/Mac OS X ([\d_.]+)/', $ua, $match) === 1) {
            return ['macOS', str_replace('_', '.', self::majorMinor(str_replace('_', '.', $match[1])))];
        }

        if (str_contains($ua, 'CrOS')) {
            return ['ChromeOS', ''];
        }

        if (str_contains($ua, 'Linux')) {
            return ['Linux', ''];
        }

        return ['Otro', ''];
    }

    private static function device(string $ua): string
    {
        if (preg_match('/iPad|Tablet|(Android(?!.*Mobile))/i', $ua) === 1) {
            return 'tablet';
        }

        if (preg_match('/Mobi|iPhone|iPod|Android.*Mobile/i', $ua) === 1) {
            return 'mobile';
        }

        return 'desktop';
    }

    private static function majorMinor(string $version): string
    {
        $parts = explode('.', $version);

        return implode('.', array_slice($parts, 0, 2));
    }
}
