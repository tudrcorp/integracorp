<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Efectos de RRHH sobre el organigrama vigente.
 *
 * Solo se toca la versión vigente: los borradores se revisan al publicar (un
 * ocupante inactivo es un error que la pantalla ofrece corregir de un clic).
 * Nada de esto puede tumbar el guardado de RRHH: si falla, se registra y se sigue.
 */
final class OrgStructureLiveSync
{
    public static function isActiveStatus(mixed $status): bool
    {
        return OrgStructureRrhh::isActiveStatus($status);
    }

    public static function touch(): void
    {
        try {
            OrgStructureCache::bumpRrhhStamp();
        } catch (Throwable $exception) {
            Log::warning('Organigrama: no se pudo invalidar la caché', ['message' => $exception->getMessage()]);
        }
    }

    /**
     * El colaborador egresó o se borró: sus puestos en la vigente quedan vacantes.
     * Las asignaciones se cierran con fecha, no se borran.
     */
    public static function colaboradorLeft(int $colaboradorId, string $name): void
    {
        try {
            $current = OrgStructureVersion::query()->current()->first(['id']);

            if ($current !== null) {
                $assignments = OrgPositionAssignment::query()
                    ->active()
                    ->where('version_id', $current->id)
                    ->where('rrhh_colaborador_id', $colaboradorId)
                    ->with('position:id,title,position_key')
                    ->get();

                foreach ($assignments as $assignment) {
                    $assignment->ends_on = now()->toDateString();
                    $assignment->save();

                    OrgStructureEventLog::record(
                        (int) $current->id,
                        'occupant_left',
                        $name.' ya no está activo en RRHH: «'.$assignment->position?->title.'» queda vacante',
                        auth()->user(),
                        $assignment->position?->position_key,
                        ['colaborador_id' => $colaboradorId, 'role' => $assignment->role->value],
                        null,
                    );
                }
            }
        } catch (Throwable $exception) {
            Log::error('Organigrama: no se pudo liberar el puesto de un colaborador inactivo', [
                'colaborador_id' => $colaboradorId,
                'message' => $exception->getMessage(),
            ]);
        }

        self::touch();
    }
}
