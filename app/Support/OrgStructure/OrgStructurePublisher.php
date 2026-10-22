<?php

declare(strict_types=1);

namespace App\Support\OrgStructure;

use App\Enums\OrgStructureVersionStatus;
use App\Jobs\NotifyOrgStructureReviewJob;
use App\Models\OrgStructureVersion;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Cómo un borrador pasa a ser el organigrama oficial.
 *
 * - Un SUPERADMIN publica directo, sin aprobación.
 * - Cualquier otro editor lo envía a revisión; un SUPERADMIN lo aprueba (y se
 *   publica) o lo devuelve con motivo. Quien lo envía no puede aprobarlo.
 *
 * Publicar corre bajo un candado global y una transacción: nunca quedan dos
 * vigentes, la anterior pasa a ARCHIVADA y, si se pidió, RRHH se actualiza en la
 * misma transacción. Si el vigente cambió desde que nació el borrador, no se
 * publica: se pisaría lo publicado por otro.
 */
final class OrgStructurePublisher
{
    private const PUBLISH_LOCK = 'org-structure:publish';

    public static function submitForReview(int $versionId, User $actor): OrgStructureVersion
    {
        if (! OrgStructureAccess::canEdit($actor)) {
            throw new OrgStructureException('No tienes permiso para enviar borradores a aprobación.');
        }

        $version = DB::transaction(function () use ($versionId, $actor): OrgStructureVersion {
            $version = self::lockedVersion($versionId);

            if (! $version->isEditable()) {
                throw new OrgStructureException('Este borrador ya está «'.$version->status->label().'».');
            }

            self::assertBaseIsStillCurrent($version);
            self::assertPublishable($version);

            $version->status = OrgStructureVersionStatus::InReview;
            $version->submitted_by = $actor->getKey();
            $version->submitted_at = now();
            $version->review_notes = null;
            $version->lock_version = $version->lock_version + 1;
            $version->updated_by = $actor->getKey();
            $version->save();

            OrgStructureEventLog::record($version->id, 'submitted', 'Envió el borrador a aprobación', $actor);

            NotifyOrgStructureReviewJob::dispatch($version->id, NotifyOrgStructureReviewJob::SUBMITTED, (int) $actor->getKey())->afterCommit();

            return $version;
        });

        OrgStructureEditLock::clear($versionId);

        return $version;
    }

    /**
     * Publica directo un borrador propio o ajeno en edición. Solo SUPERADMIN.
     *
     * @return array{version: OrgStructureVersion, rrhh_updated: int}
     */
    public static function publishDirectly(int $versionId, User $actor, bool $syncRrhh): array
    {
        if (! OrgStructureAccess::canPublishDirectly($actor)) {
            throw new OrgStructureException('Solo un SUPERADMIN puede publicar sin aprobación. Envía el borrador a aprobación.');
        }

        return self::publish($versionId, $actor, $syncRrhh, fn (OrgStructureVersion $version): bool => $version->isEditable(), 'published');
    }

    /**
     * @return array{version: OrgStructureVersion, rrhh_updated: int}
     */
    public static function approve(int $versionId, User $reviewer, bool $syncRrhh): array
    {
        if (! OrgStructureAccess::canReview($reviewer)) {
            throw new OrgStructureException('Solo un SUPERADMIN puede aprobar el organigrama.');
        }

        $result = self::publish($versionId, $reviewer, $syncRrhh, function (OrgStructureVersion $version) use ($reviewer): bool {
            if ($version->status !== OrgStructureVersionStatus::InReview) {
                return false;
            }

            if ((int) $version->submitted_by === (int) $reviewer->getKey()) {
                throw new OrgStructureException('Quien envía un borrador no puede aprobarlo.');
            }

            return true;
        }, 'approved');

        NotifyOrgStructureReviewJob::dispatch($versionId, NotifyOrgStructureReviewJob::APPROVED, (int) $reviewer->getKey());

        return $result;
    }

