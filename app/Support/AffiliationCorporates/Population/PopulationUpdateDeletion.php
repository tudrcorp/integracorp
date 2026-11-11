<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Jobs\SyncCorporatePopulationItemsJob;
use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationOtpChallenge;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Elimina una carga que quedó «Lista para revisar» y nunca se aplicó.
 *
 * Una carga así no cambió la población ni los totales, por eso se puede borrar
 * con sus filas, sus claves OTP y su archivo. Lo único que sobrevive son las
 * correcciones de datos que el analista ya guardó en las fichas desde la tabla
 * de hallazgos: esas no se deshacen (siguen en «Auditoría de cambios»).
 *
 * Queda una traza en la bitácora de seguridad con la razón, quién, el archivo
 * (nombre y huella) y el resumen, para poder explicar después qué se borró.
 */
final class PopulationUpdateDeletion
{
    public const REASON_MIN_LENGTH = 15;

    /**
     * Por qué no se puede eliminar, o null si se puede.
     */
    public static function blockingReason(CorporatePopulationUpdate $update): ?string
    {
        if ($update->status !== CorporatePopulationUpdate::STATUS_ANALYZED) {
            return 'Solo se eliminan cargas «Lista para revisar». Esta está «'.$update->statusLabel().'».';
        }

        if (Cache::has(SyncCorporatePopulationItemsJob::busyKey((int) $update->getKey()))) {
            return 'Hay una sincronización con el archivo en curso en esta carga. Espere el aviso de que terminó.';
        }

        if (CorporateBillingAdjustment::query()->where('corporate_population_update_id', $update->getKey())->exists()) {
            return 'Esta carga tiene ajustes de facturación en Administración y no se puede eliminar.';
        }

        return null;
    }

    /**
     * Correcciones de datos ya guardadas en fichas desde esta carga (no se deshacen al eliminar).
     *
     * @return array{corrections: int, affiliates: list<int>}
     */
    public static function savedCorrections(CorporatePopulationUpdate $update): array
    {
        $corrections = 0;
        $affiliates = [];

        Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('details', 'like', '%"corrections"%')
            ->get(['id', 'details'])
            ->each(static function (Item $item) use (&$corrections, &$affiliates): void {
                foreach ((array) ($item->details['corrections'] ?? []) as $correction) {
                    if (($correction['undone_at'] ?? null) === null) {
                        $corrections++;
                        $affiliates[(int) ($correction['affiliate_id'] ?? 0)] = true;
                    }
                }
            });

        unset($affiliates[0]);

        return ['corrections' => $corrections, 'affiliates' => array_keys($affiliates)];
    }

    public static function delete(CorporatePopulationUpdate $update, User $user, string $reason): void
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique por qué se elimina (mínimo '.self::REASON_MIN_LENGTH.' caracteres).');
        }

        $reason = mb_substr($reason, 0, 1000);

        $trace = DB::transaction(static function () use ($update, $reason): array {
            $locked = CorporatePopulationUpdate::query()->whereKey($update->getKey())->lockForUpdate()->first();
            if (! $locked instanceof CorporatePopulationUpdate) {
                throw new RuntimeException('La carga ya no existe: otra persona la eliminó.');
            }

            $blocking = self::blockingReason($locked);
            if ($blocking !== null) {
                throw new RuntimeException($blocking);
            }

            $saved = self::savedCorrections($locked);
            $trace = [
                'update_id' => (int) $locked->getKey(),
                'public_id' => (string) $locked->public_id,
                'affiliation_corporate_id' => (int) $locked->affiliation_corporate_id,
                'file_name' => (string) $locked->file_name,
                'file_sha1' => (string) $locked->file_sha1,
                'file_disk' => (string) $locked->file_disk,
                'file_path' => (string) $locked->file_path,
                'uploaded_at' => $locked->created_at?->toIso8601String(),
                'analyzed_by' => $locked->analyzed_by,
                'analyzed_by_name' => $locked->analyzed_by_name,
                'counts' => (array) ($locked->summary['counts'] ?? []),
                'items' => Item::query()->where('corporate_population_update_id', $locked->getKey())->count(),
                'saved_corrections' => $saved['corrections'],
                'corrected_affiliates' => $saved['affiliates'],
                'reason' => $reason,
            ];

            CorporatePopulationOtpChallenge::query()->where('corporate_population_update_id', $locked->getKey())->delete();
            Item::query()->where('corporate_population_update_id', $locked->getKey())->delete();
            $locked->delete();

            return $trace;
        });

        self::deleteFile($trace);

        SecurityAudit::log('AUDIT_CORPORATE_POPULATION_DELETED', 'business.corporate-population.delete', $trace, $user);
    }

    /**
     * El archivo trae datos personales de toda la población: no se deja huérfano.
     * Solo se borra si ninguna otra carga apunta a la misma ruta.
     *
     * @param  array<string, mixed>  $trace
     */
    private static function deleteFile(array $trace): void
    {
        if ($trace['file_path'] === '' || CorporatePopulationUpdate::query()->where('file_disk', $trace['file_disk'])->where('file_path', $trace['file_path'])->exists()) {
            return;
        }

        try {
            Storage::disk($trace['file_disk'])->delete($trace['file_path']);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
