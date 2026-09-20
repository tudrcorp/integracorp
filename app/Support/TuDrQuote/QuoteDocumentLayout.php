<?php

declare(strict_types=1);

namespace App\Support\TuDrQuote;

use App\Models\QuoteDocumentLayoutSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

/**
 * Cuántas hojas tiene la Propuesta Económica y en qué página van los cálculos.
 *
 * El microservicio no sabe de paginación: devuelve las páginas de cálculo y es
 * el portal quien compone el documento. Esta configuración es lo que el
 * SUPERADMIN ajusta desde CONFIGURACIÓN sin tocar código.
 */
final class QuoteDocumentLayout
{
    public const SCOPE_INDIVIDUAL = 'individual';

    public const SCOPE_CORPORATE = 'corporate';

    /**
     * Tantas hojas como puede traer el documento del servicio —portada,
     * «acerca», una por plan, patologías y contraportada—, para que la
     * configuración por defecto no recorte nada.
     */
    public const DEFAULT_TOTAL_PAGES = 7;

    /** Donde el servicio ya las coloca: tras portada y «acerca». */
    public const DEFAULT_CALCULATIONS_PAGE = 3;

    public const MAX_TOTAL_PAGES = 20;

    private const CACHE_KEY = 'tudr-quote:document-layout:';

    /**
     * @return list<string>
     */
    public static function scopes(): array
    {
        return [self::SCOPE_INDIVIDUAL, self::SCOPE_CORPORATE];
    }

    public static function label(string $scope): string
    {
        return match ($scope) {
            self::SCOPE_CORPORATE => 'Cotización corporativa',
            default => 'Cotización individual',
        };
    }

    /**
     * @return array{total_pages: int, calculations_page: int}
     */
    public static function for(string $scope): array
    {
        $scope = self::normalizeScope($scope);

        /** @var array{total_pages: int, calculations_page: int} */
        return Cache::remember(
            self::CACHE_KEY.$scope,
            now()->addMinutes(10),
            static function () use ($scope): array {
                $setting = QuoteDocumentLayoutSetting::query()->where('scope', $scope)->first();

                return self::sanitize(
                    (int) ($setting->total_pages ?? self::DEFAULT_TOTAL_PAGES),
                    (int) ($setting->calculations_page ?? self::DEFAULT_CALCULATIONS_PAGE),
                );
            },
        );
    }

    /**
     * @return array{total_pages: int, calculations_page: int}
     */
    public static function save(string $scope, int $totalPages, int $calculationsPage): array
    {
        $scope = self::normalizeScope($scope);
        $values = self::sanitize($totalPages, $calculationsPage);

        QuoteDocumentLayoutSetting::query()->updateOrCreate(
            ['scope' => $scope],
            $values + ['updated_by' => Auth::user()?->name],
        );

        Cache::forget(self::CACHE_KEY.$scope);

        return $values;
    }

    public static function flush(): void
    {
        foreach (self::scopes() as $scope) {
            Cache::forget(self::CACHE_KEY.$scope);
        }
    }

    /**
     * La página de cálculos nunca puede caer fuera del documento: un valor
     * mayor que el total dejaría el PDF sin tarifas, que es lo único que el
     * cliente necesita ver.
     *
     * @return array{total_pages: int, calculations_page: int}
     */
    public static function sanitize(int $totalPages, int $calculationsPage): array
    {
        $totalPages = max(1, min($totalPages, self::MAX_TOTAL_PAGES));
        $calculationsPage = max(1, min($calculationsPage, $totalPages));

        return [
            'total_pages' => $totalPages,
            'calculations_page' => $calculationsPage,
        ];
    }

    private static function normalizeScope(string $scope): string
    {
        return in_array($scope, self::scopes(), true) ? $scope : self::SCOPE_INDIVIDUAL;
    }
}