    public static function reject(int $versionId, User $reviewer, string $reason): OrgStructureVersion
    {
        if (! OrgStructureAccess::canReview($reviewer)) {
            throw new OrgStructureException('Solo un SUPERADMIN puede devolver un borrador.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new OrgStructureException('Explica en al menos 10 caracteres qué hay que corregir.');
        }

        return DB::transaction(function () use ($versionId, $reviewer, $reason): OrgStructureVersion {
            $version = self::lockedVersion($versionId);

            if ($version->status !== OrgStructureVersionStatus::InReview) {
                throw new OrgStructureException('Este borrador ya no está en revisión.');
            }

            $version->status = OrgStructureVersionStatus::Returned;
            $version->review_notes = mb_substr($reason, 0, 2000);
            $version->reviewed_by = $reviewer->getKey();
            $version->reviewed_at = now();
            $version->lock_version = $version->lock_version + 1;
            $version->save();

            OrgStructureEventLog::record($version->id, 'returned', 'Devolvió el borrador: '.$reason, $reviewer);

            NotifyOrgStructureReviewJob::dispatch($version->id, NotifyOrgStructureReviewJob::RETURNED, (int) $reviewer->getKey())->afterCommit();

            return $version;
        });
    }

    /**
     * Lo que pasaría al publicar: errores que lo impiden y a quién tocaría en RRHH.
     *
     * @return array{errors: list<string>, warnings: list<string>, rrhh: array{changes: list<array<string, mixed>>, skipped: list<string>}, base_changed: bool}
     */
    public static function preview(OrgStructureVersion $version): array
    {
        $payload = OrgChartPayload::build($version);

        return [
            'errors' => OrgStructureValidator::errorMessages($payload['issues']),
            'warnings' => array_values(array_map(
                fn (array $issue): string => $issue['message'],
                array_filter($payload['issues'], fn (array $issue): bool => $issue['level'] === 'warning'),
            )),
            'rrhh' => OrgStructureRrhhSync::preview($version),
            'base_changed' => ! self::baseIsStillCurrent($version),
        ];
    }

    /**
     * @param  callable(OrgStructureVersion): bool  $canPublishFromState
     * @return array{version: OrgStructureVersion, rrhh_updated: int}
     */
    private static function publish(int $versionId, User $actor, bool $syncRrhh, callable $canPublishFromState, string $action): array
    {
        try {
            $result = Cache::lock(self::PUBLISH_LOCK, 60)->block(15, function () use ($versionId, $actor, $syncRrhh, $canPublishFromState, $action): array {
                return DB::transaction(function () use ($versionId, $actor, $syncRrhh, $canPublishFromState, $action): array {
                    $version = self::lockedVersion($versionId);

                    if (! $canPublishFromState($version)) {
                        throw new OrgStructureException('Este borrador no se puede publicar: está «'.$version->status->label().'».');
                    }

                    $previous = OrgStructureVersion::query()->current()->lockForUpdate()->first();

                    self::assertBaseIsStillCurrent($version, $previous);
                    self::assertPublishable($version);

                    $rrhhUpdated = $syncRrhh ? OrgStructureRrhhSync::apply($version, $actor) : 0;

                    if ($previous !== null) {
                        $previous->status = OrgStructureVersionStatus::Archived;
                        $previous->current_marker = null;
                        $previous->archived_at = now();
                        $previous->lock_version = $previous->lock_version + 1;
                        $previous->save();

                        OrgStructureEventLog::record($previous->id, 'archived', 'Pasó al historial al publicarse «'.$version->name.'»', $actor);
                    }

                    $version->status = OrgStructureVersionStatus::Current;
                    $version->current_marker = 1;
                    $version->published_by = $actor->getKey();
                    $version->published_at = now();

                    if ($action === 'approved') {
                        $version->reviewed_by = $actor->getKey();
                        $version->reviewed_at = now();
                    }

                    $version->lock_version = $version->lock_version + 1;
                    $version->updated_by = $actor->getKey();
                    $version->save();

                    OrgStructureEventLog::record(
                        $version->id,
                        $action,
                        ($action === 'approved' ? 'Aprobó y publicó' : 'Publicó').' el organigrama'.($rrhhUpdated > 0 ? ' y actualizó '.$rrhhUpdated.' cargo(s) en RRHH' : ''),
                        $actor,
                    );

                    return ['version' => $version, 'rrhh_updated' => $rrhhUpdated];
                });
            });
        } catch (LockTimeoutException) {
            throw new OrgStructureException('Se está publicando otra versión en este momento. Intenta de nuevo en unos segundos.');
        }

        OrgStructureCache::forgetCurrent();
        OrgStructureEditLock::clear($versionId);

        if ($result['rrhh_updated'] > 0) {
            OrgStructureCache::bumpRrhhStamp();
        }

        return $result;
    }

    private static function lockedVersion(int $versionId): OrgStructureVersion
    {
        $version = OrgStructureVersion::query()->lockForUpdate()->find($versionId);

        if ($version === null) {
            throw new OrgStructureException('El borrador ya no existe.');
        }

        return $version;
    }

    private static function assertPublishable(OrgStructureVersion $version): void
    {
        $errors = OrgStructureValidator::errorMessages(OrgChartPayload::compute($version)['issues']);

        if ($errors !== []) {
            $extra = count($errors) > 3 ? ' (y '.(count($errors) - 3).' más)' : '';

            throw new OrgStructureException('Corrige antes de publicar: '.implode(' ', array_slice($errors, 0, 3)).$extra);
        }
    }

    private static function assertBaseIsStillCurrent(OrgStructureVersion $version, ?OrgStructureVersion $current = null): void
    {
        if (! self::baseIsStillCurrent($version, $current)) {
            throw new OrgStructureException('El organigrama vigente cambió desde que se creó este borrador. Crea un borrador nuevo desde la versión vigente y repite los cambios.');
        }
    }

    private static function baseIsStillCurrent(OrgStructureVersion $version, ?OrgStructureVersion $current = null): bool
    {
        $current ??= OrgStructureVersion::query()->current()->first(['id']);

        if ($current === null) {
            return true;
        }

        return $version->based_on_version_id !== null && (int) $version->based_on_version_id === (int) $current->id;
    }
}
