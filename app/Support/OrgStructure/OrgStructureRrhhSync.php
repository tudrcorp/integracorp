<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgAssignmentRole;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use App\Models\RrhhColaborador;
use App\Models\User;

/**
 * Opcional al publicar: llevar a RRHH el cargo y departamento de quienes cambiaron
 * de puesto. Solo se toca a titulares de un único puesto que tenga cargo de RRHH;
 * si alguien es titular de dos puestos con cargos distintos no se adivina, se
 * informa y se deja como está.
 */
final class OrgStructureRrhhSync
{
    /**
     * @return array{changes: list<array{colaborador_id: int, name: string, from_cargo: string|null, to_cargo: string, to_cargo_id: int, from_depto: string|null, to_depto: string|null, to_depto_id: int|null}>, skipped: list<string>}
     */
    public static function preview(OrgStructureVersion $version): array
    {
        $assignments = OrgPositionAssignment::query()
            ->active()
            ->where('version_id', $version->id)
            ->where('role', OrgAssignmentRole::Holder->value)
            ->get(['id', 'position_id', 'rrhh_colaborador_id']);

        $positions = OrgPosition::query()
            ->where('version_id', $version->id)
            ->whereIn('id', $assignments->pluck('position_id')->unique()->all())
            ->get(['id', 'title', 'rrhh_cargo_id', 'rrhh_departamento_id'])
            ->keyBy('id');

        $colaboradores = RrhhColaborador::query()
            ->whereIn('id', $assignments->pluck('rrhh_colaborador_id')->unique()->all())
            ->get(['id', 'fullName', 'cargo_id', 'departmento_id', 'status'])
            ->keyBy('id');

        $cargos = OrgStructureRrhh::cargoNames();
        $departamentos = OrgStructureRrhh::departamentoNames();
        $changes = [];
        $skipped = [];

        foreach ($assignments->groupBy('rrhh_colaborador_id') as $colaboradorId => $group) {
            $colaborador = $colaboradores->get($colaboradorId);

            if ($colaborador === null || ! OrgStructureRrhh::isActive($colaborador)) {
                continue;
            }

            $targets = $group
                ->map(fn (OrgPositionAssignment $assignment): ?OrgPosition => $positions->get($assignment->position_id))
                ->filter(fn (?OrgPosition $position): bool => $position?->rrhh_cargo_id !== null)
                ->unique(fn (OrgPosition $position): int => (int) $position->rrhh_cargo_id)
                ->values();

            if ($targets->isEmpty()) {
                continue;
            }

            if ($targets->count() > 1) {
                $skipped[] = $colaborador->fullName.' es titular de puestos con cargos distintos; su cargo en RRHH no se toca.';

                continue;
            }

            /** @var OrgPosition $target */
            $target = $targets->first();
            $toCargo = (int) $target->rrhh_cargo_id;
            $toDepto = $target->rrhh_departamento_id !== null ? (int) $target->rrhh_departamento_id : null;
            $fromCargo = filled($colaborador->cargo_id) ? (int) $colaborador->cargo_id : null;
            $fromDepto = filled($colaborador->departmento_id) ? (int) $colaborador->departmento_id : null;

            if ($fromCargo === $toCargo && ($toDepto === null || $fromDepto === $toDepto)) {
                continue;
            }

            $changes[] = [
                'colaborador_id' => (int) $colaborador->id,
                'name' => (string) $colaborador->fullName,
                'from_cargo' => $fromCargo !== null ? ($cargos[$fromCargo] ?? 'Cargo #'.$fromCargo) : null,
                'to_cargo' => $cargos[$toCargo] ?? 'Cargo #'.$toCargo,
                'to_cargo_id' => $toCargo,
                'from_depto' => $fromDepto !== null ? ($departamentos[$fromDepto] ?? null) : null,
                'to_depto' => $toDepto !== null ? ($departamentos[$toDepto] ?? null) : null,
                'to_depto_id' => $toDepto,
            ];
        }

        return ['changes' => $changes, 'skipped' => $skipped];
    }

    /**
     * Aplica la vista previa. Corre dentro de la transacción de publicación.
     *
     * @return int cantidad de colaboradores actualizados
     */
    public static function apply(OrgStructureVersion $version, User $actor): int
    {
        $preview = self::preview($version);
        $updated = 0;

        foreach ($preview['changes'] as $change) {
            $colaborador = RrhhColaborador::query()->lockForUpdate()->find($change['colaborador_id']);

            if ($colaborador === null) {
                continue;
            }

            $before = ['cargo_id' => $colaborador->cargo_id, 'departmento_id' => $colaborador->departmento_id];
            $colaborador->cargo_id = (string) $change['to_cargo_id'];

            if ($change['to_depto_id'] !== null) {
                $colaborador->departmento_id = (string) $change['to_depto_id'];
            }

            $colaborador->updated_by = (string) ($actor->name ?? $colaborador->updated_by);
            $colaborador->save();
            $updated++;

            OrgStructureEventLog::record(
                $version->id,
                'rrhh_synced',
                'Actualizó en RRHH el cargo de '.$change['name'].' a «'.$change['to_cargo'].'»',
                $actor,
                null,
                $before,
                ['cargo_id' => $colaborador->cargo_id, 'departmento_id' => $colaborador->departmento_id],
            );
        }

        return $updated;
    }
}
