<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgAssignmentRole;
use App\Enums\OrgPositionKind;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use App\Models\RrhhCargo;
use App\Models\RrhhColaborador;
use App\Models\RrhhDepartamento;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Cambios sobre un borrador del organigrama.
 *
 * Cada operación corre en una transacción que bloquea la fila de la versión y exige:
 * que la versión sea editable, que el usuario tenga el candado de edición y que el
 * `lock_version` que trae la pantalla sea el actual. Si otro guardó antes, se
 * rechaza con OrgStructureConflictException en lugar de pisar su cambio. Todas
 * suben `lock_version` y dejan su línea en la bitácora.
 */
final class OrgStructureEditor
{
    public const MAX_TITLE_LENGTH = 150;

    public const MAX_SEATS = 99;

    /**
     * Colores por área disponibles en la tarjeta. La pantalla los traduce a tonos
     * que funcionan en claro y oscuro.
     *
     * @var list<string>
     */
    public const COLORS = ['navy', 'teal', 'green', 'amber', 'red', 'purple', 'slate'];

    /**
     * @return array{lock_version: int, position_id: int}
     */
    public static function addPosition(int $versionId, User $actor, int $expectedLock, ?int $parentId, string $title, string $kind = 'LINEA', ?int $cargoId = null, ?int $index = null, ?int $departamentoId = null): array
    {
        $title = self::cleanTitle($title);
        $kindEnum = OrgPositionKind::tryFrom($kind) ?? throw new OrgStructureException('Tipo de puesto no válido.');
        $createdId = 0;

        $lock = self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($parentId, $title, $kindEnum, $cargoId, $index, $departamentoId, $actor, &$createdId): void {
            $positions = self::positionsOf($version);

            if ($parentId === null) {
                if ($positions->isNotEmpty()) {
                    throw new OrgStructureException('El organigrama ya tiene un puesto principal. Agrega el nuevo puesto debajo de otro.');
                }

                if ($kindEnum === OrgPositionKind::Staff) {
                    throw new OrgStructureException('El puesto principal no puede ser staff.');
                }
            } else {
                $parent = self::findIn($positions, $parentId);

                if ($parent->kind === OrgPositionKind::Staff) {
                    throw new OrgStructureException('«'.$parent->title.'» es staff: un puesto staff no tiene subordinados.');
                }
            }

            [$cargoId, $cargoDepartamentoId] = self::resolveCargo($cargoId);
            $departamentoId = $cargoDepartamentoId ?? self::resolveDepartamento($departamentoId);

            $position = OrgPosition::query()->create([
                'version_id' => $version->id,
                'position_key' => (string) Str::uuid(),
                'parent_id' => $parentId,
                'rrhh_cargo_id' => $cargoId,
                'rrhh_departamento_id' => $departamentoId,
                'title' => $title,
                'kind' => $kindEnum,
                'seats' => 1,
                'sort_order' => self::nextSortOrder($positions, $parentId),
            ]);

            $createdId = (int) $position->id;

            if ($index !== null && $parentId !== null) {
                self::placeAt($positions, $position, $parentId, $index);
            }

            OrgStructureEventLog::record($version->id, 'position_added', 'Agregó el puesto «'.$title.'»', $actor, $position->position_key, null, self::snapshot($position));
        });

