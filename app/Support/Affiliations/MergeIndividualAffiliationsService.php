<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use App\Jobs\PrepareAffiliationRenovations;
use App\Jobs\RegenerateMergedAffiliationDocumentsJob;
use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\AnnualCollection;
use App\Models\Collection;
use App\Models\Renovation;
use App\Models\TelemedicinePatient;
use App\Support\AffiliationAffiliateFeeCalculator;
use App\Support\SecurityAudit;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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
        $sourceIds = $sources->modelKeys();
        $identifications = $affiliates
            ->map(fn (Affiliate $affiliate): string => trim((string) $affiliate->nro_identificacion))
            ->filter()
            ->values()
            ->all();

        $pendingCollections = Collection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->get();

        $pendingAnnualCollections = AnnualCollection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->get();

        $openRenovations = Renovation::query()
            ->whereIn('affiliation_id', $sourceIds)
            ->whereIn('status', [
                PrepareAffiliationRenovations::STATUS_VIGENTE,
                PrepareAffiliationRenovations::STATUS_RENOVATION_PERIOD,
            ])
            ->get();

        $telemedicinePatients = TelemedicinePatient::query()
            ->where(function ($query) use ($sourceIds, $identifications): void {
                $query->whereIn('afilliation_id', $sourceIds);

                if ($identifications !== []) {
                    $query->orWhereIn('nro_identificacion', $identifications);
                }
            })
            ->get();

        return [
            'target' => $target,
            'sources' => $sources,
            'affiliates' => $affiliates,
            'pendingCollections' => $pendingCollections,
            'pendingAnnualCollections' => $pendingAnnualCollections,
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
                $affiliates->modelKeys(),
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

            if (self::normalizeCode((string) $source->payment_frequency) !== self::normalizeCode((string) $target->payment_frequency)) {
                $blockers[] = 'La frecuencia de pago de '.$source->code.' no coincide con la de la póliza destino.';
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

        $memberRows = [];
        $projectedAnnual = 0.0;
        $projectedActive = 0;

        foreach ($affiliates as $affiliate) {
            $amounts = $this->calculator->calculateAffiliateAmounts($target, $affiliate);
            $status = strtoupper((string) ($affiliate->status ?? ''));
            $feeAfter = $amounts['annual_fee'] ?? null;

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
        $newTotalAmount = $this->calculator->totalAmountForPaymentFrequency(
            $newFeeAnual,
            (string) ($target->payment_frequency ?? 'ANUAL'),
        );

        $targetPendingCollections = Collection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->count();

        $targetPendingAnnual = AnnualCollection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->count();

        if ($targetPendingCollections + $targetPendingAnnual > 0) {
            $warnings[] = 'Las cuotas POR PAGAR de '.$target->code.' se recalcularán al nuevo monto familiar. Los recibos ya pagados no cambian.';
        }

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
            collectionsToCancel: $bundle['pendingCollections']->map(static fn (Collection $collection): array => [
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
            pendingCollectionsToRecalculate: $targetPendingCollections + $targetPendingAnnual,
        );
    }

    /**
     * @param  array{
     *     target: Affiliation,
     *     sources: SupportCollection<int, Affiliation>,
     *     affiliates: SupportCollection<int, Affiliate>,
     *     pendingCollections: SupportCollection<int, Collection>,
     *     pendingAnnualCollections: SupportCollection<int, AnnualCollection>,
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

        $cancelledCollections = $this->cancelPendingCollections($sourceCodes);
        $this->recalculateTargetPendingCollections($target);

        $cancelledRenovations = 0;
        if ($bundle['openRenovations']->isNotEmpty()) {
            $cancelledRenovations = Renovation::query()
                ->whereIn('id', $bundle['openRenovations']->modelKeys())
                ->update([
                    'status' => self::RENOVATION_CANCELLED_STATUS,
                    'updated_by' => $request->actorName,
                ]);
        }

        $updatedPatients = $this->reassignTelemedicinePatients($target, $bundle['telemedicinePatients']);

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
            $source->save();

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
            'observation' => 'Recibió el grupo familiar de: '.implode(', ', $sourceCodes).'. Motivo: '.$request->reason,
            'updated_by' => $request->actorName,
        ]);

        $target->affiliationObservations()->create([
            'description' => $mergeNote,
            'created_by' => $request->actorUserId !== null ? (string) $request->actorUserId : $request->actorName,
        ]);

        SecurityAudit::log('AUDIT_AFFILIATION_FAMILY_MERGE', 'affiliations.merge-family', [
            'target_id' => $target->id,
            'target_code' => $target->code,
            'source_codes' => $sourceCodes,
            'moved_affiliate_ids' => $movedAffiliateIds,
            'titular_affiliate_id' => $request->titularAffiliateId,
            'new_fee_anual' => (float) $target->fee_anual,
            'new_family_members' => (int) $target->family_members,
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

        $recalculated = Collection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->count()
            + AnnualCollection::query()
                ->where('affiliation_code', $target->code)
                ->where('status', self::COLLECTION_PENDING_STATUS)
                ->count();

        return new MergeIndividualAffiliationsResult(
            targetAffiliationId: (int) $target->id,
            targetCode: (string) $target->code,
            excludedCodes: $sourceCodes,
            movedAffiliateIds: $movedAffiliateIds,
            newFeeAnual: (float) $target->fee_anual,
            newTotalAmount: (float) $target->total_amount,
            newFamilyMembers: (int) $target->family_members,
            cancelledCollections: $cancelledCollections,
            recalculatedCollections: $recalculated,
            cancelledRenovations: (int) $cancelledRenovations,
            updatedTelemedicinePatients: $updatedPatients,
        );
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

    private function hasFrozenWhiteCompanyPricing(Affiliation $affiliation): bool
    {
        return filled($affiliation->white_company_fee_id)
            || filled($affiliation->white_company_neta)
            || filled($affiliation->white_company_sale_price);
    }

    /**
     * @param  list<string>  $sourceCodes
     */
    private function cancelPendingCollections(array $sourceCodes): int
    {
        if ($sourceCodes === []) {
            return 0;
        }

        $collections = Collection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->update([
                'status' => self::COLLECTION_CANCELLED_STATUS,
                'affiliate_status' => 'EXCLUIDO',
            ]);

        $annual = AnnualCollection::query()
            ->whereIn('affiliation_code', $sourceCodes)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->update([
                'status' => self::COLLECTION_CANCELLED_STATUS,
                'affiliate_status' => 'EXCLUIDO',
            ]);

        return (int) $collections + (int) $annual;
    }

    private function recalculateTargetPendingCollections(Affiliation $target): void
    {
        $payload = [
            'persons' => (string) ($target->family_members ?? 0),
            'total_amount' => $target->total_amount,
            'affiliate_full_name' => $target->full_name_ti,
            'affiliate_contact' => $target->full_name_ti,
            'affiliate_ci_rif' => $target->nro_identificacion_ti,
            'affiliate_phone' => $target->phone_ti,
            'affiliate_email' => $target->email_ti,
            'affiliate_status' => $target->status,
            'payment_frequency' => $target->payment_frequency,
            'plan_id' => $target->plan_id,
            'coverage_id' => $target->coverage_id,
        ];

        Collection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->update($payload);

        $annualPayload = $payload;
        unset($annualPayload['coverage_id']);

        if (isset($target->coverage_id)) {
            $annualPayload['coverage_id'] = $target->coverage_id;
        }

        AnnualCollection::query()
            ->where('affiliation_code', $target->code)
            ->where('status', self::COLLECTION_PENDING_STATUS)
            ->update($annualPayload);
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
