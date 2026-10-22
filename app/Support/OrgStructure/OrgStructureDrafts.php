<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgStructureVersionStatus;
use App\Models\OrgPosition;
use App\Models\OrgPositionAssignment;
use App\Models\OrgStructureVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Nacimiento y descarte de borradores.
 *
 * - Desde la vigente: copia exacta, conservando `position_key`, para poder comparar.
 * - Organigrama nuevo: sin puestos; el estudio abre el lienzo con un solo cuadro
 *   para escribir el puesto principal.
 *
 * RRHH nunca arma la estructura por su cuenta: en el estudio es una paleta.
 *
 * Un borrador nunca se borra: se marca DESCARTADO y queda en la bitácora.
 */
final class OrgStructureDrafts
{
    public const MAX_OPEN_DRAFTS = 10;

    public static function createFromCurrent(User $actor, string $name): OrgStructureVersion
    {
        self::assertCanCreate($actor, $name);

        return DB::transaction(function () use ($actor, $name): OrgStructureVersion {
            $current = OrgStructureVersion::query()->current()->lockForUpdate()->first();

            if ($current === null) {
                throw new OrgStructureException('Todavía no hay un organigrama vigente. Usa la carga inicial desde RRHH.');
            }

            $draft = self::newDraft($actor, $name, $current->id);
            $positions = OrgPosition::query()->where('version_id', $current->id)->orderBy('id')->get();
            $childrenByParent = $positions->groupBy(fn (OrgPosition $position): string => (string) ($position->parent_id ?? 'root'));
            $newIds = [];
            $queue = [null];

            while ($queue !== []) {
                $parentId = array_shift($queue);

                foreach ($childrenByParent->get((string) ($parentId ?? 'root'), []) as $position) {
                    $copy = OrgPosition::query()->create([
                        'version_id' => $draft->id,
                        'position_key' => $position->position_key,
                        'parent_id' => $parentId === null ? null : $newIds[$parentId],
                        'rrhh_cargo_id' => $position->rrhh_cargo_id,
                        'rrhh_departamento_id' => $position->rrhh_departamento_id,
                        'title' => $position->title,
                        'kind' => $position->kind,
                        'seats' => $position->seats,
                        'sort_order' => $position->sort_order,
                        'color' => $position->color,
                    ]);

                    $newIds[$position->id] = $copy->id;
                    $queue[] = $position->id;
                }
            }

            $now = now();
            $rows = OrgPositionAssignment::query()
                ->active()
                ->where('version_id', $current->id)
                ->get()
                ->filter(fn (OrgPositionAssignment $assignment): bool => isset($newIds[$assignment->position_id]))
                ->map(fn (OrgPositionAssignment $assignment): array => [
                    'version_id' => $draft->id,
                    'position_id' => $newIds[$assignment->position_id],
                    'rrhh_colaborador_id' => $assignment->rrhh_colaborador_id,
                    'role' => $assignment->role->value,
                    'starts_on' => $assignment->starts_on?->toDateString(),
                    'ends_on' => null,
                    'created_by' => $assignment->created_by,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
                ->values()
                ->all();

            foreach (array_chunk($rows, 500) as $chunk) {
                OrgPositionAssignment::query()->insert($chunk);
            }

            OrgStructureEventLog::record($draft->id, 'draft_created', 'Creó el borrador «'.$draft->name.'» desde la versión vigente «'.$current->name.'»', $actor);

            return $draft;
        });
    }

    /**
     * Borrador sin ningún puesto. Si ya hay vigente, queda basado en ella para que al
     * publicarlo la reemplace y la comparación muestre todo lo que cambia.
     */
    public static function createEmpty(User $actor, string $name): OrgStructureVersion
    {
        self::assertCanCreate($actor, $name);

        return DB::transaction(function () use ($actor, $name): OrgStructureVersion {
            $current = OrgStructureVersion::query()->current()->lockForUpdate()->first(['id']);
            $draft = self::newDraft($actor, $name, $current?->id);

            OrgStructureEventLog::record($draft->id, 'draft_created', 'Creó el organigrama «'.$draft->name.'» con el lienzo vacío', $actor);

            return $draft;
        });
    }

    public static function discard(int $versionId, User $actor): void
    {
        DB::transaction(function () use ($versionId, $actor): void {
            $version = OrgStructureVersion::query()->lockForUpdate()->find($versionId);

            if ($version === null || ! $version->isEditable()) {
                throw new OrgStructureException('Solo se pueden descartar borradores en edición.');
            }

            $isOwner = (int) $version->created_by === (int) $actor->getKey();

            if (! $isOwner && ! OrgStructureAccess::canReview($actor)) {
                throw new OrgStructureException('Solo quien creó el borrador o un SUPERADMIN puede descartarlo.');
            }

            $version->status = OrgStructureVersionStatus::Discarded;
            $version->updated_by = $actor->getKey();
            $version->lock_version = $version->lock_version + 1;
            $version->save();

            OrgStructureEventLog::record($version->id, 'draft_discarded', 'Descartó el borrador «'.$version->name.'»', $actor);
        });

        OrgStructureEditLock::clear($versionId);
    }

    private static function assertCanCreate(User $actor, string $name): void
    {
        if (! OrgStructureAccess::canEdit($actor)) {
            throw new OrgStructureException('No tienes permiso para editar la estructura organizacional.');
        }

        $name = trim($name);

        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            throw new OrgStructureException('El nombre del borrador debe tener entre 3 y 120 caracteres.');
        }

        $open = OrgStructureVersion::query()->whereIn('status', OrgStructureVersionStatus::openValues())->count();

        if ($open >= self::MAX_OPEN_DRAFTS) {
            throw new OrgStructureException('Ya hay '.$open.' borradores abiertos. Publica o descarta alguno antes de crear otro.');
        }
    }

    private static function newDraft(User $actor, string $name, ?int $basedOn): OrgStructureVersion
    {
        return OrgStructureVersion::query()->create([
            'name' => trim($name),
            'status' => OrgStructureVersionStatus::Draft,
            'based_on_version_id' => $basedOn,
            'lock_version' => 0,
            'created_by' => $actor->getKey(),
            'updated_by' => $actor->getKey(),
        ]);
    }
}