        return ['lock_version' => $lock, 'position_id' => $createdId];
    }

    /**
     * Puesto creado desde la paleta de RRHH: toma el nombre, el cargo y el área del
     * cargo, y trae como titulares a los colaboradores activos con ese cargo que
     * todavía no tienen puesto en este borrador. Si el árbol está vacío, el puesto
     * pasa a ser el principal.
     *
     * @return array{lock_version: int, position_id: int, assigned: int}
     */
    public static function addFromCargo(int $versionId, User $actor, int $expectedLock, ?int $parentId, int $cargoId, ?int $index = null): array
    {
        $createdId = 0;
        $assigned = 0;

        $lock = self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($parentId, $cargoId, $index, $actor, &$createdId, &$assigned): void {
            $positions = self::positionsOf($version);

            if ($parentId === null && $positions->isNotEmpty()) {
                throw new OrgStructureException('Suelta el cargo sobre el puesto del que va a depender.');
            }

            if ($parentId !== null) {
                $parent = self::findIn($positions, $parentId);

                if ($parent->kind === OrgPositionKind::Staff) {
                    throw new OrgStructureException('«'.$parent->title.'» es staff: un puesto staff no tiene subordinados.');
                }
            }

            $cargo = RrhhCargo::query()->find($cargoId, ['id', 'description', 'departamento_id']);

            if ($cargo === null) {
                throw new OrgStructureException('El cargo ya no existe en RRHH.');
            }

            $placedIds = OrgPositionAssignment::query()->active()->where('version_id', $version->id)->pluck('rrhh_colaborador_id')->all();
            $people = OrgStructureRrhh::whereActive(RrhhColaborador::query())
                ->where('cargo_id', (string) $cargo->id)
                ->whereNotIn('id', $placedIds === [] ? [0] : $placedIds)
                ->orderBy('fullName')
                ->limit(self::MAX_SEATS)
                ->get(['id', 'fullName']);

            $position = OrgPosition::query()->create([
                'version_id' => $version->id,
                'position_key' => (string) Str::uuid(),
                'parent_id' => $parentId,
                'rrhh_cargo_id' => (int) $cargo->id,
                'rrhh_departamento_id' => filled($cargo->departamento_id) ? (int) $cargo->departamento_id : null,
                'title' => self::cleanTitle((string) $cargo->description),
                'kind' => OrgPositionKind::Line,
                'seats' => max(1, $people->count()),
                'sort_order' => self::nextSortOrder($positions, $parentId),
            ]);

            $createdId = (int) $position->id;

            if ($index !== null && $parentId !== null) {
                self::placeAt($positions, $position, $parentId, $index);
            }

            foreach ($people as $person) {
                OrgPositionAssignment::query()->create([
                    'version_id' => $version->id,
                    'position_id' => $position->id,
                    'rrhh_colaborador_id' => $person->id,
                    'role' => OrgAssignmentRole::Holder,
                    'starts_on' => now()->toDateString(),
                    'created_by' => $actor->getKey(),
                ]);
            }

            $assigned = $people->count();

            OrgStructureEventLog::record(
                $version->id,
                'position_added',
                'Agregó el puesto «'.$position->title.'» desde el cargo de RRHH'.($assigned > 0 ? ' con '.$assigned.' persona(s)' : ''),
                $actor,
                $position->position_key,
                null,
                self::snapshot($position) + ['colaboradores' => $people->pluck('id')->all()],
            );
        });

        return ['lock_version' => $lock, 'position_id' => $createdId, 'assigned' => $assigned];
    }

    /**
     * @param  array{title?: string, kind?: string, seats?: int|string, color?: string|null, rrhh_cargo_id?: int|string|null, rrhh_departamento_id?: int|string|null}  $attributes
     */
    public static function updatePosition(int $versionId, User $actor, int $expectedLock, int $positionId, array $attributes): int
    {
        return self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($positionId, $attributes, $actor): void {
            $positions = self::positionsOf($version);
            $position = self::findIn($positions, $positionId);
            $before = self::snapshot($position);

            if (array_key_exists('title', $attributes)) {
                $position->title = self::cleanTitle((string) $attributes['title']);
            }

            if (array_key_exists('kind', $attributes)) {
                $kind = OrgPositionKind::tryFrom((string) $attributes['kind']) ?? throw new OrgStructureException('Tipo de puesto no válido.');

                if ($kind === OrgPositionKind::Staff) {
                    if ($position->parent_id === null) {
                        throw new OrgStructureException('El puesto principal no puede ser staff.');
                    }

                    if ($positions->contains(fn (OrgPosition $other): bool => $other->parent_id === $position->id)) {
                        throw new OrgStructureException('Un puesto staff no tiene subordinados. Mueve primero a quienes dependen de «'.$position->title.'».');
                    }
                }

                $position->kind = $kind;
            }

            if (array_key_exists('seats', $attributes)) {
                $seats = filter_var($attributes['seats'], FILTER_VALIDATE_INT);

                if ($seats === false || $seats < 1 || $seats > self::MAX_SEATS) {
                    throw new OrgStructureException('Las plazas deben ser un número entre 1 y '.self::MAX_SEATS.'.');
                }

                $occupied = self::activeAssignmentsCount($position->id);

                if ($seats < $occupied) {
                    throw new OrgStructureException('«'.$position->title.'» tiene '.$occupied.' ocupantes; no puede quedar con '.$seats.' plaza(s). Retira primero a alguien.');
                }

                $position->seats = $seats;
            }

            if (array_key_exists('color', $attributes)) {
                $color = $attributes['color'];

                if ($color !== null && $color !== '' && ! in_array($color, self::COLORS, true)) {
                    throw new OrgStructureException('Color no válido.');
                }

                $position->color = $color === '' ? null : $color;
            }

            if (array_key_exists('rrhh_cargo_id', $attributes)) {
                $cargoId = filled($attributes['rrhh_cargo_id']) ? (int) $attributes['rrhh_cargo_id'] : null;
                [$cargoId, $departamentoId] = self::resolveCargo($cargoId);
                $position->rrhh_cargo_id = $cargoId;

                if ($departamentoId !== null) {
                    $position->rrhh_departamento_id = $departamentoId;
                }
            }

            if (array_key_exists('rrhh_departamento_id', $attributes) && ! array_key_exists('rrhh_cargo_id', $attributes)) {
                $departamentoId = self::resolveDepartamento(filled($attributes['rrhh_departamento_id']) ? (int) $attributes['rrhh_departamento_id'] : null);
                $cargoDepartamentoId = $position->rrhh_cargo_id !== null ? self::resolveCargo($position->rrhh_cargo_id)[1] : null;

                if ($cargoDepartamentoId !== null && $departamentoId !== $cargoDepartamentoId) {
                    throw new OrgStructureException('El área la define el cargo de RRHH de este puesto. Quita el cargo para elegir otra área.');
                }

                $position->rrhh_departamento_id = $departamentoId;
            }

            if (! $position->isDirty()) {
                return;
            }

            $position->save();

            OrgStructureEventLog::record($version->id, 'position_updated', 'Editó el puesto «'.$position->title.'»', $actor, $position->position_key, $before, self::snapshot($position));
        });
    }

    /**
     * Cambia a quién reporta un puesto y/o su orden entre hermanos.
     */
    public static function movePosition(int $versionId, User $actor, int $expectedLock, int $positionId, int $newParentId, int $index): int
    {
        return self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($positionId, $newParentId, $index, $actor): void {
            $positions = self::positionsOf($version);
            $position = self::findIn($positions, $positionId);
            $newParent = self::findIn($positions, $newParentId);

            if ($position->parent_id === null) {
                throw new OrgStructureException('El puesto principal no se puede mover.');
            }

            if ($newParent->id === $position->id || in_array($newParent->id, self::descendantIds($positions, $position->id), true)) {
                throw new OrgStructureException('«'.$position->title.'» no puede quedar bajo «'.$newParent->title.'»: este depende de él.');
            }

            if ($newParent->kind === OrgPositionKind::Staff) {
                throw new OrgStructureException('«'.$newParent->title.'» es staff: un puesto staff no tiene subordinados.');
            }

            $before = ['parent_key' => self::keyOf($positions, $position->parent_id), 'sort_order' => $position->sort_order];
            $oldParentId = $position->parent_id;

            $siblings = $positions
                ->filter(fn (OrgPosition $other): bool => $other->parent_id === $newParent->id && $other->id !== $position->id)
                ->sortBy('sort_order')
                ->values()
                ->all();

            $index = max(0, min($index, count($siblings)));
            array_splice($siblings, $index, 0, [$position]);

            $position->parent_id = $newParent->id;
            self::resequence($siblings);

            if ($oldParentId !== $newParent->id) {
                self::resequence($positions
                    ->filter(fn (OrgPosition $other): bool => $other->parent_id === $oldParentId && $other->id !== $position->id)
                    ->sortBy('sort_order')
                    ->values()
                    ->all());
            }

            $summary = $oldParentId === $newParent->id
                ? 'Reordenó «'.$position->title.'»'
                : 'Movió «'.$position->title.'» bajo «'.$newParent->title.'»';

            OrgStructureEventLog::record($version->id, 'position_moved', $summary, $actor, $position->position_key, $before, [
                'parent_key' => $newParent->position_key,
                'sort_order' => $position->sort_order,
            ]);
        });
    }

    /**
     * Quita un puesto. Sus subordinados suben al jefe del puesto quitado, en el mismo
     * lugar. Un puesto con gente solo se quita si se pide retirar a sus ocupantes en
     * la misma operación; las personas quedan sin puesto, no se borran de RRHH.
     */
    public static function removePosition(int $versionId, User $actor, int $expectedLock, int $positionId, bool $releaseOccupants = false): int
    {
        return self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($positionId, $releaseOccupants, $actor): void {
            $positions = self::positionsOf($version);
            $position = self::findIn($positions, $positionId);

            if ($position->parent_id === null) {
                throw new OrgStructureException('El puesto principal no se puede quitar.');
            }

            $occupants = OrgPositionAssignment::query()
                ->active()
                ->where('position_id', $position->id)
                ->with('colaborador:id,fullName')
                ->get();

            if ($occupants->isNotEmpty()) {
                $names = $occupants->map(fn (OrgPositionAssignment $assignment): string => (string) ($assignment->colaborador?->fullName ?? 'colaborador #'.$assignment->rrhh_colaborador_id))->implode(', ');

                if (! $releaseOccupants) {
                    throw new OrgStructureException('«'.$position->title.'» tiene ocupantes ('.$names.'). Retíralos o reasígnalos antes de quitar el puesto.');
                }

                OrgStructureEventLog::record($version->id, 'occupant_removed', 'Retiró a '.$names.' al quitar «'.$position->title.'»', $actor, $position->position_key, ['colaboradores' => $occupants->pluck('rrhh_colaborador_id')->all()], null);
            }

            $children = $positions->filter(fn (OrgPosition $other): bool => $other->parent_id === $position->id)->sortBy('sort_order')->values()->all();
            $siblings = $positions->filter(fn (OrgPosition $other): bool => $other->parent_id === $position->parent_id)->sortBy('sort_order')->values()->all();
            $ordered = [];

            foreach ($siblings as $sibling) {
                if ($sibling->id === $position->id) {
                    foreach ($children as $child) {
                        $child->parent_id = $position->parent_id;
                        $ordered[] = $child;
                    }

                    continue;
                }

                $ordered[] = $sibling;
            }

            $before = self::snapshot($position);
            OrgPositionAssignment::query()->where('position_id', $position->id)->delete();
            $position->delete();
            self::resequence($ordered);

            OrgStructureEventLog::record(
                $version->id,
                'position_removed',
                'Quitó el puesto «'.$position->title.'»'.(count($children) > 0 ? ' ('.count($children).' subordinado(s) suben un nivel)' : ''),
                $actor,
                $position->position_key,
                $before,
                null,
            );
        });
    }

    /**
     * @return array{lock_version: int, dual: bool}
     */
    public static function assign(int $versionId, User $actor, int $expectedLock, int $positionId, int $colaboradorId, string $role = 'TITULAR'): array
    {
        $roleEnum = OrgAssignmentRole::tryFrom($role) ?? throw new OrgStructureException('Tipo de ocupación no válido.');
        $dual = false;

        $lock = self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($positionId, $colaboradorId, $roleEnum, $actor, &$dual): void {
            $position = self::findIn(self::positionsOf($version), $positionId);
            $colaborador = RrhhColaborador::query()->find($colaboradorId, ['id', 'fullName', 'status']);

            if ($colaborador === null) {
                throw new OrgStructureException('El colaborador ya no existe en RRHH.');
            }

            if (! OrgStructureRrhh::isActive($colaborador)) {
                throw new OrgStructureException((string) $colaborador->fullName.' no está activo en RRHH.');
            }

            $occupants = OrgPositionAssignment::query()->active()->where('position_id', $position->id)->get();

            if ($occupants->contains(fn (OrgPositionAssignment $assignment): bool => $assignment->rrhh_colaborador_id === $colaborador->id)) {
                throw new OrgStructureException((string) $colaborador->fullName.' ya ocupa «'.$position->title.'».');
            }

            if ($occupants->count() >= $position->seats) {
                throw new OrgStructureException('«'.$position->title.'» no tiene plazas libres ('.$position->seats.'). Aumenta las plazas o retira a alguien.');
            }

            $dual = $roleEnum === OrgAssignmentRole::Holder && OrgPositionAssignment::query()
                ->active()
                ->where('version_id', $version->id)
                ->where('rrhh_colaborador_id', $colaborador->id)
                ->where('role', OrgAssignmentRole::Holder->value)
                ->exists();

            OrgPositionAssignment::query()->create([
                'version_id' => $version->id,
                'position_id' => $position->id,
                'rrhh_colaborador_id' => $colaborador->id,
                'role' => $roleEnum,
                'starts_on' => now()->toDateString(),
                'created_by' => $actor->getKey(),
            ]);

            OrgStructureEventLog::record(
                $version->id,
                'occupant_assigned',
                'Asignó a '.$colaborador->fullName.' en «'.$position->title.'» como '.mb_strtolower($roleEnum->label()),
                $actor,
                $position->position_key,
                null,
                ['colaborador_id' => $colaborador->id, 'role' => $roleEnum->value],
            );
        });

        return ['lock_version' => $lock, 'dual' => $dual];
    }

    public static function unassign(int $versionId, User $actor, int $expectedLock, int $assignmentId): int
    {
        return self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($assignmentId, $actor): void {
            $assignment = OrgPositionAssignment::query()
                ->where('version_id', $version->id)
                ->with(['position:id,title,position_key', 'colaborador:id,fullName'])
                ->find($assignmentId);

            if ($assignment === null) {
                throw new OrgStructureException('Esa asignación ya no existe. Se recargará el borrador.');
            }

            $assignment->delete();

            OrgStructureEventLog::record(
                $version->id,
                'occupant_removed',
                'Retiró a '.($assignment->colaborador?->fullName ?? 'colaborador #'.$assignment->rrhh_colaborador_id).' de «'.$assignment->position?->title.'»',
                $actor,
                $assignment->position?->position_key,
                ['colaborador_id' => $assignment->rrhh_colaborador_id, 'role' => $assignment->role->value],
                null,
            );
        });
    }

    /**
     * Retira de un golpe a los ocupantes que ya no están activos en RRHH. Es la
     * corrección rápida que ofrece la pantalla antes de publicar.
     *
     * @return array{lock_version: int, removed: int}
     */
    public static function removeInactiveOccupants(int $versionId, User $actor, int $expectedLock): array
    {
        $removed = 0;

        $lock = self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($actor, &$removed): void {
            $assignments = OrgPositionAssignment::query()
                ->active()
                ->where('version_id', $version->id)
                ->with(['colaborador:id,fullName,status', 'position:id,title,position_key'])
                ->get()
                ->reject(fn (OrgPositionAssignment $assignment): bool => $assignment->colaborador !== null && OrgStructureRrhh::isActive($assignment->colaborador));

            foreach ($assignments as $assignment) {
                $assignment->delete();
                $removed++;

                OrgStructureEventLog::record(
                    $version->id,
                    'occupant_removed',
                    'Retiró a '.($assignment->colaborador?->fullName ?? 'colaborador #'.$assignment->rrhh_colaborador_id).' (inactivo en RRHH) de «'.$assignment->position?->title.'»',
                    $actor,
                    $assignment->position?->position_key,
                    ['colaborador_id' => $assignment->rrhh_colaborador_id],
                    null,
                );
            }
        });

        return ['lock_version' => $lock, 'removed' => $removed];
    }

    public static function rename(int $versionId, User $actor, int $expectedLock, string $name, ?string $notes): int
    {
        $name = trim($name);

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            throw new OrgStructureException('El nombre del borrador debe tener entre 3 y 120 caracteres.');
        }

        return self::mutate($versionId, $actor, $expectedLock, function (OrgStructureVersion $version) use ($name, $notes, $actor): void {
            $before = ['name' => $version->name, 'notes' => $version->notes];
            $version->name = $name;
            $version->notes = filled($notes) ? mb_substr(trim((string) $notes), 0, 2000) : null;

            OrgStructureEventLog::record($version->id, 'draft_renamed', 'Renombró el borrador a «'.$name.'»', $actor, null, $before, ['name' => $name, 'notes' => $version->notes]);
        });
    }

    /**
     * @param  Closure(OrgStructureVersion): void  $change
     */
    private static function mutate(int $versionId, User $actor, int $expectedLock, Closure $change): int
    {
        if (! OrgStructureAccess::canEdit($actor)) {
            throw new OrgStructureException('No tienes permiso para editar la estructura organizacional.');
        }

        return DB::transaction(function () use ($versionId, $actor, $expectedLock, $change): int {
            $version = OrgStructureVersion::query()->lockForUpdate()->find($versionId);

            if ($version === null) {
                throw new OrgStructureException('El borrador ya no existe.');
            }

            if (! $version->isEditable()) {
                throw new OrgStructureException('Este borrador ya no se puede editar: está «'.$version->status->label().'».');
            }

            OrgStructureEditLock::ensure($version->id, $actor);

            if ($version->lock_version !== $expectedLock) {
                throw new OrgStructureConflictException('El borrador cambió desde que lo abriste. Se recargó con la última versión; revisa y repite el cambio.');
            }

            $change($version);

            $version->lock_version = $version->lock_version + 1;
            $version->updated_by = $actor->getKey();
            $version->save();

            return $version->lock_version;
        });
    }

    /**
     * @return Collection<int, OrgPosition>
     */
    private static function positionsOf(OrgStructureVersion $version): Collection
    {
        return OrgPosition::query()->where('version_id', $version->id)->get();
    }

    /**
     * @param  Collection<int, OrgPosition>  $positions
     */
    private static function findIn(Collection $positions, int $positionId): OrgPosition
    {
        $position = $positions->firstWhere('id', $positionId);

        if (! $position instanceof OrgPosition) {
            throw new OrgStructureConflictException('Ese puesto ya no existe en el borrador. Se recargó con la última versión.');
        }

        return $position;
    }

    /**
     * @param  Collection<int, OrgPosition>  $positions
     * @return list<int>
     */
    public static function descendantIds(Collection $positions, int $positionId): array
    {
        $childrenByParent = $positions->groupBy('parent_id');
        $found = [];
        $stack = [$positionId];

        while ($stack !== []) {
            $current = array_pop($stack);

            foreach ($childrenByParent->get($current, []) as $child) {
                if (in_array($child->id, $found, true)) {
                    continue;
                }

                $found[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $found;
    }

    /**
     * @param  Collection<int, OrgPosition>  $positions
     */
    private static function nextSortOrder(Collection $positions, ?int $parentId): int
    {
        $max = $positions->where('parent_id', $parentId)->max('sort_order');

        return $max === null ? 0 : ((int) $max) + 1;
    }

    /**
     * @param  list<OrgPosition>  $ordered
     */
    private static function resequence(array $ordered): void
    {
        foreach ($ordered as $order => $position) {
            if ($position->sort_order !== $order) {
                $position->sort_order = $order;
            }

            if ($position->isDirty()) {
                $position->save();
            }
        }
    }

    /**
     * Ubica un puesto recién creado en la posición pedida entre sus hermanos.
     *
     * @param  Collection<int, OrgPosition>  $positions  hermanos previos (sin el nuevo)
     */
    private static function placeAt(Collection $positions, OrgPosition $position, int $parentId, int $index): void
    {
        $siblings = $positions
            ->filter(fn (OrgPosition $other): bool => $other->parent_id === $parentId)
            ->sortBy('sort_order')
            ->values()
            ->all();

        array_splice($siblings, max(0, min($index, count($siblings))), 0, [$position]);
        self::resequence($siblings);
    }

    private static function resolveDepartamento(?int $departamentoId): ?int
    {
        if ($departamentoId === null) {
            return null;
        }

        if (! RrhhDepartamento::query()->whereKey($departamentoId)->exists()) {
            throw new OrgStructureException('El departamento elegido ya no existe en RRHH.');
        }

        return $departamentoId;
    }

    /**
     * @param  Collection<int, OrgPosition>  $positions
     */
    private static function keyOf(Collection $positions, ?int $positionId): ?string
    {
        return $positionId === null ? null : $positions->firstWhere('id', $positionId)?->position_key;
    }

    private static function activeAssignmentsCount(int $positionId): int
    {
        return OrgPositionAssignment::query()->active()->where('position_id', $positionId)->count();
    }

    private static function cleanTitle(string $title): string
    {
        $title = trim((string) preg_replace('/\s+/u', ' ', $title));

        if (mb_strlen($title) < 2) {
            throw new OrgStructureException('Escribe el nombre del puesto (al menos 2 caracteres).');
        }

        if (mb_strlen($title) > self::MAX_TITLE_LENGTH) {
            throw new OrgStructureException('El nombre del puesto admite hasta '.self::MAX_TITLE_LENGTH.' caracteres.');
        }

        return $title;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private static function resolveCargo(?int $cargoId): array
    {
        if ($cargoId === null) {
            return [null, null];
        }

        $cargo = RrhhCargo::query()->find($cargoId, ['id', 'departamento_id']);

        if ($cargo === null) {
            throw new OrgStructureException('El cargo elegido ya no existe en RRHH.');
        }

        return [(int) $cargo->id, filled($cargo->departamento_id) ? (int) $cargo->departamento_id : null];
    }

    /**
     * @return array<string, mixed>
     */
    private static function snapshot(OrgPosition $position): array
    {
        return [
            'title' => $position->title,
            'kind' => $position->kind instanceof OrgPositionKind ? $position->kind->value : $position->kind,
            'seats' => $position->seats,
            'color' => $position->color,
            'rrhh_cargo_id' => $position->rrhh_cargo_id,
            'rrhh_departamento_id' => $position->rrhh_departamento_id,
            'parent_id' => $position->parent_id,
        ];
    }
}
