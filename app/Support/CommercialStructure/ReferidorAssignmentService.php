<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\ReferidorAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class ReferidorAssignmentService
{
    public const GENERAL_AGENCY_IDS_FIELD = 'referred_general_agency_ids';

    public const AGENT_IDS_FIELD = 'referred_agent_ids';

    /**
     * @var list<string>
     */
    public const AGENT_TYPE_DEFINITIONS = ['AGENTE', 'SUB-AGENTE'];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function mergeFormState(Agency|Agent|null $record, array $data): array
    {
        $data[self::GENERAL_AGENCY_IDS_FIELD] = $record instanceof Agency || $record instanceof Agent
            ? ($record->exists ? self::assignedGeneralAgencyIds($record) : [])
            : [];
        $data[self::AGENT_IDS_FIELD] = $record instanceof Agency || $record instanceof Agent
            ? ($record->exists ? self::assignedAgentIds($record) : [])
            : [];

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{general_agency_ids: list<int>, agent_ids: list<int>}
     */
    public static function capture(array $data): array
    {
        return [
            'general_agency_ids' => self::normalizeIds($data[self::GENERAL_AGENCY_IDS_FIELD] ?? []),
            'agent_ids' => self::normalizeIds($data[self::AGENT_IDS_FIELD] ?? []),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function strip(array $data): array
    {
        unset($data[self::GENERAL_AGENCY_IDS_FIELD], $data[self::AGENT_IDS_FIELD]);

        return $data;
    }

    public static function isReferrer(Agency|Agent $record): bool
    {
        return (bool) $record->is_referidor;
    }

    public static function isReferrerAgency(Agency $agency): bool
    {
        return self::isReferrer($agency);
    }

    public static function isReferrerAgent(Agent $agent): bool
    {
        return self::isReferrer($agent);
    }

    public static function hasAssignedReferrer(Agency|Agent $record): bool
    {
        $loaded = self::loadedReferrers($record);

        if ($loaded !== null) {
            return $loaded !== [];
        }

        return filled($record->referidor_id) || filled($record->referidor_agent_id);
    }

    public static function assignedReferrerLabel(Agency|Agent $record): ?string
    {
        $labels = [];

        foreach (self::assignedReferrers($record) as $referrer) {
            $label = $referrer instanceof Agent
                ? self::agentLabel($referrer)
                : self::generalAgencyLabel($referrer);

            if ($label !== '') {
                $labels[] = $label;
            }
        }

        return $labels === [] ? null : implode("\n", $labels);
    }

    /**
     * @return list<Agency|Agent>
     */
    public static function assignedReferrers(Agency|Agent $record): array
    {
        $loaded = self::loadedReferrers($record);

        if ($loaded !== null) {
            return $loaded;
        }

        $legacy = self::legacyReferrer($record);

        return $legacy !== null ? [$legacy] : [];
    }

    public static function referredGeneralAgenciesText(Agency|Agent $record): string
    {
        $labels = $record->referredGeneralAgencies
            ->loadMissing('typeAgency')
            ->map(fn (Agency $agency): string => self::generalAgencyLabel($agency))
            ->filter()
            ->values();

        return $labels->isEmpty() ? 'Ninguna' : $labels->implode("\n");
    }

    public static function referredAgentsText(Agency|Agent $record): string
    {
        $labels = $record->referredAgents
            ->map(fn (Agent $agent): string => self::agentLabel($agent))
            ->filter()
            ->values();

        return $labels->isEmpty() ? 'Ninguno' : $labels->implode("\n");
    }

    /**
     * @param  array{general_agency_ids: list<int>, agent_ids: list<int>}  $assignments
     */
    public static function sync(Agency|Agent $referrer, array $assignments): void
    {
        DB::transaction(function () use ($referrer, $assignments): void {
            $referrer->refresh();

            if (! self::isReferrer($referrer)) {
                self::clearAssignments($referrer);

                return;
            }

            $generalAgencyIds = self::normalizeIds($assignments['general_agency_ids'] ?? []);
            $agentIds = self::normalizeIds($assignments['agent_ids'] ?? []);

            if ($referrer instanceof Agency) {
                $referrerId = (int) $referrer->id;
                $generalAgencyIds = array_values(array_filter(
                    $generalAgencyIds,
                    fn (int $id): bool => $id !== $referrerId,
                ));
            }

            if ($referrer instanceof Agent) {
                $referrerId = (int) $referrer->id;
                $agentIds = array_values(array_filter(
                    $agentIds,
                    fn (int $id): bool => $id !== $referrerId,
                ));
            }

            self::assertGeneralAgenciesAssignable($referrer, $generalAgencyIds);
            self::assertAgentsAssignable($referrer, $agentIds);

            self::syncReferredAgencies($referrer, $generalAgencyIds);
            self::syncReferredAgents($referrer, $agentIds);
        });
    }

    /**
     * @return list<int>
     */
    public static function assignedGeneralAgencyIds(Agency|Agent $referrer): array
    {
        if (! $referrer->exists) {
            return [];
        }

        return ReferidorAssignment::query()
            ->forReferrer($referrer)
            ->whereNotNull('referred_agency_id')
            ->orderBy('referred_agency_id')
            ->pluck('referred_agency_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function assignedAgentIds(Agency|Agent $referrer): array
    {
        if (! $referrer->exists) {
            return [];
        }

        return ReferidorAssignment::query()
            ->forReferrer($referrer)
            ->whereNotNull('referred_agent_id')
            ->orderBy('referred_agent_id')
            ->pluck('referred_agent_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchGeneralAgencies(string $search, Agency|Agent|null $referrer): array
    {
        $term = trim($search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return self::assignableGeneralAgenciesQuery($referrer)
            ->where(function (Builder $query) use ($term): void {
                $query->where('code', 'like', '%'.$term.'%')
                    ->orWhere('name_corporative', 'like', '%'.$term.'%')
                    ->orWhere('rif', 'like', '%'.$term.'%');
            })
            ->with('typeAgency:id,definition')
            ->orderBy('name_corporative')
            ->limit(40)
            ->get(['id', 'code', 'name_corporative', 'rif', 'status', 'agency_type_id'])
            ->mapWithKeys(fn (Agency $agency): array => [
                (int) $agency->id => self::generalAgencyLabel($agency),
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchAgents(string $search, Agency|Agent|null $referrer): array
    {
        $term = trim($search);

        if (mb_strlen($term) < 2) {
            return [];
        }

        return self::assignableAgentsQuery($referrer)
            ->with('typeAgent:id,definition')
            ->where(function (Builder $query) use ($term): void {
                $query->where('name', 'like', '%'.$term.'%')
                    ->orWhere('ci', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%')
                    ->orWhere('code_agent', 'like', '%'.$term.'%')
                    ->orWhere('id', $term);
            })
            ->orderBy('name')
            ->limit(40)
            ->get(['id', 'name', 'ci', 'code_agent', 'agent_type_id', 'status'])
            ->mapWithKeys(fn (Agent $agent): array => [
                (int) $agent->id => self::agentLabel($agent),
            ])
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    public static function generalAgencyLabels(array $ids): array
    {
        $ids = self::normalizeIds($ids);

        if ($ids === []) {
            return [];
        }

        return Agency::query()
            ->with('typeAgency:id,definition')
            ->whereIn('id', $ids)
            ->get(['id', 'code', 'name_corporative', 'rif', 'status', 'agency_type_id'])
            ->mapWithKeys(fn (Agency $agency): array => [
                (int) $agency->id => self::generalAgencyLabel($agency),
            ])
            ->all();
    }

    /**
     * @param  list<int|string>  $ids
     * @return array<int, string>
     */
    public static function agentLabels(array $ids): array
    {
        $ids = self::normalizeIds($ids);

        if ($ids === []) {
            return [];
        }

        return Agent::query()
            ->with('typeAgent:id,definition')
            ->whereIn('id', $ids)
            ->get(['id', 'name', 'ci', 'code_agent', 'agent_type_id', 'status'])
            ->mapWithKeys(fn (Agent $agent): array => [
                (int) $agent->id => self::agentLabel($agent),
            ])
            ->all();
    }

    public static function generalAgencyLabel(Agency $agency): string
    {
        $name = trim((string) ($agency->name_corporative ?? ''));
        $code = trim((string) ($agency->code ?? ''));
        $status = trim((string) ($agency->status ?? ''));
        $type = trim((string) ($agency->typeAgency?->definition ?? ''));

        $label = $code !== '' && $name !== ''
            ? $code.' — '.$name
            : ($name !== '' ? $name : ($code !== '' ? $code : 'Agencia #'.$agency->id));

        if ($type !== '') {
            $label .= ' ('.$type.')';
        }

        return $status !== '' ? $label.' ('.$status.')' : $label;
    }

    public static function agentLabel(Agent $agent): string
    {
        $name = trim((string) ($agent->name ?? ''));
        $code = trim((string) ($agent->code_agent ?? ''));
        if ($code === '') {
            $code = 'AGT-000'.$agent->id;
        }

        $type = trim((string) ($agent->typeAgent?->definition ?? ''));
        $label = $code.' — '.($name !== '' ? $name : 'Sin nombre');

        return $type !== '' ? $label.' ('.$type.')' : $label;
    }

    /**
     * @return list<int>
     */
    public static function normalizeIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $ids = [];

        foreach ($value as $item) {
            if ($item === null || $item === '') {
                continue;
            }

            $id = (int) $item;

            if ($id > 0) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @return list<Agency|Agent>|null
     */
    private static function loadedReferrers(Agency|Agent $record): ?array
    {
        $agenciesLoaded = $record->relationLoaded('referrerAgencies');
        $agentsLoaded = $record->relationLoaded('referrerAgents');

        if (! $agenciesLoaded && ! $agentsLoaded) {
            return null;
        }

        $agencies = $agenciesLoaded ? $record->getRelation('referrerAgencies') : [];
        $agents = $agentsLoaded ? $record->getRelation('referrerAgents') : [];

        $referrers = [];

        foreach ($agents as $agent) {
            if ($agent instanceof Agent) {
                $referrers[] = $agent;
            }
        }

        foreach ($agencies as $agency) {
            if ($agency instanceof Agency) {
                $referrers[] = $agency;
            }
        }

        return $referrers;
    }

    private static function legacyReferrer(Agency|Agent $record): Agency|Agent|null
    {
        if (filled($record->referidor_agent_id)) {
            $agent = $record->relationLoaded('referidorAgent')
                ? $record->getRelation('referidorAgent')
                : $record->referidorAgent;

            return $agent instanceof Agent ? $agent : null;
        }

        if (filled($record->referidor_id)) {
            $agency = $record->relationLoaded('referidor')
                ? $record->getRelation('referidor')
                : $record->referidor;

            return $agency instanceof Agency ? $agency : null;
        }

        return null;
    }

    private static function clearAssignments(Agency|Agent $referrer): void
    {
        if (! $referrer->exists) {
            return;
        }

        $affectedAgencyIds = ReferidorAssignment::query()
            ->forReferrer($referrer)
            ->whereNotNull('referred_agency_id')
            ->pluck('referred_agency_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $affectedAgentIds = ReferidorAssignment::query()
            ->forReferrer($referrer)
            ->whereNotNull('referred_agent_id')
            ->pluck('referred_agent_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        ReferidorAssignment::query()
            ->forReferrer($referrer)
            ->delete();

        self::refreshLegacyColumnsForAgencies($affectedAgencyIds);
        self::refreshLegacyColumnsForAgents($affectedAgentIds);
    }

    /**
     * @param  list<int>  $ids
     */
    private static function assertGeneralAgenciesAssignable(Agency|Agent $referrer, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $count = self::assignableGeneralAgenciesQuery($referrer)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw ReferidorAssignmentException::agencyNotAssignable();
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function assertAgentsAssignable(Agency|Agent $referrer, array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $count = self::assignableAgentsQuery($referrer)
            ->whereIn('id', $ids)
            ->count();

        if ($count !== count($ids)) {
            throw ReferidorAssignmentException::agentNotAssignable();
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function syncReferredAgencies(Agency|Agent $referrer, array $ids): void
    {
        $currentIds = self::assignedGeneralAgencyIds($referrer);
        $toDetach = array_values(array_diff($currentIds, $ids));
        $toAttach = array_values(array_diff($ids, $currentIds));

        if ($toDetach !== []) {
            ReferidorAssignment::query()
                ->forReferrer($referrer)
                ->whereIn('referred_agency_id', $toDetach)
                ->delete();
        }

        foreach ($toAttach as $agencyId) {
            $agency = (new Agency)->forceFill(['id' => $agencyId]);
            $agency->exists = true;

            ReferidorAssignment::query()->create(array_merge(
                [
                    'assignment_key' => ReferidorAssignment::keyFor($referrer, $agency),
                    'created_by' => Auth::id(),
                ],
                ReferidorAssignment::referrerPayload($referrer),
                ReferidorAssignment::referredPayload($agency),
            ));
        }

        self::refreshLegacyColumnsForAgencies(array_values(array_unique([...$toDetach, ...$toAttach])));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function syncReferredAgents(Agency|Agent $referrer, array $ids): void
    {
        $currentIds = self::assignedAgentIds($referrer);
        $toDetach = array_values(array_diff($currentIds, $ids));
        $toAttach = array_values(array_diff($ids, $currentIds));

        if ($toDetach !== []) {
            ReferidorAssignment::query()
                ->forReferrer($referrer)
                ->whereIn('referred_agent_id', $toDetach)
                ->delete();
        }

        foreach ($toAttach as $agentId) {
            $agent = (new Agent)->forceFill(['id' => $agentId]);
            $agent->exists = true;

            ReferidorAssignment::query()->create(array_merge(
                [
                    'assignment_key' => ReferidorAssignment::keyFor($referrer, $agent),
                    'created_by' => Auth::id(),
                ],
                ReferidorAssignment::referrerPayload($referrer),
                ReferidorAssignment::referredPayload($agent),
            ));
        }

        self::refreshLegacyColumnsForAgents(array_values(array_unique([...$toDetach, ...$toAttach])));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function refreshLegacyColumnsForAgencies(array $ids): void
    {
        foreach ($ids as $id) {
            $agency = Agency::query()->find($id);

            if ($agency instanceof Agency) {
                self::refreshLegacyReferrerColumns($agency);
            }
        }
    }

    /**
     * @param  list<int>  $ids
     */
    private static function refreshLegacyColumnsForAgents(array $ids): void
    {
        foreach ($ids as $id) {
            $agent = Agent::query()->find($id);

            if ($agent instanceof Agent) {
                self::refreshLegacyReferrerColumns($agent);
            }
        }
    }

    private static function refreshLegacyReferrerColumns(Agency|Agent $referred): void
    {
        $assignments = ReferidorAssignment::query()
            ->forReferred($referred)
            ->orderBy('id')
            ->get(['referrer_agency_id', 'referrer_agent_id']);

        $agentReferrerId = $assignments
            ->first(fn (ReferidorAssignment $assignment): bool => filled($assignment->referrer_agent_id))
            ?->referrer_agent_id;
        $agencyReferrerId = $assignments
            ->first(fn (ReferidorAssignment $assignment): bool => filled($assignment->referrer_agency_id))
            ?->referrer_agency_id;

        $payload = [
            'referidor_id' => null,
            'referidor_agent_id' => null,
        ];

        if ($agentReferrerId !== null) {
            $payload['referidor_agent_id'] = (int) $agentReferrerId;
        } elseif ($agencyReferrerId !== null) {
            $payload['referidor_id'] = (int) $agencyReferrerId;
        }

        $referred->forceFill($payload)->saveQuietly();
    }

    private static function assignableGeneralAgenciesQuery(Agency|Agent|null $referrer = null): Builder
    {
        $query = Agency::query();

        if ($referrer instanceof Agency && $referrer->exists) {
            $query->whereKeyNot($referrer->id);
        }

        return $query;
    }

    private static function assignableAgentsQuery(Agency|Agent|null $referrer = null): Builder
    {
        $query = Agent::query()
            ->whereHas('typeAgent', function (Builder $query): void {
                $query->whereIn('definition', self::AGENT_TYPE_DEFINITIONS);
            });

        if ($referrer instanceof Agent && $referrer->exists) {
            $query->whereKeyNot($referrer->id);
        }

        return $query;
    }
}
