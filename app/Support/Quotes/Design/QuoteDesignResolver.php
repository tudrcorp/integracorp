<?php

declare(strict_types=1);

namespace App\Support\Quotes\Design;

use App\Models\QuoteDesign;
use App\Models\QuoteDesignIssuance;
use App\Models\QuoteDesignVersion;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Qué diseño lleva cada plan de una cotización.
 *
 * La primera vez que se emite la propuesta se anota la versión vigente del diseño del plan
 * ({@see QuoteDesignIssuance}); si la propuesta se vuelve a generar (p. ej. se perdió el
 * archivo), se usa esa misma versión aunque el diseño se haya editado o inactivado después.
 *
 * Una cotización que se emitió sin diseño sigue sin diseño: activar uno nuevo no cambia
 * propuestas viejas al regenerarlas.
 */
final class QuoteDesignResolver
{
    /**
     * Memoria corta por plan: un worker de colas vive horas y no debe quedarse con un diseño que
     * ya se inactivó en otro proceso.
     */
    private const MEMO_SECONDS = 30;

    /** @var array<int, array{version: QuoteDesignVersion|false, at: int}> */
    private static array $activeVersionByPlan = [];

    private static ?bool $tablesExist = null;

    /**
     * Versión con la que se emite (o se emitió) el plan de esta cotización, o `null` si va sin
     * diseño. Nunca lanza: ante cualquier problema la propuesta sale con el diseño de siempre.
     */
    public static function forIssuance(string $scope, int $quoteId, int $planId, bool $quoteIsNew): ?QuoteDesignVersion
    {
        if (! self::tablesExist()) {
            return null;
        }

        try {
            $issued = QuoteDesignIssuance::query()
                ->with('version')
                ->where('scope', $scope)
                ->where('quote_id', $quoteId)
                ->where('plan_id', $planId)
                ->first();

            if ($issued !== null) {
                return $issued->version;
            }

            /** Regenerar una propuesta que se emitió sin diseño no le pone uno ahora. */
            if (! $quoteIsNew) {
                return null;
            }

            $version = self::activeVersionForPlan($planId);

            if ($version === null) {
                return null;
            }

            try {
                QuoteDesignIssuance::query()->create([
                    'scope' => $scope,
                    'quote_id' => $quoteId,
                    'plan_id' => $planId,
                    'quote_design_version_id' => $version->getKey(),
                ]);
            } catch (QueryException) {
                /** Dos generaciones a la vez: gana la que anotó primero. */
                return QuoteDesignIssuance::query()
                    ->with('version')
                    ->where('scope', $scope)
                    ->where('quote_id', $quoteId)
                    ->where('plan_id', $planId)
                    ->first()?->version ?? $version;
            }

            return $version;
        } catch (Throwable $exception) {
            Log::error('quote-design: no se pudo resolver el diseño; la propuesta sale con el diseño de siempre', [
                'scope' => $scope,
                'quote_id' => $quoteId,
                'plan_id' => $planId,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Versión vigente del diseño activo del plan, sin anotar nada (vista previa, comprobaciones).
     */
    public static function activeVersionForPlan(int $planId): ?QuoteDesignVersion
    {
        if (! self::tablesExist()) {
            return null;
        }

        $memo = self::$activeVersionByPlan[$planId] ?? null;

        if ($memo !== null && time() - $memo['at'] < self::MEMO_SECONDS) {
            return $memo['version'] ?: null;
        }

        $design = QuoteDesign::query()
            ->active()
            ->whereHas('plans', fn ($query) => $query->whereKey($planId))
            ->first();

        $version = $design?->versions()->where('version', $design->version)->first();

        self::$activeVersionByPlan[$planId] = ['version' => $version ?? false, 'at' => time()];

        return $version;
    }

    /**
     * Si alguno de los planes tiene diseño (anotado o activo). Consulta barata para decidir si la
     * propuesta se arma con diseño antes de construir nada.
     *
     * @param  list<int>  $planIds
     */
    public static function anyPlanHasDesign(string $scope, int $quoteId, array $planIds, bool $quoteIsNew): bool
    {
        if (! self::tablesExist() || $planIds === []) {
            return false;
        }

        try {
            $issued = QuoteDesignIssuance::query()
                ->where('scope', $scope)
                ->where('quote_id', $quoteId)
                ->whereIn('plan_id', $planIds)
                ->exists();

            if ($issued) {
                return true;
            }

            if (! $quoteIsNew) {
                return false;
            }

            foreach ($planIds as $planId) {
                if (self::activeVersionForPlan($planId) !== null) {
                    return true;
                }
            }
        } catch (Throwable $exception) {
            Log::error('quote-design: no se pudo consultar el diseño de los planes', [
                'scope' => $scope,
                'quote_id' => $quoteId,
                'message' => $exception->getMessage(),
            ]);
        }

        return false;
    }

    /**
     * Olvida lo memorizado: tras guardar, activar o inactivar un diseño, y entre tests.
     */
    public static function flush(): void
    {
        self::$activeVersionByPlan = [];
        self::$tablesExist = null;
    }

    /**
     * El código puede llegar a producción antes que la migración: sin tablas, todo sigue igual.
     */
    private static function tablesExist(): bool
    {
        if (self::$tablesExist !== null) {
            return self::$tablesExist;
        }

        try {
            return self::$tablesExist = Schema::hasTable('quote_design_issuances');
        } catch (Throwable) {
            return self::$tablesExist = false;
        }
    }
}
