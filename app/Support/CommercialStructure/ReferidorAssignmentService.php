<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReferidorAssignmentService
{
    public const MASTER_AGENCY_TYPE_ID = 1;

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
        return filled($record->referidor_id) || filled($record->referidor_agent_id);
    }

    public static function assignedReferrerLabel(Agency|Agent $record): ?string
    {
        if (filled($record->referidor_agent_id)) {
            $agent = $record->referidorAgent;

            return $agent instanceof Agent ? self::agentLabel($agent) : null;
        }

        if (filled($record->referidor_id)) {
            $agency = $record->referidor;

            return $agency instanceof Agency ? self::generalAgencyLabel($agency) : null;
        }

        return null;
    }

    public static function referredGeneralAgenciesText(Agency|Agent $record): string
    {
        $labels = $record->referredGeneralAgencies
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

            self::syncGeneralAgencies($referrer, $generalAgencyIds);
            self::syncAgents($referrer, $agentIds);
        });
    }

    /**
     * @return list<int>
     */
    public static function assignedGeneralAgencyIds(Agency|Agent $referrer): array
    {
        $owner = self::referrerOwner($referrer);

        if ($owner === null) {
            return [];
        }

        return Agency::query()
            ->where($owner['column'], $owner['id'])
            ->where('agency_type_id', '!=', self::MASTER_AGENCY_TYPE_ID)
            ->orderBy('name_corporative')
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public static function assignedAgentIds(Agency|Agent $referrer): array
    {
        $owner = self::referrerOwner($referrer);

        if ($owner === null) {
            return [];
        }

        return self::assignableAgentsQuery($referrer)
            ->where($owner['column'], $owner['id'])
            ->orderBy('name')
            ->pluck('id')
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
            ->orderBy('name_corporative')
            ->limit(40)
            ->get(['id', 'code', 'name_corporative', 'rif', 'status'])
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
            ->whereIn('id', $ids)
            ->get(['id', 'code', 'name_corporative', 'rif', 'status'])
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

        $label = $code !== '' && $name !== ''
            ? $code.' — '.$name
            : ($name !== '' ? $name : ($code !== '' ? $code : 'Agencia #'.$agency->id));

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

    private static function clearAssignments(Agency|Agent $referrer): void
    {
        $owner = self::referrerOwner($referrer);

        if ($owner === null) {
            return;
        }

        $cleared = [
            'referidor_id' => null,
            'referidor_agent_id' => null,
        ];

        Agency::query()
            ->where($owner['column'], $owner['id'])
            ->update($cleared);

        Agent::query()
            ->where($owner['column'], $owner['id'])
            ->update($cleared);
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
    private static function syncGeneralAgencies(Agency|Agent $referrer, array $ids): void
    {
        $owner = self::referrerOwner($referrer);

        if ($owner === null) {
            return;
        }

        $cleared = [
            'referidor_id' => null,
            'referidor_agent_id' => null,
        ];

        Agency::query()
            ->where($owner['column'], $owner['id'])
            ->when($ids !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $ids))
            ->update($cleared);

        if ($ids === []) {
            return;
        }

        Agency::query()
            ->whereIn('id', $ids)
            ->update(self::assignmentPayload($referrer));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function syncAgents(Agency|Agent $referrer, array $ids): void
    {
        $owner = self::referrerOwner($referrer);

        if ($owner === null) {
            return;
        }

        $cleared = [
            'referidor_id' => null,
            'referidor_agent_id' => null,
        ];

        Agent::query()
            ->where($owner['column'], $owner['id'])
            ->when($ids !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $ids))
            ->update($cleared);

        if ($ids === []) {
            return;
        }

        Agent::query()
            ->whereIn('id', $ids)
            ->update(self::assignmentPayload($referrer));
    }

    /**
     * @return array{referidor_id: int|null, referidor_agent_id: int|null}
     */
    private static function assignmentPayload(Agency|Agent $referrer): array
    {
        if ($referrer instanceof Agent) {
            return [
                'referidor_id' => null,
                'referidor_agent_id' => (int) $referrer->id,
            ];
        }

        return [
            'referidor_id' => (int) $referrer->id,
            'referidor_agent_id' => null,
        ];
    }

    /**
     * @return array{column: string, id: int}|null
     */
    private static function referrerOwner(Agency|Agent|null $referrer): ?array
    {
        if ($referrer instanceof Agent && $referrer->exists) {
            return [
                'column' => 'referidor_agent_id',
                'id' => (int) $referrer->id,
            ];
        }

        if ($referrer instanceof Agency && $referrer->exists) {
            return [
                'column' => 'referidor_id',
                'id' => (int) $referrer->id,
            ];
        }

        return null;
    }

    private static function applyAssignableOwnerConstraint(Builder $query, Agency|Agent|null $referrer): Builder
    {
        $owner = self::referrerOwner($referrer);

        return $query->where(function (Builder $constraint) use ($owner): void {
            $constraint->where(function (Builder $free): void {
                $free->whereNull('referidor_id')
                    ->whereNull('referidor_agent_id');
            });

            if ($owner === null) {
                return;
            }

            $otherColumn = $owner['column'] === 'referidor_id'
                ? 'referidor_agent_id'
                : 'referidor_id';

            $constraint->orWhere(function (Builder $owned) use ($owner, $otherColumn): void {
                $owned->where($owner['column'], $owner['id'])
                    ->whereNull($otherColumn);
            });
        });
    }

    private static function assignableGeneralAgenciesQuery(Agency|Agent|null $referrer = null): Builder
    {
        $query = Agency::query()
            ->where('agency_type_id', '!=', self::MASTER_AGENCY_TYPE_ID);

        if ($referrer instanceof Agency && $referrer->exists) {
            $query->whereKeyNot($referrer->id);
        }

        return self::applyAssignableOwnerConstraint($query, $referrer);
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

        return self::applyAssignableOwnerConstraint($query, $referrer);
    }
}
