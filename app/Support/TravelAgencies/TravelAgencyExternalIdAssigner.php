<?php

declare(strict_types=1);

namespace App\Support\TravelAgencies;

use App\Models\TravelAgency;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Rellena id_agencia e id_de_agente en agencias ya existentes.
 *
 * No crea ni borra filas. No toca ningún otro campo, salvo el nombre de las
 * cuatro agencias cuyo nombre del Excel reemplaza al que había en la tabla.
 * Si el identificador ya está ocupado o hay más de una coincidencia, no escribe.
 */
final class TravelAgencyExternalIdAssigner
{
    /**
     * @return array{
     *     ready: bool,
     *     updated: int,
     *     renamed: int,
     *     unchanged: int,
     *     changes: list<array{id: int, name: string, new_name: string|null, id_agencia: int, id_de_agente: string}>,
     *     conflicts: list<string>,
     *     skipped: list<string>
     * }
     */
    public static function apply(bool $persist = true): array
    {
        $plan = self::plan();

        if (! $plan['ready'] || ! $persist || $plan['changes'] === []) {
            return $plan;
        }

        DB::transaction(function () use ($plan): void {
            foreach ($plan['changes'] as $change) {
                $agency = TravelAgency::query()->find($change['id']);

                if (! $agency instanceof TravelAgency) {
                    continue;
                }

                $attributes = [
                    'id_agencia' => $change['id_agencia'],
                    'id_de_agente' => $change['id_de_agente'],
                ];

                if ($change['new_name'] !== null) {
                    $attributes['name'] = $change['new_name'];
                }

                $agency->timestamps = false;
                $agency->fill($attributes);
                $agency->save();
            }
        });

        return $plan;
    }

    /**
     * @return array{
     *     ready: bool,
     *     updated: int,
     *     renamed: int,
     *     unchanged: int,
     *     changes: list<array{id: int, name: string, new_name: string|null, id_agencia: int, id_de_agente: string}>,
     *     conflicts: list<string>,
     *     skipped: list<string>
     * }
     */
    public static function plan(): array
    {
        $empty = [
            'ready' => false,
            'updated' => 0,
            'renamed' => 0,
            'unchanged' => 0,
            'changes' => [],
            'conflicts' => [],
            'skipped' => [],
        ];

        if (! Schema::hasColumn('travel_agencies', 'id_agencia') || ! Schema::hasColumn('travel_agencies', 'id_de_agente')) {
            return $empty;
        }

        $agencies = TravelAgency::query()->orderBy('id')->get(['id', 'name', 'id_agencia', 'id_de_agente']);

        /** @var array<string, list<TravelAgency>> $byName */
        $byName = [];

        /** @var array<int, TravelAgency> $byExternalId */
        $byExternalId = [];

        foreach ($agencies as $agency) {
            $byName[TravelAgencyExternalCatalog::normalizeName((string) $agency->name)][] = $agency;

            if ($agency->id_agencia !== null) {
                $byExternalId[(int) $agency->id_agencia] = $agency;
            }
        }

        $changes = [];
        $conflicts = [];
        $skipped = [];
        $unchanged = 0;
        $usedIds = [];

        foreach (TravelAgencyExternalCatalog::rows() as $row) {
            $owner = $byExternalId[$row['id_agencia']] ?? null;
            $candidates = self::candidates($byName, $row, $usedIds);

            if ($owner instanceof TravelAgency && (int) $owner->id_agencia === $row['id_agencia']) {
                $candidates[$owner->id] = $owner;
            }

            if ($candidates === []) {
                $skipped[] = $row['name'];

                continue;
            }

            $agency = self::resolveCandidate($candidates, $row, $owner);

            if (! $agency instanceof TravelAgency) {
                $conflicts[] = $row['name'].': hay más de una agencia con un nombre equivalente y no se tocó ninguna.';

                continue;
            }

            if (isset($usedIds[$agency->id])) {
                $conflicts[] = $row['name'].': la agencia #'.$agency->id.' ya quedó asignada a otro identificador.';

                continue;
            }

            if ($owner instanceof TravelAgency && $owner->id !== $agency->id) {
                $conflicts[] = $row['name'].': el ID agencia '.$row['id_agencia'].' ya está en «'.$owner->name.'».';

                continue;
            }

            if ($agency->id_agencia !== null && (int) $agency->id_agencia !== $row['id_agencia']) {
                $conflicts[] = $row['name'].': «'.$agency->name.'» ya tiene el ID agencia '.$agency->id_agencia.'.';

                continue;
            }

            $currentAgentId = trim((string) $agency->id_de_agente);

            if ($currentAgentId !== '' && $currentAgentId !== $row['id_de_agente']) {
                $conflicts[] = $row['name'].': «'.$agency->name.'» ya tiene el ID de agente '.$currentAgentId.'.';

                continue;
            }

            $usedIds[$agency->id] = true;
            $newName = self::replacementName($agency, $row);
            $needsAgentId = $currentAgentId === '';
            $needsAgencyId = $agency->id_agencia === null;

            if (! $needsAgencyId && ! $needsAgentId && $newName === null) {
                $unchanged++;

                continue;
            }

            $changes[] = [
                'id' => (int) $agency->id,
                'name' => (string) $agency->name,
                'new_name' => $newName,
                'id_agencia' => $row['id_agencia'],
                'id_de_agente' => $row['id_de_agente'],
            ];
        }

        return [
            'ready' => true,
            'updated' => count($changes),
            'renamed' => count(array_filter($changes, fn (array $change): bool => $change['new_name'] !== null)),
            'unchanged' => $unchanged,
            'changes' => $changes,
            'conflicts' => $conflicts,
            'skipped' => $skipped,
        ];
    }

