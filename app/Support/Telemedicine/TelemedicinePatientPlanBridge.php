<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Affiliation;
use App\Models\Plan;
use App\Models\TelemedicinePatient;
use App\Support\ClinicalEntitlements\AffiliateClinicalEntitlementResolver;
use App\Support\ClinicalEntitlements\TelemedicineConsultationClinicalUi;
use Illuminate\Support\Collection;

/**
 * El paciente de telemedicina y el afiliado guardan `plan_id` por separado.
 * Si Operaciones asigna el plan después de «Asociar a pacientes», el paciente
 * queda en null y Telemedicina no ve cupos. Este puente hereda el plan del
 * afiliado vinculado (misma afiliación + cédula) y puede persistirlo.
 */
final class TelemedicinePatientPlanBridge
{
    public const RELATION_CORPORATE_AFFILIATE = 'linkedAffiliateCorporate';

    public const RELATION_INDIVIDUAL_AFFILIATE = 'linkedAffiliate';

    public static function plan(TelemedicinePatient $patient): ?Plan
    {
        if ($patient->relationLoaded('plan') && $patient->plan instanceof Plan && $patient->plan->getKey() !== null) {
            return $patient->plan;
        }

        if ((int) ($patient->plan_id ?? 0) > 0) {
            if ($patient->exists) {
                $patient->loadMissing('plan');
            }

            if ($patient->plan instanceof Plan) {
                return $patient->plan;
            }
        }

        $corporate = self::linkedAffiliateCorporate($patient);
        if ($corporate instanceof AffiliateCorporate) {
            if ($corporate->exists && ! $corporate->relationLoaded('plan')) {
                $corporate->loadMissing('plan');
            }

            if ($corporate->plan instanceof Plan) {
                return $corporate->plan;
            }
        }

        $individual = self::linkedAffiliate($patient);
        if ($individual instanceof Affiliate) {
            if ($individual->exists) {
                $individual->loadMissing(['plan', 'affiliation.plan']);
            }

            if ($individual->plan instanceof Plan) {
                return $individual->plan;
            }

            if ($individual->affiliation?->plan instanceof Plan) {
                return $individual->affiliation->plan;
            }
        }

        $affiliation = $patient->afilliation;
        if ($affiliation instanceof Affiliation) {
            if ($affiliation->exists && ! $affiliation->relationLoaded('plan')) {
                $affiliation->loadMissing('plan');
            }

            if ($affiliation->plan instanceof Plan) {
                return $affiliation->plan;
            }
        }

        return null;
    }

    public static function hydrate(TelemedicinePatient $patient): ?Plan
    {
        $plan = self::plan($patient);
        if ($plan instanceof Plan) {
            $patient->setRelation('plan', $plan);
        }

        return $plan;
    }

