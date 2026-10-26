<?php

declare(strict_types=1);

namespace App\Support\Affiliations\Deletion;

use App\Enums\PersonIdentitySource;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateObservation;
use App\Models\AffiliationDeletion;
use App\Models\AffiliationObservation;
use App\Models\Collection as CollectionModel;
use App\Models\CollectionAdjustment;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\User;
use App\Services\TelemedicineCaseLogicalDeletionService;
use App\Support\Affiliations\PersonIdentity\PersonIdentityGuard;
use App\Support\Affiliations\PersonIdentity\PersonIdentityRecord;
use App\Support\Collections\CollectionAdjuster;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Filament\UserNavigationAccess;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseDeletion;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Eliminación reversible de una afiliación individual o corporativa con todos sus afiliados.
 *
 * Nunca se borra nada: la afiliación y sus afiliados quedan ocultos (`deleted_at`) en todo el
 * sistema, las cuotas por pagar se anulan, los pacientes de telemedicina se desvinculan (o pasan a
 * la afiliación vigente de la misma persona) y sus casos cerrados se ocultan con la eliminación
 * lógica de casos. Todo en una transacción; `affiliation_deletions` guarda la foto exacta y la
 * restauración la deshace todo o nada.
 *
 * Exige el permiso {@see BusinessFilamentActionPermissionRegistry::DELETE_AFFILIATIONS} (Negocios o
 * Administración) y un motivo. Antes de tocar nada vuelve a correr {@see AffiliationDeletionInspector}
 * con la afiliación bloqueada: si algo cambió desde que el analista revisó, no elimina.
 */
final class AffiliationDeletionService
{
    public const REASON_MIN_LENGTH = 15;

    public const PATIENT_UNLINKED_STATUS = 'AFILIACION ELIMINADA';

    /**
     * Columnas del paciente que lo atan a una afiliación: se guardan antes y después.
     *
     * @var list<string>
     */
    public const PATIENT_LINK_COLUMNS = [
        'afilliation_id',
        'afilliation_corporate_id',
        'code_affiliation',
        'type_affiliation',
        'status_affiliation',
        'name_corporate',
        'plan_id',
        'coverage_id',
        'business_unit_id',
        'specific_business_unit',
        'business_line_id',
    ];

