<?php

declare(strict_types=1);

namespace App\Support\ProspectAgents;

use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Eliminación lógica de prospectos de captación: exige motivo, deja quién y cuándo,
 * y anota el hecho en la bitácora del prospecto. Contactos, sucursales, zonas, tareas
 * y notas no se tocan, para que vuelvan intactos al restaurar.
 */
final class ProspectAgentDeletion
{
    public const MIN_REASON_LENGTH = 10;

    public const MAX_REASON_LENGTH = 1000;

    /**
     * @param  iterable<ProspectAgent>  $prospects
     * @return int Prospectos eliminados (los que ya estaban eliminados se omiten).
     */
    public static function delete(iterable $prospects, string $reason, string $actorName): int
    {
        $reason = self::normalizeReason($reason);

        return DB::transaction(function () use ($prospects, $reason, $actorName): int {
            $deleted = 0;

            foreach ($prospects as $prospect) {
                if ($prospect->trashed()) {
                    continue;
                }

                $prospect->forceFill([
                    'deleted_by' => $actorName,
                    'deletion_reason' => $reason,
                ])->saveQuietly();

                $prospect->delete();

                self::log($prospect, 'Prospecto eliminado. Motivo: '.$reason, $actorName);

                $deleted++;
            }

            return $deleted;
        });
    }

    /**
     * @param  iterable<ProspectAgent>  $prospects
     * @return int Prospectos restaurados (los que no estaban eliminados se omiten).
     */
    public static function restore(iterable $prospects, string $actorName): int
    {
        return DB::transaction(function () use ($prospects, $actorName): int {
            $restored = 0;

            foreach ($prospects as $prospect) {
                if (! $prospect->trashed()) {
                    continue;
                }

                $previousReason = trim((string) $prospect->deletion_reason);
                $previousActor = trim((string) $prospect->deleted_by);

                $prospect->restore();

                $prospect->forceFill([
                    'deleted_by' => null,
                    'deletion_reason' => null,
                ])->saveQuietly();

                $note = 'Prospecto restaurado.';

                if ($previousReason !== '') {
                    $note .= ' Había sido eliminado'
                        .($previousActor !== '' ? ' por '.$previousActor : '')
                        .' con el motivo: '.$previousReason;
                }

                self::log($prospect, $note, $actorName);

                $restored++;
            }

            return $restored;
        });
    }

    public static function normalizeReason(string $reason): string
    {
        $reason = trim(preg_replace('/\s+/u', ' ', $reason) ?? '');

        if (mb_strlen($reason) < self::MIN_REASON_LENGTH) {
            throw new InvalidArgumentException('El motivo de eliminación debe tener al menos '.self::MIN_REASON_LENGTH.' caracteres.');
        }

        return mb_substr($reason, 0, self::MAX_REASON_LENGTH);
    }

    private static function log(ProspectAgent $prospect, string $note, string $actorName): void
    {
        ProspectAgentObservation::query()->create([
            'prospect_agent_id' => $prospect->getKey(),
            'observation' => $note,
            'created_by' => $actorName,
        ]);
    }
}