    public static function planId(TelemedicinePatient $patient): ?int
    {
        $plan = self::plan($patient);
        if ($plan instanceof Plan && $plan->getKey() !== null) {
            return (int) $plan->getKey();
        }

        $id = (int) ($patient->plan_id ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return array{plan_id: int, coverage_id: int|null}|null
     */
    public static function pendingAttributes(TelemedicinePatient $patient, bool $onlyMissing = true): ?array
    {
        $source = self::sourcePlanAndCoverage($patient);
        if ($source === null) {
            return null;
        }

        $currentPlanId = (int) ($patient->plan_id ?? 0);
        if ($onlyMissing && $currentPlanId > 0) {
            return null;
        }

        $payload = [];
        if ($currentPlanId !== $source['plan_id']) {
            $payload['plan_id'] = $source['plan_id'];
        }

        $currentCoverage = $patient->coverage_id !== null ? (int) $patient->coverage_id : null;
        $sourceCoverage = $source['coverage_id'];
        if ($sourceCoverage !== null && $currentCoverage !== $sourceCoverage) {
            if (! $onlyMissing || $currentCoverage === null) {
                $payload['coverage_id'] = $sourceCoverage;
            }
        }

        return $payload === [] ? null : $payload;
    }

    public static function persistFromLinkedAffiliate(TelemedicinePatient $patient, bool $onlyMissing = true): bool
    {
        $payload = self::pendingAttributes($patient, $onlyMissing);
        if ($payload === null || ! $patient->exists) {
            return false;
        }

        $patient->forceFill($payload)->save();
        self::flushPatientCaches((int) $patient->id);

        return true;
    }

    public static function syncFromAffiliateCorporate(AffiliateCorporate $affiliate, bool $onlyMissing = false): int
    {
        if (! $affiliate->exists) {
            return 0;
        }

        $affiliationId = (int) ($affiliate->affiliation_corporate_id ?? 0);
        if ($affiliationId < 1 || (int) ($affiliate->plan_id ?? 0) < 1) {
            return 0;
        }

        $updated = 0;

        foreach (self::matchingCorporatePatients($affiliate) as $patient) {
            if (self::persistFromLinkedAffiliate($patient, $onlyMissing)) {
                $updated++;
            }
        }

        return $updated;
    }

    public static function syncFromAffiliate(Affiliate $affiliate, bool $onlyMissing = false): int
    {
        if (! $affiliate->exists) {
            return 0;
        }

        $affiliationId = (int) ($affiliate->affiliation_id ?? 0);
        $planId = (int) ($affiliate->plan_id ?? $affiliate->affiliation?->plan_id ?? 0);
        if ($affiliationId < 1 || $planId < 1) {
            return 0;
        }

        $updated = 0;

        foreach (self::matchingIndividualPatients($affiliate) as $patient) {
            if (self::persistFromLinkedAffiliate($patient, $onlyMissing)) {
                $updated++;
            }
        }

        return $updated;
    }

    /**
     * @return array{scanned: int, updated: int, skipped: int, rows: list<array<string, mixed>>}
     */
    public static function backfillMissing(bool $apply, ?int $patientId = null, ?string $document = null, ?int $limit = null): array
    {
        $query = TelemedicinePatient::query()
            ->whereNull('plan_id')
            ->where(function ($builder): void {
                $builder->whereNotNull('afilliation_corporate_id')
                    ->orWhereNotNull('afilliation_id');
            })
            ->orderBy('id');

        if ($patientId !== null && $patientId > 0) {
            $query->where('id', $patientId);
        }

        $normalizedDocument = TelemedicinePatientIdentity::normalizeDocument($document);
        if ($normalizedDocument !== '') {
            $lookups = self::documentLookupValues($normalizedDocument);
            $query->where(function ($builder) use ($lookups): void {
                $builder->whereIn('nro_identificacion', $lookups);
            });
        }

        if ($limit !== null && $limit > 0) {
            $query->limit($limit);
        }

        $scanned = 0;
        $updated = 0;
        $skipped = 0;
        $rows = [];

        $query->chunkById(100, function (Collection $patients) use ($apply, &$scanned, &$updated, &$skipped, &$rows): void {
            foreach ($patients as $patient) {
                if (! $patient instanceof TelemedicinePatient) {
                    continue;
                }

                $scanned++;
                $payload = self::pendingAttributes($patient, onlyMissing: true);
                if ($payload === null) {
                    $skipped++;

                    continue;
                }

                $rows[] = [
                    'patient_id' => $patient->id,
                    'full_name' => $patient->full_name,
                    'nro_identificacion' => $patient->nro_identificacion,
                    'code_affiliation' => $patient->code_affiliation,
                    'plan_id' => $payload['plan_id'] ?? null,
                    'coverage_id' => $payload['coverage_id'] ?? $patient->coverage_id,
                ];

                if (! $apply) {
                    continue;
                }

                $patient->forceFill($payload)->save();
                self::flushPatientCaches((int) $patient->id);
                $updated++;
            }
        });

        if ($apply) {
            TelemedicineConsultationClinicalUi::flush();
        }

        return [
            'scanned' => $scanned,
            'updated' => $updated,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    public static function linkedAffiliateCorporate(TelemedicinePatient $patient): ?AffiliateCorporate
    {
        if ($patient->relationLoaded(self::RELATION_CORPORATE_AFFILIATE)) {
            $rel = $patient->getRelation(self::RELATION_CORPORATE_AFFILIATE);

            return $rel instanceof AffiliateCorporate ? $rel : null;
        }

        $affiliationId = (int) ($patient->afilliation_corporate_id ?? 0);
        if ($affiliationId < 1 || ! $patient->exists) {
            $patient->setRelation(self::RELATION_CORPORATE_AFFILIATE, null);

            return null;
        }

        $affiliate = self::preferActiveCorporate(self::corporateCandidates($affiliationId, $patient->nro_identificacion));
        $patient->setRelation(self::RELATION_CORPORATE_AFFILIATE, $affiliate);

        return $affiliate;
    }

    public static function linkedAffiliate(TelemedicinePatient $patient): ?Affiliate
    {
        if ($patient->relationLoaded(self::RELATION_INDIVIDUAL_AFFILIATE)) {
            $rel = $patient->getRelation(self::RELATION_INDIVIDUAL_AFFILIATE);

            return $rel instanceof Affiliate ? $rel : null;
        }

        $affiliationId = (int) ($patient->afilliation_id ?? 0);
        if ($affiliationId < 1 || ! $patient->exists) {
            $patient->setRelation(self::RELATION_INDIVIDUAL_AFFILIATE, null);

            return null;
        }

        $affiliate = self::preferActiveIndividual(self::individualCandidates($affiliationId, $patient->nro_identificacion));
        $patient->setRelation(self::RELATION_INDIVIDUAL_AFFILIATE, $affiliate);

        return $affiliate;
    }

    /**
     * @return array{plan_id: int, coverage_id: int|null}|null
     */
    private static function sourcePlanAndCoverage(TelemedicinePatient $patient): ?array
    {
        $corporate = self::linkedAffiliateCorporate($patient);
        if ($corporate instanceof AffiliateCorporate && (int) ($corporate->plan_id ?? 0) > 0) {
            return [
                'plan_id' => (int) $corporate->plan_id,
                'coverage_id' => $corporate->coverage_id !== null ? (int) $corporate->coverage_id : null,
            ];
        }

        $individual = self::linkedAffiliate($patient);
        if ($individual instanceof Affiliate) {
            if ($individual->exists && ! $individual->relationLoaded('affiliation')) {
                $individual->loadMissing('affiliation');
            }

            $planId = (int) ($individual->plan_id ?? $individual->affiliation?->plan_id ?? 0);
            if ($planId > 0) {
                $coverageId = $individual->coverage_id ?? $individual->affiliation?->coverage_id;

                return [
                    'plan_id' => $planId,
                    'coverage_id' => $coverageId !== null ? (int) $coverageId : null,
                ];
            }
        }

        $affiliation = $patient->afilliation;
        if ($affiliation instanceof Affiliation && (int) ($affiliation->plan_id ?? 0) > 0) {
            return [
                'plan_id' => (int) $affiliation->plan_id,
                'coverage_id' => $affiliation->coverage_id !== null ? (int) $affiliation->coverage_id : null,
            ];
        }

        return null;
    }

    /**
     * @return Collection<int, AffiliateCorporate>
     */
    private static function corporateCandidates(int $affiliationId, mixed $document): Collection
    {
        $digits = self::documentDigits($document);

        $query = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $affiliationId)
            ->whereNotNull('plan_id')
            ->with('plan');

        self::constrainDocument($query, $document);

        return $query->get()->filter(
            static fn (AffiliateCorporate $row): bool => self::documentsLikelyMatch($row->nro_identificacion, $document, $digits),
        )->values();
    }

    /**
     * @return Collection<int, Affiliate>
     */
    private static function individualCandidates(int $affiliationId, mixed $document): Collection
    {
        $digits = self::documentDigits($document);

        $query = Affiliate::query()
            ->where('affiliation_id', $affiliationId)
            ->with(['plan', 'affiliation.plan']);

        self::constrainDocument($query, $document);

        return $query->get()->filter(
            static fn (Affiliate $row): bool => self::documentsLikelyMatch($row->nro_identificacion, $document, $digits),
        )->values();
    }

    /**
     * @return Collection<int, TelemedicinePatient>
     */
    private static function matchingCorporatePatients(AffiliateCorporate $affiliate): Collection
    {
        $affiliationId = (int) $affiliate->affiliation_corporate_id;
        $digits = self::documentDigits($affiliate->nro_identificacion);

        $query = TelemedicinePatient::query()
            ->where('afilliation_corporate_id', $affiliationId);

        self::constrainDocument($query, $affiliate->nro_identificacion);

        return $query->get()->filter(
            static fn (TelemedicinePatient $patient): bool => self::documentsLikelyMatch(
                $patient->nro_identificacion,
                $affiliate->nro_identificacion,
                $digits,
            ),
        )->values();
    }

    /**
     * @return Collection<int, TelemedicinePatient>
     */
    private static function matchingIndividualPatients(Affiliate $affiliate): Collection
    {
        $affiliationId = (int) $affiliate->affiliation_id;
        $digits = self::documentDigits($affiliate->nro_identificacion);

        $query = TelemedicinePatient::query()
            ->where('afilliation_id', $affiliationId);

        self::constrainDocument($query, $affiliate->nro_identificacion);

        return $query->get()->filter(
            static fn (TelemedicinePatient $patient): bool => self::documentsLikelyMatch(
                $patient->nro_identificacion,
                $affiliate->nro_identificacion,
                $digits,
            ),
        )->values();
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $matches
     */
    private static function preferActiveCorporate(Collection $matches): ?AffiliateCorporate
    {
        if ($matches->isEmpty()) {
            return null;
        }

        $active = $matches->first(
            static fn (AffiliateCorporate $row): bool => mb_strtoupper(trim((string) $row->status)) === 'ACTIVO'
        );

        return $active instanceof AffiliateCorporate ? $active : $matches->first();
    }

    /**
     * @param  Collection<int, Affiliate>  $matches
     */
    private static function preferActiveIndividual(Collection $matches): ?Affiliate
    {
        if ($matches->isEmpty()) {
            return null;
        }

        $active = $matches->first(
            static fn (Affiliate $row): bool => mb_strtoupper(trim((string) $row->status)) === 'ACTIVO'
        );

        return $active instanceof Affiliate ? $active : $matches->first();
    }

    /**
     * @return list<string>
     */
    private static function documentLookupValues(mixed $document): array
    {
        $normalized = TelemedicinePatientIdentity::normalizeDocument(
            is_string($document) || is_numeric($document) ? (string) $document : null,
        );
        if ($normalized === '') {
            return [];
        }

        $digits = self::documentDigits($normalized);
        $values = [$normalized];
        if ($digits !== '') {
            $values[] = $digits;
            $values[] = 'V-'.$digits;
            $values[] = 'V'.$digits;
            $values[] = 'E-'.$digits;
            $values[] = 'E'.$digits;
            $values[] = 'P-'.$digits;
            $values[] = 'P'.$digits;
        }

        return array_values(array_unique(array_filter($values)));
    }

    private static function documentDigits(mixed $document): string
    {
        $normalized = TelemedicinePatientIdentity::normalizeDocument(
            is_string($document) || is_numeric($document) ? (string) $document : null,
        );

        return preg_replace('/\D+/', '', $normalized) ?? '';
    }

    private static function documentsLikelyMatch(mixed $left, mixed $right, string $digits): bool
    {
        $leftValue = is_string($left) || is_numeric($left) ? (string) $left : null;
        $rightValue = is_string($right) || is_numeric($right) ? (string) $right : null;

        if (TelemedicinePatientIdentity::documentsMatch($leftValue, $rightValue)) {
            return true;
        }

        return $digits !== '' && self::documentDigits($left) === $digits;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function constrainDocument($query, mixed $document): void
    {
        $lookups = self::documentLookupValues($document);
        if ($lookups === []) {
            return;
        }

        $normalizedSql = "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(nro_identificacion), ' ', ''), '.', ''), '-', ''), '_', '')";
        $placeholders = implode(',', array_fill(0, count($lookups), '?'));

        $query->where(function ($builder) use ($lookups, $normalizedSql, $placeholders): void {
            $builder->whereIn('nro_identificacion', $lookups)
                ->orWhereRaw($normalizedSql.' in ('.$placeholders.')', $lookups);
        });
    }

    private static function flushPatientCaches(int $patientId): void
    {
        if ($patientId > 0) {
            AffiliateClinicalEntitlementResolver::flush($patientId);
        }

        TelemedicineConsultationClinicalUi::flush();
    }
}
