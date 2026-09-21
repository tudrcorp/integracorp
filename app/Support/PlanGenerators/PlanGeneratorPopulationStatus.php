<?php

declare(strict_types=1);

namespace App\Support\PlanGenerators;

use App\Models\PlanGenerator;

/**
 * Estado del padrón de una pre-afiliación corporativa: cuántas filas hay, si el
 * import sigue corriendo y si ya se puede crear la afiliación.
 *
 * La regla que importa: **no se continúa mientras el import esté en cola**. El
 * padrón se copia a `affiliate_corporates` al crear la afiliación, así que
 * hacerlo a medio importar dejaría afiliados fuera y nadie se enteraría.
 */
final class PlanGeneratorPopulationStatus
{
    public static function importedCount(PlanGenerator $plan): int
    {
        return $plan->populations()->count();
    }

    /**
     * Población declarada en la matriz del plan, que es la que el analista
     * cotizó. Se contrasta con lo importado para avisar de diferencias.
     */
    public static function declaredPopulation(): int
    {
        $payload = PlanGeneratorPreAffiliationSession::get();

        return max(0, (int) ($payload['total_persons'] ?? 0));
    }

    public static function isImportRunning(PlanGenerator $plan): bool
    {
        $import = $plan->populationImport;

        return $import !== null && $import->completed_at === null;
    }

    /**
     * Progreso del último import.
     *
     * `failed` sale de `failed_import_rows`, no de `Import::getFailedRowsCount()`:
     * ese método es `total_rows - successful_rows`, así que antes de que el
     * worker toque el lote devuelve el total de filas y la pantalla acusaba de
     * fallidas 8 filas que nadie había procesado todavía.
     *
     * `queued` distingue «encolado y sin arrancar» de «procesando»: lo normal
     * cuando se queda en cero es que no haya un worker levantado.
     *
     * @return array{
     *     total: int,
     *     processed: int,
     *     successful: int,
     *     failed: int,
     *     completed: bool,
     *     queued: bool
     * }|null
     */
    public static function importProgress(PlanGenerator $plan): ?array
    {
        $import = $plan->populationImport;

        if ($import === null) {
            return null;
        }

        $processed = (int) $import->processed_rows;

        return [
            'total' => (int) $import->total_rows,
            'processed' => $processed,
            'successful' => (int) $import->successful_rows,
            'failed' => $import->failedRows()->count(),
            'completed' => $import->completed_at !== null,
            'queued' => $processed === 0 && $import->completed_at === null,
        ];
    }

    public static function canContinue(PlanGenerator $plan): bool
    {
        return self::blockedReason($plan) === null;
    }

    /**
     * Motivo por el que todavía no se puede crear la afiliación, o null si se
     * puede. El mismo texto alimenta el tooltip del botón y la notificación.
     */
    public static function blockedReason(PlanGenerator $plan): ?string
    {
        if (self::isImportRunning($plan)) {
            $progress = self::importProgress($plan);

            if ($progress === null) {
                return 'La importación de población está en curso. Espere a que termine.';
            }

            // Encolado y sin una sola fila procesada: casi siempre es que no hay
            // un worker de colas levantado. Decirlo evita que el analista espere
            // indefinidamente frente a una barra que no avanza.
            if ($progress['queued']) {
                return 'La importación de '.$progress['total'].' fila(s) está encolada y todavía no arrancó. '
                    .'Si no avanza, verifique que el worker de colas esté corriendo (php artisan queue:listen).';
            }

            return 'La importación está en curso ('.$progress['processed'].' de '.$progress['total']
                .' filas). Espere a que termine para no crear la afiliación a medio poblar.';
        }

        if (self::importedCount($plan) === 0) {
            return 'Importe el padrón de población antes de continuar.';
        }

        return null;
    }
}