    public static function userCan(?User $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        foreach (BusinessFilamentActionPermissionRegistry::modulesForSlug(BusinessFilamentActionPermissionRegistry::DELETE_AFFILIATIONS) as $module) {
            if (UserNavigationAccess::canPerformModuleAction($user, $module, BusinessFilamentActionPermissionRegistry::DELETE_AFFILIATIONS)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{deletion: AffiliationDeletion, lines: list<string>}
     */
    public static function delete(Affiliation|AffiliationCorporate $affiliation, string $reason, User $actor): array
    {
        self::assertCan($actor);
        $reason = self::assertReason($reason);

        return DB::transaction(function () use ($affiliation, $reason, $actor): array {
            /** @var Affiliation|AffiliationCorporate|null $locked */
            $locked = $affiliation::query()->withTrashed()->lockForUpdate()->find($affiliation->getKey());

            if ($locked === null) {
                throw new RuntimeException('La afiliación ya no existe.');
            }

            if ($locked->trashed()) {
                throw new RuntimeException('La afiliación '.$locked->code.' ya estaba eliminada.');
            }

            $subject = AffiliationDeletionSubject::for($locked);
            $inspection = AffiliationDeletionInspector::inspect($subject);

            if (! $inspection['ok']) {
                throw new RuntimeException('No se eliminó nada: algo cambió desde la revisión. '.self::failedChecksText($inspection['checks']));
            }

            $caseReason = 'Eliminación de '.$subject->label().': '.$reason;
            $collections = self::cancelCollections($subject, $reason, $actor);
            $patients = self::detachPatients($subject, $inspection['patients']);
            $cases = self::hideCases($inspection['patients'], $caseReason);

            $affiliateIds = [];
            foreach ($subject->affiliatesQuery()->lockForUpdate()->get() as $affiliate) {
                $affiliate->delete();
                $affiliateIds[] = (int) $affiliate->getKey();
            }

            $lines = self::deletionLines($subject, count($affiliateIds), $collections, $patients, $cases, $inspection['patients']);

            self::observation($subject, 'AFILIACIÓN ELIMINADA por '.$actor->name.'. Motivo: '.$reason.' · '.implode(' · ', $lines), $actor);

            $locked->delete();

            $deletion = AffiliationDeletion::query()->create([
                'affiliation_type' => $subject->type,
                'affiliation_id' => $subject->id(),
                'affiliation_code' => $subject->code(),
                'affiliation_name' => Str::limit($subject->name(), 250, ''),
                'status' => AffiliationDeletion::STATUS_DELETED,
                'reason' => $reason,
                'snapshot' => [
                    'affiliation_status' => (string) $locked->status,
                    'affiliate_ids' => $affiliateIds,
                    'collections' => $collections,
                    'patients' => $patients,
                    'cases' => $cases,
                ],
                'summary' => $lines,
                'deleted_by' => $actor->getKey(),
                'deleted_by_name' => $actor->name,
                'deleted_at_moment' => now(),
            ]);

            SecurityAudit::log('AUDIT_AFFILIATION_DELETED', 'affiliations.delete', [
                'deletion_id' => $deletion->getKey(),
                'type' => $subject->type,
                'affiliation_id' => $subject->id(),
                'affiliation_code' => $subject->code(),
                'reason' => $reason,
                'affiliate_ids' => $affiliateIds,
                'collections' => $collections,
                'patients' => $patients,
                'cases' => $cases,
            ], $actor);

            return ['deletion' => $deletion, 'lines' => $lines];
        });
    }

    /**
     * La eliminación vigente de una afiliación oculta, si la hay.
     */
    public static function activeDeletionFor(Affiliation|AffiliationCorporate $affiliation): ?AffiliationDeletion
    {
        return AffiliationDeletion::query()
            ->where('affiliation_type', AffiliationDeletionSubject::for($affiliation)->type)
            ->where('affiliation_id', $affiliation->getKey())
            ->where('status', AffiliationDeletion::STATUS_DELETED)
            ->latest('id')
            ->first();
    }

    /**
     * Revisión antes de restaurar: qué vuelve y qué lo impediría. Solo lee.
     *
     * @return array{ok: bool, blockers: list<string>, warnings: list<string>, lines: list<string>}
     */
    public static function inspectRestore(AffiliationDeletion $deletion): array
    {
        $blockers = [];
        $warnings = [];
        $snapshot = (array) $deletion->snapshot;
        $modelClass = AffiliationDeletionSubject::modelClassFor((string) $deletion->affiliation_type);
        $affiliation = $modelClass::query()->withTrashed()->find($deletion->affiliation_id);

        if ($deletion->status !== AffiliationDeletion::STATUS_DELETED) {
            $blockers[] = 'Esta eliminación ya se revirtió el '.($deletion->restored_at?->format('d/m/Y H:i') ?? '').' por '.($deletion->restored_by_name ?? '—').'.';
        }

        if ($affiliation === null) {
            return ['ok' => false, 'blockers' => [...$blockers, 'La afiliación ya no existe en la base de datos.'], 'warnings' => [], 'lines' => []];
        }

        if (! $affiliation->trashed()) {
            $blockers[] = 'La afiliación '.$affiliation->code.' ya está visible: alguien la restauró por otra vía.';
        }

        $subject = AffiliationDeletionSubject::for($affiliation);

        if ($modelClass::query()->where('code', $subject->code())->whereKeyNot($subject->id())->exists()) {
            $blockers[] = 'Otra afiliación vigente usa ahora el código '.$subject->code().'. No se puede restaurar sin mezclar su cobranza: revíselo con Sistemas.';
        }

        $affiliates = $subject->affiliatesQuery(withTrashed: true)->whereIn('id', (array) ($snapshot['affiliate_ids'] ?? []) ?: [0])->get();

        foreach ($affiliates as $affiliate) {
            $record = PersonIdentityRecord::fromModel($affiliate, $affiliation);

            if (! $record->isActive()) {
                continue;
            }

            foreach (PersonIdentityGuard::conflictsFor($record) as $conflict) {
                if (self::isSameAffiliation($subject, $conflict['existing'])) {
                    continue;
                }

                $blockers[] = '«'.AffiliationDeletionSubject::affiliateName($affiliate).'» ya está vigente como '.Str::lcfirst($conflict['existing']->whereDescription())
                    .'. Una persona no puede estar vigente dos veces: dé de baja ese registro o resuélvalo en Negocios → Personas duplicadas.';
            }
        }

        foreach ((array) ($snapshot['collections'] ?? []) as $item) {
            $collection = CollectionModel::query()->find((int) $item['id']);

            if ($collection === null) {
                $warnings[] = 'El aviso de cobro '.$item['invoice'].' ya no existe; no se puede restituir.';

                continue;
            }

            $lastAdjustment = (int) CollectionAdjustment::query()->where('collection_id', $collection->getKey())->max('id');

            if (Str::upper(trim((string) $collection->status)) !== CollectionAdjuster::STATUS_CANCELLED || $lastAdjustment !== (int) $item['adjustment_id']) {
                $blockers[] = 'El aviso de cobro '.$item['invoice'].' cambió después de la eliminación (hoy: '.mb_strtolower((string) $collection->status).'). Revíselo en Gestión de Cobranza antes de restaurar.';
            }
        }

        foreach ((array) ($snapshot['patients'] ?? []) as $item) {
            $patient = TelemedicinePatient::query()->find((int) $item['id']);

            if ($patient === null) {
                $warnings[] = 'El paciente '.$item['name'].' ya no existe en Telemedicina.';

                continue;
            }

            foreach ((array) $item['after'] as $column => $value) {
                if ((string) ($patient->getAttribute($column) ?? '') !== (string) ($value ?? '')) {
                    $blockers[] = 'El paciente '.$item['name'].' cambió de afiliación después de la eliminación (hoy: '.((string) $patient->code_affiliation ?: 'sin afiliación').'). Revíselo con Telemedicina antes de restaurar.';

                    break;
                }
            }
        }

        foreach ((array) ($snapshot['cases'] ?? []) as $item) {
            $case = TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $item['id']);

            if ($case === null || ! TelemedicineCaseDeletion::isDeleted($case)) {
                $warnings[] = 'El caso '.$item['code'].' ya no está oculto (alguien lo restauró): se deja como está.';
            }
        }

        $lines = [
            'La afiliación '.$subject->code().' ('.$subject->name().') vuelve con el estatus que tenía: '.mb_strtolower((string) ($snapshot['affiliation_status'] ?? $affiliation->status)).'.',
            $affiliates->count().' afiliado(s) vuelven a estar visibles, cada uno con su estatus de antes.',
        ];

        if (($snapshot['collections'] ?? []) !== []) {
            $lines[] = count($snapshot['collections']).' aviso(s) de cobro vuelven a «por pagar».';
        }

        if (($snapshot['patients'] ?? []) !== []) {
            $lines[] = count($snapshot['patients']).' paciente(s) de telemedicina vuelven a quedar asociados a esta afiliación.';
        }

        if (($snapshot['cases'] ?? []) !== []) {
            $lines[] = count($snapshot['cases']).' caso(s) clínico(s) cerrado(s) vuelven a verse, y sus cupos se vuelven a descontar.';
        }

        return ['ok' => $blockers === [], 'blockers' => array_values(array_unique($blockers)), 'warnings' => $warnings, 'lines' => $lines];
    }

    /**
     * @return array{lines: list<string>, warnings: list<string>}
     */
    public static function restore(AffiliationDeletion $deletion, string $reason, User $actor): array
    {
        self::assertCan($actor);
        $reason = self::assertReason($reason);

        return DB::transaction(function () use ($deletion, $reason, $actor): array {
            $locked = AffiliationDeletion::query()->lockForUpdate()->findOrFail($deletion->getKey());
            $modelClass = AffiliationDeletionSubject::modelClassFor((string) $locked->affiliation_type);
            $affiliation = $modelClass::query()->withTrashed()->lockForUpdate()->find($locked->affiliation_id);
            $inspection = self::inspectRestore($locked);

            if (! $inspection['ok'] || $affiliation === null) {
                throw new RuntimeException('No se restauró nada. '.implode(' ', $inspection['blockers']));
            }

            $snapshot = (array) $locked->snapshot;
            $subject = AffiliationDeletionSubject::for($affiliation);
            $warnings = $inspection['warnings'];

            $affiliation->restore();

            foreach ($subject->affiliatesQuery(withTrashed: true)->whereIn('id', (array) ($snapshot['affiliate_ids'] ?? []) ?: [0])->lockForUpdate()->get() as $affiliate) {
                $affiliate->restore();
            }

            foreach ((array) ($snapshot['collections'] ?? []) as $item) {
                $collection = CollectionModel::query()->lockForUpdate()->find((int) $item['id']);

                if ($collection !== null) {
                    self::changeCollectionStatus($collection, (string) ($item['status_before'] ?? CollectionAdjuster::STATUS_PENDING), 'Restauración de '.$subject->label().': '.$reason, $actor, (string) $locked->getKey());
                }
            }

            foreach ((array) ($snapshot['patients'] ?? []) as $item) {
                $patient = TelemedicinePatient::query()->lockForUpdate()->find((int) $item['id']);

                if ($patient !== null) {
                    $patient->forceFill((array) $item['before'])->save();
                    TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->getKey());
                }
            }

            $service = app(TelemedicineCaseLogicalDeletionService::class);

            foreach ((array) ($snapshot['cases'] ?? []) as $item) {
                $case = TelemedicineCaseLogicalDeletionService::findIncludingDeleted((int) $item['id']);

                if ($case instanceof TelemedicineCase && TelemedicineCaseDeletion::isDeleted($case)) {
                    $result = $service->restore($case, 'Restauración de '.$subject->label().': '.$reason);
                    $warnings = [...$warnings, ...(array) ($result['quota_warnings'] ?? [])];
                }
            }

            $lines = $inspection['lines'];

            self::observation($subject, 'AFILIACIÓN RESTAURADA por '.$actor->name.'. Motivo: '.$reason, $actor);

            $locked->forceFill([
                'status' => AffiliationDeletion::STATUS_RESTORED,
                'restoration_reason' => $reason,
                'restoration_summary' => ['lines' => $lines, 'warnings' => $warnings],
                'restored_by' => $actor->getKey(),
                'restored_by_name' => $actor->name,
                'restored_at' => now(),
            ])->save();

            SecurityAudit::log('AUDIT_AFFILIATION_RESTORED', 'affiliations.restore', [
                'deletion_id' => $locked->getKey(),
                'type' => $subject->type,
                'affiliation_id' => $subject->id(),
                'affiliation_code' => $subject->code(),
                'reason' => $reason,
                'warnings' => $warnings,
            ], $actor);

            return ['lines' => $lines, 'warnings' => $warnings];
        });
    }

    /**
     * @param  list<array{key: string, label: string, passed: bool, detail: string, lines: list<string>}>  $checks
     */
    public static function failedChecksText(array $checks): string
    {
        $parts = [];

        foreach ($checks as $check) {
            if (! $check['passed']) {
                $parts[] = $check['label'].': '.$check['detail'].($check['lines'] !== [] ? ' ('.implode('; ', array_slice($check['lines'], 0, 5)).(count($check['lines']) > 5 ? '; y '.(count($check['lines']) - 5).' más' : '').')' : '');
            }
        }

        return implode(' · ', $parts);
    }

    /**
     * @return list<array{id: int, invoice: string, status_before: string, adjustment_id: int}>
     */
    private static function cancelCollections(AffiliationDeletionSubject $subject, string $reason, User $actor): array
    {
        $cancelled = [];

        $collections = CollectionModel::query()
            ->where('affiliation_code', $subject->code())
            ->where('status', CollectionAdjuster::STATUS_PENDING)
            ->lockForUpdate()
            ->get();

        foreach ($collections as $collection) {
            $before = (string) $collection->status;
            $adjustment = self::changeCollectionStatus($collection, CollectionAdjuster::STATUS_CANCELLED, 'Eliminación de '.$subject->label().': '.$reason, $actor, 'eliminacion-'.$subject->type.'-'.$subject->id());

            $cancelled[] = [
                'id' => (int) $collection->getKey(),
                'invoice' => (string) ($collection->collection_invoice_number ?? '#'.$collection->getKey()),
                'status_before' => $before,
                'adjustment_id' => (int) $adjustment->getKey(),
            ];
        }

        return $cancelled;
    }

    private static function changeCollectionStatus(CollectionModel $collection, string $status, string $reason, User $actor, string $batch): CollectionAdjustment
    {
        $before = (string) $collection->status;
        $collection->status = $status;
        $collection->save();

        return CollectionAdjustment::query()->create([
            'collection_id' => $collection->getKey(),
            'affiliation_code' => $collection->affiliation_code,
            'collection_invoice_number' => $collection->collection_invoice_number,
            'batch_uuid' => Str::limit($batch, 36, ''),
            'mode' => CollectionAdjustment::MODE_DELETION,
            'changes' => ['status' => ['before' => $before, 'after' => $status]],
            'reason' => Str::limit($reason, 2000, ''),
            'performed_by_id' => $actor->getKey(),
            'performed_by_name' => $actor->name,
            'ip' => request()?->ip(),
            'user_agent' => Str::limit((string) request()?->userAgent(), 1000, ''),
        ]);
    }

    /**
     * Desvincula cada paciente, o lo pasa a la afiliación vigente de la misma persona.
     *
     * @param  list<array<string, mixed>>  $patients
     * @return list<array{id: int, name: string, action: string, before: array<string, mixed>, after: array<string, mixed>}>
     */
    private static function detachPatients(AffiliationDeletionSubject $subject, array $patients): array
    {
        $changed = [];

        foreach ($patients as $item) {
            $patient = TelemedicinePatient::query()->lockForUpdate()->find((int) $item['id']);

            if ($patient === null) {
                continue;
            }

            $before = $patient->only(self::PATIENT_LINK_COLUMNS);
            $other = $item['action'] === 'reubicar' ? self::otherParent($item['other'] ?? null) : null;

            if ($other !== null) {
                $patient->forceFill([
                    'afilliation_id' => $other instanceof Affiliation ? $other->getKey() : null,
                    'afilliation_corporate_id' => $other instanceof AffiliationCorporate ? $other->getKey() : null,
                    'code_affiliation' => $other->code,
                    'type_affiliation' => $other instanceof AffiliationCorporate ? 'CORPORATIVO' : 'INDIVIDUAL',
                    'status_affiliation' => (string) ($item['other']['status'] ?? $other->status),
                    'name_corporate' => $other instanceof AffiliationCorporate ? Str::limit((string) $other->name_corporate, 100, '') : null,
                ])->save();

                TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->getKey());
                TelemedicinePatientPlanBridge::persistFromLinkedAffiliate($patient->fresh() ?? $patient, onlyMissing: false);
            } else {
                $patient->forceFill([
                    'afilliation_id' => null,
                    'afilliation_corporate_id' => null,
                    'code_affiliation' => null,
                    'status_affiliation' => self::PATIENT_UNLINKED_STATUS,
                    'plan_id' => null,
                    'coverage_id' => null,
                ])->save();
            }

            TelemedicinePatientPlanBridge::flushCachesFor((int) $patient->getKey());
            $fresh = TelemedicinePatient::query()->findOrFail($patient->getKey());

            $changed[] = [
                'id' => (int) $patient->getKey(),
                'name' => (string) $item['name'],
                'action' => $other !== null ? 'reubicar' : 'desvincular',
                'to' => $other?->code,
                'before' => $before,
                'after' => $fresh->only(self::PATIENT_LINK_COLUMNS),
            ];
        }

        return $changed;
    }

