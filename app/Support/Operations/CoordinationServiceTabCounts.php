<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Closure;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Caché de los contadores de pestañas del cuadro de control de servicios médicos.
 *
 * El conteo de «Todas» recorre la tabla completa con ocho subconsultas por fila y se
 * pedía en cada render (página, carga diferida de la tabla y cada clic). Aquí se
 * guarda por alcance —cada proveedor, ATENMEDI o quien ve eliminados cuenta otra
 * cosa— durante {@see self::TTL_SECONDS} segundos, y se invalida en cuanto se escribe
 * una coordinación, un ítem clínico o el estatus de un caso.
 *
 * Las escrituras masivas (`Model::query()->update()`) no disparan eventos: esas se
 * reflejan al vencer el TTL.
 */
final class CoordinationServiceTabCounts
{
    public const TTL_SECONDS = 60;

    private const VERSION_KEY = 'operations:coordination-tab-counts:version';

    /**
     * Versión de la regla de conteo; subirla evita servir conteos guardados con la anterior.
     * `v2`: las pestañas de trabajo excluyen los servicios finalizados.
     * `v3`: el proveedor también ve servicios con estudios o especialistas cubiertos.
     */
    private const KEY_PREFIX = 'operations:coordination-tab-counts:v3';

    /**
     * @param  Closure(): array<string, int>  $compute
     * @return array<string, int>
     */
    public static function remember(Closure $compute): array
    {
        try {
            $cached = Cache::remember(self::cacheKey(), self::TTL_SECONDS, $compute);

            if (is_array($cached)) {
                return $cached;
            }
        } catch (Throwable $exception) {
            Log::warning('No se pudo usar la caché de contadores del cuadro de control; se calculan en vivo.', [
                'exception' => $exception->getMessage(),
            ]);
        }

        return $compute();
    }

    /**
     * Invalida todos los alcances de una vez cambiando la versión de la clave.
     * Si hay una transacción abierta, espera al commit: invalidar antes dejaría
     * que otra petición guarde en caché el conteo sin el cambio.
     */
    public static function invalidate(): void
    {
        DB::afterCommit(static function (): void {
            try {
                Cache::forever(self::VERSION_KEY, (string) Str::uuid());
            } catch (Throwable $exception) {
                Log::warning('No se pudo invalidar la caché de contadores del cuadro de control.', [
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }

    public static function registerCacheInvalidation(): void
    {
        $invalidate = static function (): void {
            self::invalidate();
        };

        foreach ([
            OperationCoordinationService::class,
            TelemedicinePatientMedications::class,
            TelemedicinePatientLab::class,
            TelemedicinePatientStudy::class,
            TelemedicinePatientSpecialty::class,
        ] as $model) {
            $model::saved($invalidate);
            $model::deleted($invalidate);
        }

        /*
         * Un caso ELIMINADO saca sus coordinaciones del cuadro (scope global), así
         * que solo importa cuando cambia el estatus del caso.
         */
        TelemedicineCase::saved(static function (TelemedicineCase $case): void {
            if ($case->wasChanged('status') || $case->wasRecentlyCreated) {
                self::invalidate();
            }
        });
        TelemedicineCase::deleted($invalidate);
    }

    public static function cacheKey(): string
    {
        return self::KEY_PREFIX.':'.self::version().':'.self::scopeSignature();
    }

    /**
     * Lo que cambia el resultado del conteo según quién mira.
     */
    public static function scopeSignature(): string
    {
        $supplierId = OperationsSupplierScope::currentSupplierId();
        $isAtenmedi = in_array('ATENMEDI', Auth::user()?->departament ?? [], true);

        return implode('|', [
            'supplier='.($supplierId ?? 'tdg'),
            'atenmedi='.($isAtenmedi ? '1' : '0'),
            'deleted='.(CoordinationServiceCaseDeletion::userCanDeleteCases() ? '1' : '0'),
        ]);
    }

    private static function version(): string
    {
        try {
            $version = Cache::get(self::VERSION_KEY);
        } catch (Throwable) {
            return 'sin-version';
        }

        return is_string($version) && $version !== '' ? $version : '0';
    }
}
