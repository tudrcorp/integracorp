<?php

declare(strict_types=1);

namespace App\Support\Upgrades;

use App\Enums\UpgradeNetworkVisibility;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\Plan;
use App\Models\Upgrade;
use App\Models\UpgradeCoverage;
use App\Models\UpgradeNetworkTarget;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Qué upgrades puede cotizar un usuario de la red comercial (agentes, agencias Master y General).
 *
 * «Red seleccionada» se hereda hacia abajo: quien está debajo de una agencia o agente elegido
 * también lo ve; hacia arriba no. Por eso se parte del usuario y se sube por la jerarquía
 * (`agents.owner_agent`, `agents.owner_code`, `agencies.owner_code`) y luego se busca si algo de esa
 * cadena fue elegido. Lo que no se pueda resolver no da acceso.
 */
final class UpgradeNetworkAudience
{
    private const MAX_DEPTH = 20;

    private const SEARCH_LIMIT = 50;

    private const HEAD_OFFICE_CODE = 'TDG-100';

    /**
     * El usuario y todo lo que tiene por encima en la jerarquía comercial.
     *
     * @return array{agency_ids: list<int>, agent_ids: list<int>}
     */
    public static function chainFor(User $user): array
    {
        $agentIds = [];
        $agencyCodes = [];

        if (filled($user->agent_id)) {
            $pending = (int) $user->agent_id;

            for ($depth = 0; $depth < self::MAX_DEPTH && $pending > 0 && ! isset($agentIds[$pending]); $depth++) {
                $agent = Agent::query()->select(['id', 'owner_code', 'owner_agent'])->find($pending);

                if ($agent === null) {
                    break;
                }

                $agentIds[$pending] = true;

                if (filled($agent->owner_code)) {
                    $agencyCodes[] = (string) $agent->owner_code;
                }

                $pending = is_numeric($agent->owner_agent) ? (int) $agent->owner_agent : 0;
            }
        }

        if ($user->is_agency && filled($user->code_agency)) {
            $agencyCodes[] = (string) $user->code_agency;
        }

        return [
            'agency_ids' => self::agencyChain($agencyCodes),
            'agent_ids' => array_keys($agentIds),
        ];
    }

    /**
     * @param  list<string>  $codes
     * @return list<int>
     */
    private static function agencyChain(array $codes): array
    {
        $agencyIds = [];
        $seen = [];

        for ($depth = 0; $depth < self::MAX_DEPTH && $codes !== []; $depth++) {
            $codes = array_values(array_unique(array_filter(
                array_map(static fn (string $code): string => mb_strtoupper(trim($code), 'UTF-8'), $codes),
                static fn (string $code): bool => $code !== '' && ! isset($seen[$code]),
            )));

            foreach ($codes as $code) {
                $seen[$code] = true;
            }

            if ($codes === []) {
                break;
            }

            $agencies = Agency::query()->whereIn('code', $codes)->get(['id', 'owner_code']);
            $codes = [];

            foreach ($agencies as $agency) {
                $agencyIds[(int) $agency->getKey()] = true;

                if (filled($agency->owner_code)) {
                    $codes[] = (string) $agency->owner_code;
                }
            }
        }

        return array_keys($agencyIds);
    }

    /**
     * Upgrades activos que el usuario de la red puede cotizar. Un upgrade inactivo no lo ve nadie.
     *
     * @return list<int>
     */
    public static function visibleUpgradeIds(User $user): array
    {
        return once(static function () use ($user): array {
            $chain = self::chainFor($user);

            return Upgrade::query()
                ->active()
                ->where(function (Builder $query) use ($chain): void {
                    $query->where('network_visibility', UpgradeNetworkVisibility::AllNetwork->value)
                        ->orWhere(function (Builder $selected) use ($chain): void {
                            $selected->where('network_visibility', UpgradeNetworkVisibility::Selected->value)
                                ->whereHas('networkTargets', function (Builder $targets) use ($chain): void {
                                    $targets->whereIn('agency_id', $chain['agency_ids'] === [] ? [0] : $chain['agency_ids'])
                                        ->orWhereIn('agent_id', $chain['agent_ids'] === [] ? [0] : $chain['agent_ids']);
                                });
                        });
                })
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        });
    }