    /**
     * @param  list<array<string, mixed>>  $patients
     * @return list<array{id: int, code: string}>
     */
    private static function hideCases(array $patients, string $reason): array
    {
        $service = app(TelemedicineCaseLogicalDeletionService::class);
        $hidden = [];

        foreach ($patients as $patient) {
            foreach ((array) $patient['hide_cases'] as $item) {
                $case = TelemedicineCase::query()->lockForUpdate()->find((int) $item['id']);

                if ($case === null || TelemedicineCaseDeletion::isDeleted($case)) {
                    continue;
                }

                $service->delete($case, $reason);
                $hidden[] = ['id' => (int) $case->getKey(), 'code' => (string) $case->code];
            }
        }

        return $hidden;
    }

    /**
     * @param  array<string, mixed>|null  $other
     */
    private static function otherParent(?array $other): Affiliation|AffiliationCorporate|null
    {
        if ($other === null || ($other['parent_id'] ?? null) === null) {
            return null;
        }

        return match ($other['source']) {
            PersonIdentitySource::Individual->value => Affiliation::query()->find((int) $other['parent_id']),
            PersonIdentitySource::Corporate->value => AffiliationCorporate::query()->find((int) $other['parent_id']),
            default => null,
        };
    }

    private static function isSameAffiliation(AffiliationDeletionSubject $subject, PersonIdentityRecord $record): bool
    {
        $source = $subject->isCorporate() ? PersonIdentitySource::Corporate : PersonIdentitySource::Individual;

        return $record->source === $source && $record->parentId === $subject->id();
    }

