<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Jobs\PrepareAffiliationRenovations;
use App\Jobs\RegenerateMergedAffiliationDocumentsJob;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AffiliationFamilyMerge;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\Renovation;
use App\Models\TelemedicinePatient;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\AffiliationCorporates\CorporatePaymentFrequency;
use App\Support\AffiliationCorporates\CorporatePaymentFrequencyChanger;
use App\Support\Collections\DroppedAffiliationCollections;
use App\Support\SecurityAudit;
use App\Support\Upgrades\IndividualAffiliateUpgradeManager;
use Carbon\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MergeIndividualAffiliationsService
{
    public const TARGET_ALLOWED_STATUSES = ['ACTIVA', 'PRE-APROBADA'];

    public const SOURCE_FORBIDDEN_STATUSES = ['EXCLUIDO', 'EXCLUIDA'];

    public const COLLECTION_PENDING_STATUS = 'POR PAGAR';

    public const COLLECTION_CANCELLED_STATUS = 'CANCELADO';

    public const RENOVATION_CANCELLED_STATUS = 'ANULADA';

    public const MERGE_ACTION = 'UNIFICO GRUPO FAMILIAR';

    /**
     * @return array<string, string>
     */
    public static function relationshipOptions(): array
    {
        return [
            'TITULAR' => 'TITULAR',
            'MADRE' => 'MADRE',
            'PADRE' => 'PADRE',
            'ESPOSA' => 'ESPOSA',
            'ESPOSO' => 'ESPOSO',
            'CONYUGE' => 'CONYUGE',
            'HIJO' => 'HIJO',
            'HIJA' => 'HIJA',
            'AMIGO' => 'AMIGO',
            'OTRO' => 'OTRO',
        ];
    }

    public static function normalizeRelationship(string $relationship): string
    {
        return strtoupper(trim($relationship));
    }

    public static function normalizeIdentification(?string $value): string
    {
        $normalized = preg_replace('/[\s.\-]/', '', strtoupper(trim((string) $value)));

        return is_string($normalized) ? $normalized : '';
    }

    public static function normalizeCode(?string $value): string
    {
        return strtoupper(trim((string) $value));
    }

    /**
     * @param  list<int>  $affiliateIds
     * @param  array<int, string>  $relationships
     * @return list<string>
     */
    public static function relationshipBlockers(array $affiliateIds, int $titularAffiliateId, array $relationships): array
    {
        $blockers = [];
        $affiliateIds = array_values(array_unique(array_map(static fn (mixed $id): int => (int) $id, $affiliateIds)));

        if ($affiliateIds === []) {
            $blockers[] = 'No hay personas para unificar.';

            return $blockers;
        }

        if (! in_array($titularAffiliateId, $affiliateIds, true)) {
            $blockers[] = 'El titular elegido no pertenece al grupo que se va a unificar.';
        }

        $allowed = array_keys(self::relationshipOptions());
        $titularCount = 0;

        foreach ($affiliateIds as $affiliateId) {
            $relationship = self::normalizeRelationship((string) ($relationships[$affiliateId] ?? ''));

            if ($relationship === '') {
                $blockers[] = 'Debe indicar el parentesco de cada persona del grupo.';

                continue;
            }

            if (! in_array($relationship, $allowed, true)) {
                $blockers[] = 'El parentesco «'.$relationship.'» no es válido.';

                continue;
            }

            if ($relationship === 'TITULAR') {
                $titularCount++;

                if ($affiliateId !== $titularAffiliateId) {
                    $blockers[] = 'Solo la persona elegida como titular puede tener parentesco TITULAR.';
                }
            }
        }

        $titularRelationship = self::normalizeRelationship((string) ($relationships[$titularAffiliateId] ?? ''));
        if ($titularRelationship !== '' && $titularRelationship !== 'TITULAR') {
            $blockers[] = 'La persona elegida como titular debe tener parentesco TITULAR.';
        }

        if ($titularCount !== 1) {
            $blockers[] = 'El grupo unificado debe tener exactamente un titular.';
        }

        return array_values(array_unique($blockers));
    }

    /**
     * @param  list<string>  $identifications
     * @return list<string>
     */
    public static function duplicateIdentificationBlockers(array $identifications): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($identifications as $identification) {
            $normalized = self::normalizeIdentification($identification);

            if ($normalized === '') {
                continue;
            }

            if (isset($seen[$normalized])) {
                $duplicates[] = $normalized;
            }

            $seen[$normalized] = true;
        }

        if ($duplicates === []) {
            return [];
        }

        return ['Hay cédulas duplicadas en el grupo: '.implode(', ', array_unique($duplicates)).'.'];
    }

    public function __construct(
        private readonly AffiliationAffiliateFeeCalculator $calculator,
    ) {}

    public function preview(MergeIndividualAffiliationsRequest $request): MergeIndividualAffiliationsPreview
    {
        return $this->evaluate($request, $this->loadBundle($request, lock: false));
    }

    public function execute(MergeIndividualAffiliationsRequest $request): MergeIndividualAffiliationsResult
    {
        if ($request->reason === '') {
            throw MergeIndividualAffiliationsException::fromBlockers([
                'Debe indicar el motivo de la unificación.',
            ]);
        }

        return DB::transaction(function () use ($request): MergeIndividualAffiliationsResult {
            $bundle = $this->loadBundle($request, lock: true);
            $preview = $this->evaluate($request, $bundle);

            if (! $preview->canExecute()) {
                throw MergeIndividualAffiliationsException::fromBlockers($preview->blockers);
            }

            return $this->persist($request, $bundle, $preview);
        });
    }

    /**
     * @return array{
     *     target: Affiliation,
     *     sources: SupportCollection<int, Affiliation>,
     *     affiliates: SupportCollection<int, Affiliate>,
     *     pendingCollections: SupportCollection<int, Collection>,
     *     pendingAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     targetAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     openRenovations: SupportCollection<int, Renovation>,
     *     telemedicinePatients: SupportCollection<int, TelemedicinePatient>
     * }
     */
    public function loadBundle(MergeIndividualAffiliationsRequest $request, bool $lock): array
    {
        $ids = array_values(array_unique([
            $request->targetAffiliationId,
            ...$request->sourceAffiliationIds,
        ]));
        sort($ids);

        $query = Affiliation::query()
            ->whereIn('id', $ids)
            ->with(['affiliates', 'plan', 'whiteCompanyUser']);

        if ($lock) {
            $query->lockForUpdate()->orderBy('id');
        }

        $affiliations = $query->get()->keyBy('id');

        if ($lock) {
            Affiliate::query()
                ->whereIn('affiliation_id', $ids)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);
        }

        $target = $affiliations->get($request->targetAffiliationId);

        if (! $target instanceof Affiliation) {
            throw MergeIndividualAffiliationsException::fromBlockers([
                'No se encontró la afiliación destino.',
            ]);
        }

        $sources = new SupportCollection;
        foreach ($request->sourceAffiliationIds as $sourceId) {
            $source = $affiliations->get($sourceId);

            if (! $source instanceof Affiliation) {
                throw MergeIndividualAffiliationsException::fromBlockers([
                    'No se encontró la afiliación origen #'.$sourceId.'.',
                ]);
            }

            $sources->push($source);
        }

        $affiliates = $affiliations
            ->flatMap(static fn (Affiliation $affiliation) => $affiliation->affiliates)
            ->unique('id')
            ->values();

        $sourceCodes = $sources->pluck('code')->filter()->map(fn (mixed $code): string => (string) $code)->all();
        $sourceIds = $sources->map(static fn (Affiliation $source): int => (int) $source->id)->values()->all();
        $identifications = $affiliates
            ->map(fn (Affiliate $affiliate): string => trim((string) $affiliate->nro_identificacion))
            ->filter()
            ->values()
            ->all();

        // Avisos POR PAGAR de todas las pólizas (destino incluida): el calendario nuevo los reemplaza.
        $pendingCollectionsQuery = Collection::query()
            ->whereIn('affiliation_code', [(string) $target->code, ...$sourceCodes])
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->orderBy('id');

        if ($lock) {
            $pendingCollectionsQuery->lockForUpdate();
        }

        $pendingCollections = $pendingCollectionsQuery->get();

        $pendingAnnualCollections = AnnualCollection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->get();

        $targetAnnualCollections = AnnualCollection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->get();

        $openRenovations = Renovation::query()
            ->whereIn('affiliation_id', $ids)
            ->whereIn('status', [
                PrepareAffiliationRenovations::STATUS_VIGENTE,
                PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD,
            ])
            ->get();

        $telemedicinePatients = TelemedicinePatient::query()
            ->where(function ($query) use ($sourceIds, $ids, $identifications): void {
                $query->whereIn('afilliation_id', $sourceIds);

                if ($identifications !== []) {
                    // Por cédula solo se reasignan pacientes sin afiliación o ya ligados al grupo: un paciente
                    // de una afiliación corporativa u otra póliza individual no se roba.
                    $query->orWhere(function ($byIdentification) use ($ids, $identifications): void {
                        $byIdentification->whereIn('nro_identificacion', $identifications)
                            ->whereNull('afilliation_corporate_id')
                            ->where(function ($link) use ($ids): void {
                                $link->whereNull('afilliation_id')->orWhereIn('afilliation_id', $ids);
                            });
                    });
                }
            })
            ->get();

        return [
            'target' => $target,
            'sources' => $sources,
            'affiliates' => $affiliates,
            'pendingCollections' => $pendingCollections,
            'pendingAnnualCollections' => $pendingAnnualCollections,
            'targetAnnualCollections' => $targetAnnualCollections,
            'openRenovations' => $openRenovations,
            'telemedicinePatients' => $telemedicinePatients,
        ];
    }

    /**
     * @param  array{
     *     target: Affiliation,
     *     sources: SupportCollection<int, Affiliation>,
     *     affiliates: SupportCollection<int, Affiliate>,
     *     pendingCollections: SupportCollection<int, Collection>,
     *     pendingAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     targetAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     openRenovations: SupportCollection<int, Renovation>,
     *     telemedicinePatients: SupportCollection<int, TelemedicinePatient>
     * }  $bundle
     */
    public function evaluate(MergeIndividualAffiliationsRequest $request, array $bundle): MergeIndividualAffiliationsPreview
    {
        /** @var Affiliation $target */
        $target = $bundle['target'];
        /** @var SupportCollection<int, Affiliation> $sources */
        $sources = $bundle['sources'];
        /** @var SupportCollection<int, Affiliate> $affiliates */
        $affiliates = $bundle['affiliates'];

        $blockers = [];
        $warnings = [];

        if ($request->sourceAffiliationIds === []) {
            $blockers[] = 'Debe seleccionar al menos una afiliación origen para unirla a esta póliza.';
        }

        if (in_array($request->targetAffiliationId, $request->sourceAffiliationIds, true)) {
            $blockers[] = 'La afiliación destino no puede estar también como origen.';
        }

        if (in_array(strtoupper((string) $target->status), self::SOURCE_FORBIDDEN_STATUSES, true)) {
            $blockers[] = 'La afiliación destino está excluida y no puede recibir el grupo familiar.';
        }

        if (! in_array(strtoupper((string) $target->status), self::TARGET_ALLOWED_STATUSES, true)) {
            $blockers[] = 'La afiliación destino debe estar ACTIVA o PRE-APROBADA.';
        }

        foreach ($sources as $source) {
            if (in_array(strtoupper((string) $source->status), self::SOURCE_FORBIDDEN_STATUSES, true)) {
                $blockers[] = 'La afiliación '.$source->code.' ya está excluida.';
            }

            if ($source->affiliates->isEmpty()) {
                $blockers[] = 'La afiliación '.$source->code.' no tiene personas para mover.';
            }
        }

        if ($affiliates->isEmpty()) {
            $blockers[] = 'No hay afiliados para unificar.';
        }

        $blockers = [
            ...$blockers,
            ...self::relationshipBlockers(
                $affiliates->map(static fn (Affiliate $affiliate): int => (int) $affiliate->id)->values()->all(),
                $request->titularAffiliateId,
                $request->relationships,
            ),
            ...self::duplicateIdentificationBlockers(
                $affiliates->map(fn (Affiliate $affiliate): string => (string) $affiliate->nro_identificacion)->all(),
            ),
        ];

        $titular = $affiliates->first(
            fn (Affiliate $affiliate): bool => (int) $affiliate->id === $request->titularAffiliateId
        );

        if (! $titular instanceof Affiliate) {
            $blockers[] = 'No se encontró el afiliado titular en el grupo.';
        }

        foreach ($sources as $source) {
            if ((int) $source->plan_id !== (int) $target->plan_id) {
                $blockers[] = 'El plan de '.$source->code.' no coincide con el de la póliza destino. Unifique solo pólizas del mismo plan.';
            }

            if (self::normalizeCode((string) $source->code_agency) !== self::normalizeCode((string) $target->code_agency)) {
                $blockers[] = 'La agencia de '.$source->code.' no coincide con la de la póliza destino.';
            }

            if ((int) ($source->agent_id ?? 0) !== (int) ($target->agent_id ?? 0)) {
                $warnings[] = 'El agente de '.$source->code.' es distinto. La póliza unificada conservará el agente de '.$target->code.'.';
            }

            if ($this->hasFrozenWhiteCompanyPricing($source) || $this->hasFrozenWhiteCompanyPricing($target)) {
                $blockers[] = 'No se unifican pólizas con tarifa de empresa aliada congelada (white company). Hay que tratarlas en un flujo aparte.';
            }
        }

        $activeOnSource = $sources->contains(
            fn (Affiliation $source): bool => strtoupper((string) $source->status) === PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE
        );

        if ($activeOnSource && strtoupper((string) $target->status) !== PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE) {
            $blockers[] = 'Hay pólizas origen ACTIVA y la destino no lo está. Elija como destino una póliza activa.';
        }

        // La frecuencia elegida por el analista manda: el monto del período de cada persona se calcula con ella.
        $chosenFrequency = CorporatePaymentFrequency::normalize($request->paymentFrequency ?: (string) $target->payment_frequency);

        if ($chosenFrequency !== null) {
            $target->payment_frequency = $chosenFrequency;
        }

        $memberRows = [];
        $projectedAnnual = 0.0;
        $projectedActive = 0;

        foreach ($affiliates as $affiliate) {
            $amounts = $this->calculator->calculateAffiliateAmounts($target, $affiliate);
            $status = strtoupper((string) ($affiliate->status ?? ''));
            $feeAfter = $amounts !== null
                ? round((float) $amounts['annual_fee'] + IndividualAffiliateUpgradeManager::activeTotalFor($affiliate), 2)
                : null;

            if ($amounts === null && in_array($status, PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL, true)) {
                $blockers[] = 'No se pudo calcular la tarifa de '.$affiliate->full_name.' (cédula '.$affiliate->nro_identificacion.'). Revise edad, plan y cobertura antes de unificar.';
            }

            if ($feeAfter !== null && $status === 'ACTIVO') {
                $projectedAnnual += (float) $feeAfter;
                $projectedActive++;
            } elseif ($status === 'ACTIVO') {
                $projectedAnnual += (float) ($affiliate->fee ?? 0);
                $projectedActive++;
            }

            $fromAffiliation = $affiliate->affiliation_id === $target->id
                ? $target
                : $sources->first(
                    fn (Affiliation $source): bool => (int) $source->id === (int) $affiliate->affiliation_id
                );

            $memberRows[] = [
                'affiliate_id' => (int) $affiliate->id,
                'name' => (string) $affiliate->full_name,
                'identification' => (string) $affiliate->nro_identificacion,
                'from_code' => (string) ($fromAffiliation?->code ?? $target->code),
                'old_relationship' => (string) $affiliate->relationship,
                'new_relationship' => $request->relationships[(int) $affiliate->id] ?? '',
                'fee_before' => $affiliate->fee !== null ? (float) $affiliate->fee : null,
                'fee_after' => $feeAfter !== null ? (float) $feeAfter : null,
                'status' => (string) $affiliate->status,
            ];
        }

        $newFeeAnual = round($projectedAnnual, 2);

        $billing = FamilyMergeBilling::plan(
            $target,
            $sources,
            $bundle['pendingCollections'],
            $request->effectiveDate,
            $request->paymentFrequency,
            $newFeeAnual,
        );
        $blockers = [...$blockers, ...$billing['blockers']];
        $warnings = [...$warnings, ...$billing['warnings'], ...$this->renewalWarnings($target, $bundle['openRenovations'], $billing['renewal_date'])];
        $newTotalAmount = $billing['period_amount'];
        $createsCollections = strtoupper((string) $target->status) === PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE;
        $today = Carbon::today();

        $warnings[] = 'Las ventas y comisiones ya emitidas de las pólizas origen se conservan para auditoría. A futuro el grupo comisiona como una sola póliza.';

        return new MergeIndividualAffiliationsPreview(
            blockers: array_values(array_unique($blockers)),
            warnings: array_values(array_unique($warnings)),
            target: [
                'id' => (int) $target->id,
                'code' => (string) $target->code,
                'titular' => (string) $target->full_name_ti,
                'status' => (string) $target->status,
                'plan_id' => $target->plan_id !== null ? (int) $target->plan_id : null,
                'fee_anual' => (float) ($target->fee_anual ?? 0),
                'total_amount' => (float) ($target->total_amount ?? 0),
                'family_members' => (int) ($target->family_members ?? 0),
                'agency' => (string) $target->code_agency,
                'frequency' => (string) $target->payment_frequency,
            ],
            sources: $sources->map(static fn (Affiliation $source): array => [
                'id' => (int) $source->id,
                'code' => (string) $source->code,
                'titular' => (string) $source->full_name_ti,
                'status' => (string) $source->status,
                'fee_anual' => (float) ($source->fee_anual ?? 0),
                'family_members' => (int) ($source->family_members ?? 0),
            ])->values()->all(),
            members: $memberRows,
            collectionsToCancel: $bundle['pendingCollections']->whereIn('id', $billing['cancel_ids'])->map(static fn (Collection $collection): array => [
                'code' => (string) $collection->affiliation_code,
                'invoice' => $collection->collection_invoice_number !== null ? (string) $collection->collection_invoice_number : null,
                'amount' => (float) ($collection->total_amount ?? 0),
            ])->values()->all(),
            annualCollectionsToCancel: $bundle['pendingAnnualCollections']->map(static fn (AnnualCollection $collection): array => [
                'code' => (string) $collection->affiliation_code,
                'invoice' => $collection->collection_invoice_number !== null ? (string) $collection->collection_invoice_number : null,
                'amount' => (float) ($collection->total_amount ?? 0),
            ])->values()->all(),
            renovationsToCancel: $bundle['openRenovations']->map(static fn (Renovation $renovation): array => [
                'code' => (string) $renovation->code_affiliation,
                'status' => (string) $renovation->status,
            ])->values()->all(),
            telemedicinePatients: $bundle['telemedicinePatients']->map(static fn (TelemedicinePatient $patient): array => [
                'identification' => (string) $patient->nro_identificacion,
                'name' => (string) $patient->full_name,
            ])->values()->all(),
            newFeeAnual: $newFeeAnual,
            newTotalAmount: $newTotalAmount,
            newFamilyMembers: $projectedActive,
            pendingCollectionsToRecalculate: $bundle['targetAnnualCollections']->count(),
            billing: [
                'effective_date' => $billing['effective_date']?->format('d/m/Y'),
                'frequency' => $billing['frequency'],
                'renewal_date' => $billing['renewal_date']?->format('d/m/Y'),
                'period_amount' => $billing['period_amount'],
                'creates_collections' => $createsCollections,
                'installments' => array_map(static fn (array $installment): array => [
                    'date' => $installment['date']->format('d/m/Y'),
                    'due' => $installment['due']->format('d/m/Y'),
                    'amount' => $installment['amount'],
                    'overdue' => $installment['date']->lt($today),
                ], $billing['installments']),
                'paid_through' => array_map(static fn (array $row): array => [
                    'code' => $row['code'],
                    'date' => $row['date']?->format('d/m/Y'),
                ], $billing['paid_through']),
                'blockers' => $billing['blockers'],
                'warnings' => $billing['warnings'],
            ],
        );
    }

    /**
     * @param  array{
     *     target: Affiliation,
     *     sources: SupportCollection<int, Affiliation>,
     *     affiliates: SupportCollection<int, Affiliate>,
     *     pendingCollections: SupportCollection<int, Collection>,
     *     pendingAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     targetAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     openRenovations: SupportCollection<int, Renovation>,
     *     telemedicinePatients: SupportCollection<int, TelemedicinePatient>
     * }  $bundle
     */
    private function persist(
        MergeIndividualAffiliationsRequest $request,
        array $bundle,
        MergeIndividualAffiliationsPreview $preview,
    ): MergeIndividualAffiliationsResult {
        /** @var Affiliation $target */
        $target = $bundle['target'];
        /** @var SupportCollection<int, Affiliation> $sources */
        $sources = $bundle['sources'];
        /** @var SupportCollection<int, Affiliate> $affiliates */
        $affiliates = $bundle['affiliates'];

        $sourceCodes = $sources->pluck('code')->filter()->map(fn (mixed $code): string => (string) $code)->all();
        $movedAffiliateIds = [];

        $touchedIds = $this->touchedRowIds($target, $sources, $affiliates, $bundle);
        $snapshotBefore = FamilyMergeSnapshot::capture($touchedIds);
        $previousEffectiveDate = (string) $target->getRawOriginal('effective_date');
        $previousFrequency = (string) $target->getRawOriginal('payment_frequency');

        $plan = FamilyMergeBilling::plan($target, $sources, $bundle['pendingCollections'], $request->effectiveDate, $request->paymentFrequency, $preview->newFeeAnual);

        if ($plan['blockers'] !== [] || $plan['effective_date'] === null) {
            throw MergeIndividualAffiliationsException::fromBlockers($plan['blockers']);
        }

        $target->effective_date = $plan['effective_date']->format('d/m/Y');
        $target->payment_frequency = $plan['frequency'];

        foreach ($affiliates as $affiliate) {
            $relationship = $request->relationships[(int) $affiliate->id] ?? null;

            if (! is_string($relationship) || $relationship === '') {
                throw MergeIndividualAffiliationsException::fromBlockers([
                    'Falta el parentesco de '.$affiliate->full_name.'.',
                ]);
            }

            $applied = $this->calculator->applyAmountsToAffiliate($target, $affiliate);

            if (! $applied && in_array(strtoupper((string) $affiliate->status), PrepareAffiliationRenovations::AFFILIATE_STATUSES_FOR_RENEWAL, true)) {
                throw MergeIndividualAffiliationsException::fromBlockers([
                    'No se pudo recalcular la tarifa de '.$affiliate->full_name.'.',
                ]);
            }

            $affiliate->refresh();
            $affiliate->affiliation_id = $target->id;
            $affiliate->relationship = $relationship;
            $affiliate->plan_id = $target->plan_id;
            $affiliate->payment_frequency = $target->payment_frequency;
            $affiliate->save();

            $movedAffiliateIds[] = (int) $affiliate->id;
        }

        $titular = $affiliates->first(
            fn (Affiliate $affiliate): bool => (int) $affiliate->id === $request->titularAffiliateId
        );

        if (! $titular instanceof Affiliate) {
            throw MergeIndividualAffiliationsException::fromBlockers([
                'No se encontró el afiliado titular después de mover el grupo.',
            ]);
        }

        $this->syncTitularColumns($target, $titular);
        $target->feedback = true;
        $target->save();

        $this->calculator->recalculateAffiliationTotalsFromAffiliates($target);
        $target->refresh();

        // El calendario se arma con la tarifa que quedó guardada, no con la proyectada.
        $plan = FamilyMergeBilling::plan($target, $sources, $bundle['pendingCollections'], $request->effectiveDate, $request->paymentFrequency, (float) $target->fee_anual);
        $createsCollections = strtoupper((string) $target->status) === PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE;

        $cancelledAnnual = $this->cancelSourceAnnualCollections($sourceCodes);
        $billingResult = FamilyMergeBilling::apply(
            $target,
            $createsCollections ? $plan : [...$plan, 'installments' => []],
            $sourceCodes,
            $request->actorName,
            self::COLLECTION_CANCELLED_STATUS,
        );
        $cancelledCollections = count($billingResult['cancelled_ids']) + $cancelledAnnual;

        $cancelledRenovations = $this->cancelStaleRenovations($target, $bundle['openRenovations'], $plan['renewal_date'], $request->actorName);

        $updatedPatients = $this->reassignTelemedicinePatients($target, $bundle['telemedicinePatients']);
        $renovationRefreshed = PrepareAffiliationRenovations::refreshForAffiliation($target->fresh() ?? $target);

        $createdRenovationIds = Renovation::query()
            ->where('affiliation_id', $target->id)
            ->whereNotIn('id', $touchedIds['renovations'] !== [] ? $touchedIds['renovations'] : [0])
            ->pluck('id')
            ->map(intval(...))
            ->all();
        $touchedIdsAfter = $touchedIds;
        $touchedIdsAfter['collections'] = [...$touchedIds['collections'], ...$billingResult['created_ids']];
        $touchedIdsAfter['renovations'] = [...$touchedIds['renovations'], ...$createdRenovationIds];

        $mergeNote = $this->mergeObservation($target, $sourceCodes, $request->reason, $preview);

        foreach ($sources as $source) {
            $originalFee = (float) ($source->fee_anual ?? 0);
            $originalTotal = (float) ($source->total_amount ?? 0);
            $originalMembers = (int) ($source->family_members ?? 0);

            $source->status = 'EXCLUIDO';
            $source->fee_anual = 0;
            $source->total_amount = 0;
            $source->family_members = 0;
            $source->activated_at = null;
            // Sus avisos ya los resolvió el calendario unificado, con su propia foto de restauración.
            DroppedAffiliationCollections::withoutAutomaticCancellation(fn (): bool => $source->save());

            $source->status_log_affiliations()->create([
                'affiliation_id' => $source->id,
                'action' => self::MERGE_ACTION,
                'observation' => 'Unificada en '.$target->code.'. Tarifa previa: '.$originalFee.', monto: '.$originalTotal.', personas: '.$originalMembers.'. Motivo: '.$request->reason,
                'updated_by' => $request->actorName,
            ]);

            $source->affiliationObservations()->create([
                'description' => $mergeNote,
                'created_by' => $request->actorUserId !== null ? (string) $request->actorUserId : $request->actorName,
            ]);
        }

        $target->status_log_affiliations()->create([
            'affiliation_id' => $target->id,
            'action' => self::MERGE_ACTION,
            'observation' => 'Recibió el grupo familiar de: '.implode(', ', $sourceCodes)
                .'. Vigencia: '.($previousEffectiveDate !== '' ? $previousEffectiveDate : 'sin fecha').' → '.$target->effective_date
                .'. Frecuencia: '.($previousFrequency !== '' ? $previousFrequency : 'sin frecuencia').' → '.$target->payment_frequency
                .'. Avisos nuevos: '.count($billingResult['created_ids']).'. Motivo: '.$request->reason,
            'updated_by' => $request->actorName,
        ]);

        $target->affiliationObservations()->create([
            'description' => $mergeNote,
            'created_by' => $request->actorUserId !== null ? (string) $request->actorUserId : $request->actorName,
        ]);

        $merge = AffiliationFamilyMerge::query()->create([
            'public_id' => (string) Str::uuid(),
            'target_affiliation_id' => (int) $target->id,
            'target_code' => (string) $target->code,
            'source_affiliation_ids' => $sources->map(static fn (Affiliation $source): int => (int) $source->id)->values()->all(),
            'source_codes' => $sourceCodes,
            'titular_affiliate_id' => $request->titularAffiliateId,
            'reason' => $request->reason,
            'status' => AffiliationFamilyMerge::STATUS_APPLIED,
            'snapshot_before' => $snapshotBefore,
            'snapshot_after' => FamilyMergeSnapshot::capture($touchedIdsAfter),
            'summary' => [
                'moved_affiliate_ids' => $movedAffiliateIds,
                'fee_anual_before' => (float) $preview->target['fee_anual'],
                'fee_anual_after' => (float) $target->fee_anual,
                'family_members_after' => (int) $target->family_members,
                'cancelled_collections' => $cancelledCollections,
                'cancelled_renovations' => (int) $cancelledRenovations,
                'updated_telemedicine_patients' => $updatedPatients,
                'renovation_refreshed' => $renovationRefreshed,
                'effective_date_before' => $previousEffectiveDate,
                'effective_date_after' => (string) $target->effective_date,
                'payment_frequency_before' => $previousFrequency,
                'payment_frequency_after' => (string) $target->payment_frequency,
                'cancelled_collection_ids' => $billingResult['cancelled_ids'],
                'created_collection_ids' => $billingResult['created_ids'],
                'created_renovation_ids' => $createdRenovationIds,
            ],
            'merged_by' => $request->actorUserId,
            'merged_by_name' => $request->actorName,
            'merged_at' => now(),
        ]);

        SecurityAudit::log('AUDIT_AFFILIATION_FAMILY_MERGE', 'affiliations.merge-family', [
            'merge_id' => $merge->id,
            'target_id' => $target->id,
            'target_code' => $target->code,
            'source_codes' => $sourceCodes,
            'moved_affiliate_ids' => $movedAffiliateIds,
            'titular_affiliate_id' => $request->titularAffiliateId,
            'new_fee_anual' => (float) $target->fee_anual,
            'new_family_members' => (int) $target->family_members,
            'effective_date' => (string) $target->effective_date,
            'payment_frequency' => (string) $target->payment_frequency,
            'created_collection_ids' => $billingResult['created_ids'],
            'reason' => $request->reason,
        ]);

        Log::info('Grupo familiar unificado en afiliación individual', [
            'target_code' => $target->code,
            'source_codes' => $sourceCodes,
            'family_members' => (int) $target->family_members,
        ]);

        $targetId = (int) $target->id;
        $actorUserId = $request->actorUserId;

        DB::afterCommit(function () use ($targetId, $actorUserId): void {
            RegenerateMergedAffiliationDocumentsJob::dispatch($targetId, $actorUserId);
        });

        return new MergeIndividualAffiliationsResult(
            mergeId: (int) $merge->id,
            targetAffiliationId: (int) $target->id,
            targetCode: (string) $target->code,
            excludedCodes: $sourceCodes,
            movedAffiliateIds: $movedAffiliateIds,
            newFeeAnual: (float) $target->fee_anual,
            newTotalAmount: (float) $target->total_amount,
            newFamilyMembers: (int) $target->family_members,
            cancelledCollections: $cancelledCollections,
            recalculatedCollections: count($billingResult['created_ids']),
            cancelledRenovations: (int) $cancelledRenovations,
            updatedTelemedicinePatients: $updatedPatients,
        );
    }

    /**
     * Filas que la unificación puede modificar, por tabla: la base de la foto de antes y después.
     *
     * @param  SupportCollection<int, Affiliation>  $sources
     * @param  SupportCollection<int, Affiliate>  $affiliates
     * @param  array{
     *     pendingCollections: SupportCollection<int, Collection>,
     *     pendingAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     targetAnnualCollections: SupportCollection<int, AnnualCollection>,
     *     openRenovations: SupportCollection<int, Renovation>,
     *     telemedicinePatients: SupportCollection<int, TelemedicinePatient>
     * }  $bundle
     * @return array<string, list<int>>
     */
    private function touchedRowIds(Affiliation $target, SupportCollection $sources, SupportCollection $affiliates, array $bundle): array
    {
        $ids = static fn (SupportCollection $models): array => $models
            ->map(static fn ($model): int => (int) $model->id)
            ->values()
            ->all();

        return [
            'affiliations' => [(int) $target->id, ...$ids($sources)],
            'affiliates' => $ids($affiliates),
            'collections' => $ids($bundle['pendingCollections']),
            'annual_collections' => [
                ...$ids($bundle['pendingAnnualCollections']),
                ...$ids($bundle['targetAnnualCollections']),
            ],
            'renovations' => $ids($bundle['openRenovations']),
            'telemedicine_patients' => $ids($bundle['telemedicinePatients']),
        ];
    }

    private function syncTitularColumns(Affiliation $target, Affiliate $titular): void
    {
        $target->full_name_ti = $titular->full_name;
        $target->nro_identificacion_ti = $titular->nro_identificacion;
        $target->sex_ti = $titular->sex;
        $target->age = $titular->age;
        $target->birth_date_ti = $titular->birth_date;
        $target->adress_ti = $titular->address;
        $target->city_id_ti = $titular->city_id;
        $target->state_id_ti = $titular->state_id;
        $target->country_id_ti = $titular->country_id;
        $target->region_ti = $titular->region;
        $target->phone_ti = $titular->phone;
        $target->email_ti = $titular->email;

        if (filled($titular->document)) {
            $target->document = $titular->document;
        }
    }

    /**
     * Qué pasa con la renovación: todo el grupo renueva en la fecha que sale de la nueva vigencia y
     * las propuestas abiertas que dejan de valer se anulan.
     *
     * @param  SupportCollection<int, Renovation>  $openRenovations
     * @return list<string>
     */
    private function renewalWarnings(Affiliation $target, SupportCollection $openRenovations, ?Carbon $renewalDate): array
    {
        $warnings = [];

        foreach ($openRenovations as $renovation) {
            if ($renovation->status === PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD) {
                $warnings[] = 'La póliza '.$renovation->code_affiliation.' está en PERÍODO DE RENOVACIÓN: esa propuesta se anula y el grupo renueva con la nueva vigencia.';
            }
        }

        if ($renewalDate !== null && strtoupper((string) $target->status) === PrepareAffiliationRenovations::AFFILIATION_STATUS_ACTIVE) {
            $warnings[] = 'Todo el grupo renovará el '.$renewalDate->format('d/m/Y').'. La propuesta de renovación de '.$target->code.' se recalcula en el acto (no espera a la corrida de las 6:00).';
        }

        $warnings[] = 'Los cupos clínicos ya consumidos se conservan por persona, pero su período de conteo pasa a ser el aniversario de la nueva vigencia.';

        return $warnings;
    }

    private function hasFrozenWhiteCompanyPricing(Affiliation $affiliation): bool
    {
        return filled($affiliation->white_company_fee_id)
            || filled($affiliation->white_company_neta)
            || filled($affiliation->white_company_sale_price);
    }

    /**
     * La planificación anual de las pólizas origen deja de aplicar: se anula (no se borra).
     *
     * @param  list<string>  $sourceCodes
     */
    private function cancelSourceAnnualCollections(array $sourceCodes): int
    {
        if ($sourceCodes === []) {
            return 0;
        }

        return (int) AnnualCollection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->update([
                'status' => self::COLLECTION_CANCELLED_STATUS,
                'affiliate_status' => 'EXCLUIDO',
            ]);
    }

    /**
     * Anula las propuestas de renovación que dejan de valer: las de las pólizas origen y las de la
     * destino con otra fecha de renovación (la nueva vigencia la mueve). La vigente se recalcula aparte.
     *
     * @param  SupportCollection<int, Renovation>  $openRenovations
     */
    private function cancelStaleRenovations(Affiliation $target, SupportCollection $openRenovations, ?Carbon $renewalDate, string $actorName): int
    {
        $staleIds = $openRenovations
            ->filter(function (Renovation $renovation) use ($target, $renewalDate): bool {
                if ((int) $renovation->affiliation_id !== (int) $target->id) {
                    return true;
                }

                $current = CorporatePaymentFrequencyChanger::parseDueDate((string) $renovation->getRawOriginal('date_renewal'));

                return $renewalDate === null || $current === null || ! $current->isSameDay($renewalDate);
            })
            ->map(static fn (Renovation $renovation): int => (int) $renovation->id)
            ->values()
            ->all();

        if ($staleIds === []) {
            return 0;
        }

        return (int) Renovation::query()
            ->whereIn('id', $staleIds)
            ->update([
                'status' => self::RENOVATION_CANCELLED_STATUS,
                'updated_by' => $actorName,
            ]);
    }

    /**
     * @param  SupportCollection<int, TelemedicinePatient>  $patients
     */
    private function reassignTelemedicinePatients(Affiliation $target, SupportCollection $patients): int
    {
        if ($patients->isEmpty()) {
            return 0;
        }

        return TelemedicinePatient::query()
            ->whereIn('id', $patients->modelKeys())
            ->update([
                'afilliation_id' => $target->id,
                'code_affiliation' => $target->code,
                'plan_id' => $target->plan_id,
                'coverage_id' => $target->coverage_id,
                'status_affiliation' => $target->status,
                'type_affiliation' => 'INDIVIDUAL',
            ]);
    }

    /**
     * @param  list<string>  $sourceCodes
     */
    private function mergeObservation(
        Affiliation $target,
        array $sourceCodes,
        string $reason,
        MergeIndividualAffiliationsPreview $preview,
    ): string {
        return 'Unificación de grupo familiar en '.$target->code.'. '
            .'Pólizas origen (EXCLUIDO, no borradas): '.implode(', ', $sourceCodes).'. '
            .'Titular resultante: '.$target->full_name_ti.' ('.$target->nro_identificacion_ti.'). '
            .'Personas: '.$preview->newFamilyMembers.'. '
            .'Tarifa anual: '.number_format($preview->newFeeAnual, 2, ',', '.').'. '
            .'Motivo: '.$reason;
    }
}