    /**
     * Tarjetas que ve quien está cotizando: Negocios todas; la red, solo las suyas.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function cardsFor(?User $user, bool $network): array
    {
        $cards = UpgradeCardCatalog::cards();

        if (! $network) {
            return $cards;
        }

        return $user === null ? [] : array_intersect_key($cards, array_flip(self::visibleUpgradeIds($user)));
    }

    /**
     * Quita de las opciones de plan los nacidos de un upgrade que la red no puede ver: el plan
     * hereda la visibilidad de su upgrade.
     *
     * @template TOptions of Collection<int|string, mixed>|array<int|string, mixed>
     *
     * @param  TOptions  $options
     * @return TOptions
     */
    public static function filterPlanOptions(iterable $options, ?User $user): iterable
    {
        $hidden = self::hiddenPlanIds($user);

        if ($hidden === []) {
            return $options;
        }

        return $options instanceof Collection
            ? $options->except($hidden)
            : array_diff_key($options, array_flip($hidden));
    }

    /**
     * Si el usuario de la red puede cotizar esos planes.
     *
     * @param  array<int, mixed>  $planIds
     * @return list<string> nombres de los que no puede
     */
    public static function rejectedPlanNames(?User $user, array $planIds): array
    {
        $ids = array_intersect(self::hiddenPlanIds($user), array_map('intval', array_filter($planIds, 'is_numeric')));

        return $ids === [] ? [] : Plan::query()->whereIn('id', $ids)->pluck('description')->map(static fn (mixed $name): string => (string) $name)->all();
    }