    /**
     * @param  list<array<string, mixed>>  $collections
     * @param  list<array<string, mixed>>  $patients
     * @param  list<array<string, mixed>>  $cases
     * @param  list<array<string, mixed>>  $inspected
     * @return list<string>
     */
    private static function deletionLines(AffiliationDeletionSubject $subject, int $affiliates, array $collections, array $patients, array $cases, array $inspected): array
    {
        $lines = [$affiliates.' afiliado(s) ocultos junto con la afiliación.'];

        if ($collections !== []) {
            $lines[] = count($collections).' aviso(s) de cobro por pagar anulados ('.implode(', ', array_column($collections, 'invoice')).').';
        }

        $moved = array_filter($patients, static fn (array $patient): bool => $patient['action'] === 'reubicar');
        $unlinked = count($patients) - count($moved);

        if ($unlinked > 0) {
            $lines[] = $unlinked.' paciente(s) de telemedicina desvinculados (su historia clínica se conserva).';
        }

        foreach ($moved as $patient) {
            $lines[] = 'El paciente '.$patient['name'].' pasó a '.$patient['to'].', donde la persona sigue vigente.';
        }

        if ($cases !== []) {
            $lines[] = count($cases).' caso(s) clínico(s) cerrado(s) ocultos ('.implode(', ', array_column($cases, 'code')).').';
        }

        $kept = array_sum(array_map(static fn (array $patient): int => count((array) $patient['kept_cases']), $inspected));

        if ($kept > 0) {
            $lines[] = $kept.' caso(s) cerrado(s) se conservan porque no pertenecen a esta afiliación.';
        }

        return $lines;
    }

    private static function observation(AffiliationDeletionSubject $subject, string $text, User $actor): void
    {
        $values = ['description' => Str::limit($text, 5000, ''), 'created_by' => (string) $actor->getKey()];

        $subject->isCorporate()
            ? AffiliationCorporateObservation::query()->create([...$values, 'affiliation_corporate_id' => $subject->id()])
            : AffiliationObservation::query()->create([...$values, 'affiliation_id' => $subject->id()]);
    }

    private static function assertCan(User $actor): void
    {
        if (! self::userCan($actor)) {
            throw new RuntimeException('No tiene el permiso «Eliminar afiliaciones».');
        }
    }

    private static function assertReason(string $reason): string
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < self::REASON_MIN_LENGTH) {
            throw new RuntimeException('Explique el motivo con al menos '.self::REASON_MIN_LENGTH.' caracteres: queda en la afiliación, en la traza y en la auditoría.');
        }

        return $reason;
    }
}