    /**
     * @param  array<string, list<TravelAgency>>  $byName
     * @param  array{name: string, id_agencia: int, id_de_agente: string, rename: bool, match_names: list<string>}  $row
     * @param  array<int, true>  $usedIds
     * @return array<int, TravelAgency>
     */
    private static function candidates(array $byName, array $row, array $usedIds): array
    {
        $keys = [TravelAgencyExternalCatalog::normalizeName($row['name'])];

        foreach ($row['match_names'] as $alias) {
            $keys[] = TravelAgencyExternalCatalog::normalizeName($alias);
        }

        $candidates = [];

        foreach (array_unique($keys) as $key) {
            foreach ($byName[$key] ?? [] as $agency) {
                if (isset($usedIds[$agency->id])) {
                    continue;
                }

                $candidates[$agency->id] = $agency;
            }
        }

        return $candidates;
    }

    /**
     * @param  array<int, TravelAgency>  $candidates
     * @param  array{name: string, id_agencia: int, id_de_agente: string, rename: bool, match_names: list<string>}  $row
     */
    private static function resolveCandidate(array $candidates, array $row, ?TravelAgency $owner): ?TravelAgency
    {
        if ($owner instanceof TravelAgency && isset($candidates[$owner->id])) {
            return $owner;
        }

        if (count($candidates) === 1) {
            return array_values($candidates)[0];
        }

        $aliasNames = array_map(trim(...), $row['match_names']);
        $exact = array_values(array_filter(
            $candidates,
            fn (TravelAgency $agency): bool => trim((string) $agency->name) === trim($row['name'])
                || in_array(trim((string) $agency->name), $aliasNames, true),
        ));

        if (count($exact) === 1) {
            return $exact[0];
        }

        return null;
    }

    /**
     * @param  array{name: string, id_agencia: int, id_de_agente: string, rename: bool, match_names: list<string>}  $row
     */
    private static function replacementName(TravelAgency $agency, array $row): ?string
    {
        if (! $row['rename'] || $agency->name === $row['name']) {
            return null;
        }

        $current = TravelAgencyExternalCatalog::normalizeName((string) $agency->name);

        foreach ($row['match_names'] as $alias) {
            if ($current === TravelAgencyExternalCatalog::normalizeName($alias)) {
                return $row['name'];
            }
        }

        return null;
    }
}