    /**
     * @return list<int>
     */
    private static function hiddenPlanIds(?User $user): array
    {
        $visible = $user === null ? [] : self::visibleUpgradeIds($user);

        return Plan::query()
            ->whereNotNull('source_upgrade_id')
            ->when($visible !== [], static fn ($query) => $query->whereNotIn('source_upgrade_id', $visible))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * Nombres de los upgrades de las filas del formulario que el usuario no puede cotizar.
     * Se valida por la cobertura, que es lo que fija el precio, no por lo que diga la fila.
     *
     * @param  array<int|string, mixed>  $state  filas del repetidor «upgrades»
     * @return list<string>
     */
    public static function rejectedNames(?User $user, array $state): array
    {
        $coverageIds = [];

        foreach ($state as $row) {
            if (is_array($row) && is_numeric($row['upgrade_coverage_id'] ?? null)) {
                $coverageIds[] = (int) $row['upgrade_coverage_id'];
            }
        }

        if ($coverageIds === []) {
            return [];
        }

        $allowed = $user === null ? [] : self::visibleUpgradeIds($user);

        return UpgradeCoverage::query()
            ->with('upgrade:id,name')
            ->whereIn('id', array_values(array_unique($coverageIds)))
            ->get()
            ->reject(static fn (UpgradeCoverage $coverage): bool => in_array((int) $coverage->upgrade_id, $allowed, true))
            ->map(static fn (UpgradeCoverage $coverage): string => (string) ($coverage->upgrade?->name ?? 'Upgrade'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public static function searchAgencies(string $search): array
    {
        return self::agencyOptions(self::agencyQuery()
            ->where(function (Builder $query) use ($search): void {
                $query->where('code', 'like', '%'.$search.'%')
                    ->orWhere('name_corporative', 'like', '%'.$search.'%');
            })
            ->orderBy('name_corporative')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'code', 'name_corporative']));
    }

    /**
     * @return array<int, string>
     */
    public static function searchAgents(string $search): array
    {
        return self::agentOptions(Agent::query()
            ->where('status', 'ACTIVO')
            ->where(function (Builder $query) use ($search): void {
                $query->where('name', 'like', '%'.$search.'%')
                    ->orWhere('code_agent', 'like', '%'.$search.'%');
            })
            ->orderBy('name')
            ->limit(self::SEARCH_LIMIT)
            ->get(['id', 'code_agent', 'name']));
    }

    /**
     * Etiquetas de lo ya elegido, aunque hoy esté inactivo.
     *
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    public static function agencyLabels(array $ids): array
    {
        return self::agencyOptions(Agency::query()->whereIn('id', $ids)->get(['id', 'code', 'name_corporative']));
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return array<int, string>
     */
    public static function agentLabels(array $ids): array
    {
        return self::agentOptions(Agent::query()->whereIn('id', $ids)->get(['id', 'code_agent', 'name']));
    }

    /**
     * Texto para la ficha del upgrade: a quién se le eligió.
     *
     * @return list<string>
     */
    public static function targetLabels(Upgrade $upgrade): array
    {
        $targets = $upgrade->networkTargets()->get(['agency_id', 'agent_id']);

        return [
            ...array_values(self::agencyLabels($targets->pluck('agency_id')->filter()->all())),
            ...array_values(self::agentLabels($targets->pluck('agent_id')->filter()->all())),
        ];
    }

    /**
     * Reemplaza la selección del upgrade. Devuelve lo elegido antes y después para la auditoría.
     *
     * @param  list<int>  $agencyIds
     * @param  list<int>  $agentIds
     * @return array{changed: bool, before: array{agencies: list<int>, agents: list<int>}, after: array{agencies: list<int>, agents: list<int>}}
     */
    public static function syncTargets(Upgrade $upgrade, array $agencyIds, array $agentIds, string $actor): array
    {
        $current = UpgradeNetworkTarget::query()->where('upgrade_id', $upgrade->getKey())->lockForUpdate()->get(['id', 'agency_id', 'agent_id']);
        $before = [
            'agencies' => self::sortedIds($current->pluck('agency_id')->filter()->all()),
            'agents' => self::sortedIds($current->pluck('agent_id')->filter()->all()),
        ];
        $agencyIds = $agencyIds === [] ? [] : Agency::query()->whereIn('id', $agencyIds)->pluck('id')->all();
        $agentIds = $agentIds === [] ? [] : Agent::query()->whereIn('id', $agentIds)->pluck('id')->all();
        $after = ['agencies' => self::sortedIds($agencyIds), 'agents' => self::sortedIds($agentIds)];

        if ($before === $after) {
            return ['changed' => false, 'before' => $before, 'after' => $after];
        }

        UpgradeNetworkTarget::query()
            ->where('upgrade_id', $upgrade->getKey())
            ->where(function (Builder $query) use ($after): void {
                $query->where(fn (Builder $agencies) => $agencies->whereNotNull('agency_id')->whereNotIn('agency_id', $after['agencies'] === [] ? [0] : $after['agencies']))
                    ->orWhere(fn (Builder $agents) => $agents->whereNotNull('agent_id')->whereNotIn('agent_id', $after['agents'] === [] ? [0] : $after['agents']));
            })
            ->delete();

        foreach (array_diff($after['agencies'], $before['agencies']) as $agencyId) {
            UpgradeNetworkTarget::query()->create(['upgrade_id' => $upgrade->getKey(), 'agency_id' => $agencyId, 'created_by' => $actor]);
        }

        foreach (array_diff($after['agents'], $before['agents']) as $agentId) {
            UpgradeNetworkTarget::query()->create(['upgrade_id' => $upgrade->getKey(), 'agent_id' => $agentId, 'created_by' => $actor]);
        }

        return ['changed' => true, 'before' => $before, 'after' => $after];
    }

    /**
     * @return Builder<Agency>
     */
    private static function agencyQuery(): Builder
    {
        return Agency::query()->where('status', 'ACTIVO')->where('code', '!=', self::HEAD_OFFICE_CODE);
    }

    /**
     * @param  Collection<int, Agency>  $agencies
     * @return array<int, string>
     */
    private static function agencyOptions(Collection $agencies): array
    {
        return $agencies
            ->mapWithKeys(static fn (Agency $agency): array => [(int) $agency->getKey() => trim($agency->code.' · '.$agency->name_corporative, ' ·')])
            ->all();
    }

    /**
     * @param  Collection<int, Agent>  $agents
     * @return array<int, string>
     */
    private static function agentOptions(Collection $agents): array
    {
        return $agents
            ->mapWithKeys(static fn (Agent $agent): array => [(int) $agent->getKey() => trim($agent->code_agent.' · '.$agent->name, ' ·')])
            ->all();
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<int>
     */
    private static function sortedIds(array $ids): array
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));
        sort($ids);

        return $ids;
    }
}
