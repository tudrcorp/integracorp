<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentityFindingKind;
use App\Enums\PersonIdentitySource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\CompanyAssociate;
use App\Models\PersonIdentityFinding as Finding;
use App\Models\PersonIdentityFindingEvent as Event;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Detector de personas duplicadas: recorre las tres poblaciones vigentes y mantiene la lista de
 * casos al día.
 *
 * Por caso:
 * - Nuevo → ABIERTO (evento DETECTADO).
 * - ABIERTO que sigue → se actualizan su foto y su última detección.
 * - NO_ES_DUPLICADO → sigue cerrado mientras los datos sean los que se revisaron; si cambiaron, se reabre.
 * - CORREGIDO o RESUELTO que reaparece → se reabre.
 * - ABIERTO que ya no se detecta (alguien lo corrigió fuera de aquí) → RESUELTO.
 *
 * Solo lee las poblaciones (salvo recalcular sus claves de identidad); nada se borra.
 */
final class PersonIdentityScanner
{
    public const LOCK_KEY = 'person-identity-findings:scan';

    /**
     * @return array{people: int, opened: int, updated: int, reopened: int, resolved: int, keys_updated: int}
     */
    public static function scanAll(?User $actor = null): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 1800);

        if (! $lock->get()) {
            throw new RuntimeException('Ya hay una revisión de personas duplicadas en curso.');
        }

        try {
            $keysUpdated = array_sum(PersonIdentityIndex::rebuildAll());
            $records = self::activeRecords();
            $findings = self::detect($records);
            $counts = self::sync($findings, $actor);

            return ['people' => count($records), ...$counts, 'keys_updated' => $keysUpdated];
        } finally {
            $lock->release();
        }
    }

    /**
     * Casos que se ven hoy en un conjunto de registros vigentes.
     *
     * @param  list<PersonIdentityRecord>  $records
     * @return array<string, array{kind: PersonIdentityFindingKind, a: PersonIdentityRecord, b: ?PersonIdentityRecord}>
     */
    public static function detect(array $records): array
    {
        $findings = [];
        $groups = [];

        foreach ($records as $index => $record) {
            if ($record->documentKey !== null) {
                $groups['d:'.$record->documentKey][] = $index;
            }

            if ($record->nameKey !== null && $record->birthDateKey !== null) {
                $groups['n:'.$record->birthDateKey.'|'.$record->nameKey][] = $index;
            }

            if ($record->repeatedName) {
                $findings[Finding::pairKey(PersonIdentityFindingKind::RepeatedName, $record)] = [
                    'kind' => PersonIdentityFindingKind::RepeatedName,
                    'a' => $record,
                    'b' => null,
                ];
            }
        }

        foreach ($groups as $members) {
            $total = count($members);

            for ($i = 0; $i < $total; $i++) {
                for ($j = $i + 1; $j < $total; $j++) {
                    [$a, $b] = self::ordered($records[$members[$i]], $records[$members[$j]]);

                    if (! $a->overlapsInTime($b)) {
                        continue;
                    }

                    $kind = PersonIdentityMatcher::match($a, $b);

                    if ($kind === null) {
                        continue;
                    }

                    $findings[Finding::pairKey($kind, $a, $b)] = ['kind' => $kind, 'a' => $a, 'b' => $b];
                }
            }
        }

        return $findings;
    }

    /**
     * Todas las personas vigentes de las tres poblaciones, con su afiliación o empresa precargada.
     *
     * @return list<PersonIdentityRecord>
     */
    public static function activeRecords(): array
    {
        $records = [];
        $notInactive = static function ($query): void {
            $query->whereNull('status')->orWhereNotIn('status', PersonIdentityRecord::INACTIVE_PERSON_STATUSES);
        };

        Affiliate::query()
            ->where($notInactive)
            ->with('affiliation:id,code,status,full_name_payer,full_name_ti')
            ->chunkById(1000, function ($affiliates) use (&$records): void {
                foreach ($affiliates as $affiliate) {
                    self::push($records, PersonIdentityRecord::fromModel($affiliate, $affiliate->affiliation));
                }
            });

        AffiliateCorporate::query()
            ->where($notInactive)
            ->with('affiliationCorporate:id,code,status,name_corporate')
            ->chunkById(1000, function ($affiliates) use (&$records): void {
                foreach ($affiliates as $affiliate) {
                    self::push($records, PersonIdentityRecord::fromModel($affiliate, $affiliate->affiliationCorporate));
                }
            });

        CompanyAssociate::query()
            ->where($notInactive)
            ->with('company:id,name')
            ->chunkById(1000, function ($associates) use (&$records): void {
                foreach ($associates as $associate) {
                    self::push($records, PersonIdentityRecord::fromModel($associate, $associate->company));
                }
            });

        return $records;
    }

    /**
     * Foto actual de un registro, o null si ya no existe.
     */
    public static function currentRecord(?PersonIdentitySource $source, ?int $id): ?PersonIdentityRecord
    {
        if ($source === null || $id === null) {
            return null;
        }

        $model = $source->modelClass()::query()->find($id);

        return $model !== null ? PersonIdentityRecord::fromModel($model) : null;
    }

    /**
     * Vuelve a revisar solo los registros de un caso (después de corregir o dar de baja).
     */
    public static function rescanFinding(Finding $finding, ?User $actor = null): void
    {
        $a = self::currentRecord($finding->sourceA(), (int) $finding->record_a_id);
        $b = self::currentRecord($finding->sourceB(), $finding->record_b_id !== null ? (int) $finding->record_b_id : null);
        $records = array_values(array_filter([$a, $b], static fn (?PersonIdentityRecord $record): bool => $record?->isActive() ?? false));
        $kind = $finding->kindEnum();
        $detected = array_filter(
            self::detect($records),
            static fn (array $found): bool => $found['kind'] === $kind,
        );

        self::sync($detected, $actor, [$finding->pair_key]);
    }

    /**
     * @param  array<string, array{kind: PersonIdentityFindingKind, a: PersonIdentityRecord, b: ?PersonIdentityRecord}>  $findings
     * @param  list<string>|null  $onlyPairKeys  limita el cierre de casos no detectados a estos (revisión parcial)
     * @return array{opened: int, updated: int, reopened: int, resolved: int}
     */
    public static function sync(array $findings, ?User $actor = null, ?array $onlyPairKeys = null): array
    {
        $counts = ['opened' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0];
        $now = now();

        DB::transaction(function () use ($findings, $actor, $onlyPairKeys, $now, &$counts): void {
            $existing = Finding::query()
                ->when($onlyPairKeys !== null, fn ($query) => $query->whereIn('pair_key', [...$onlyPairKeys, ...array_keys($findings)]))
                ->lockForUpdate()
                ->get()
                ->keyBy('pair_key');

            foreach ($findings as $pairKey => $found) {
                $fingerprint = Finding::fingerprintFor($found['kind'], $found['a'], $found['b']);
                $values = [
                    'severity' => $found['kind']->severity(),
                    'person_name' => mb_substr($found['a']->name, 0, 255),
                    'details' => self::details($found),
                    'fingerprint' => $fingerprint,
                    'last_detected_at' => $now,
                ];

                $row = $existing->get($pairKey);

                if (! $row instanceof Finding) {
                    $row = Finding::query()->create([
                        ...$values,
                        'kind' => $found['kind']->value,
                        'status' => Finding::STATUS_OPEN,
                        'pair_key' => $pairKey,
                        'record_a_type' => $found['a']->source->value,
                        'record_a_id' => (int) $found['a']->id,
                        'record_b_type' => $found['b']?->source->value,
                        'record_b_id' => $found['b']?->id,
                        'first_detected_at' => $now,
                    ]);
                    self::event($row, Event::DETECTED, null, ['where_a' => $found['a']->whereDescription(), 'where_b' => $found['b']?->whereDescription()], $actor);
                    $counts['opened']++;

                    continue;
                }

                if ($row->status === Finding::STATUS_DISMISSED && $row->dismissed_fingerprint === $fingerprint) {
                    $row->forceFill(['last_detected_at' => $now])->save();

                    continue;
                }

                if ($row->status !== Finding::STATUS_OPEN) {
                    $reason = match ($row->status) {
                        Finding::STATUS_DISMISSED => 'Los datos cambiaron desde que se marcó como no duplicado.',
                        Finding::STATUS_CORRECTED => 'Se volvió a detectar después de corregirlo.',
                        default => 'Se volvió a detectar.',
                    };

                    $row->forceFill([
                        ...$values,
                        'status' => Finding::STATUS_OPEN,
                        'dismissed_fingerprint' => null,
                        'resolved_at' => null,
                        'resolved_by' => null,
                        'resolved_by_name' => null,
                        'resolution_note' => null,
                    ])->save();
                    self::event($row, Event::REOPENED, $reason, null, $actor);
                    $counts['reopened']++;

                    continue;
                }

                $row->forceFill($values)->save();
                $counts['updated']++;
            }

            foreach ($existing as $pairKey => $row) {
                if (array_key_exists($pairKey, $findings) || $row->status !== Finding::STATUS_OPEN) {
                    continue;
                }

                $row->forceFill(['status' => Finding::STATUS_RESOLVED, 'resolved_at' => $now])->save();
                self::event($row, Event::RESOLVED, 'Ya no se detecta: los datos se corrigieron o uno de los registros dejó de estar vigente.', null, $actor);
                $counts['resolved']++;
            }
        });

        return $counts;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function event(Finding $finding, string $action, ?string $note, ?array $payload, ?User $actor): Event
    {
        return Event::query()->create([
            'person_identity_finding_id' => $finding->getKey(),
            'action' => $action,
            'note' => $note,
            'payload' => $payload,
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name,
        ]);
    }

    /**
     * @param  list<PersonIdentityRecord>  $records
     */
    private static function push(array &$records, PersonIdentityRecord $record): void
    {
        if ($record->isActive()) {
            $records[] = $record;
        }
    }

    /**
     * Orden fijo dentro del par (por población y luego por id), para que A|B y B|A sean el mismo caso.
     *
     * @return array{0: PersonIdentityRecord, 1: PersonIdentityRecord}
     */
    private static function ordered(PersonIdentityRecord $x, PersonIdentityRecord $y): array
    {
        return strcmp($x->source->value.str_pad((string) $x->id, 12, '0', STR_PAD_LEFT), $y->source->value.str_pad((string) $y->id, 12, '0', STR_PAD_LEFT)) <= 0
            ? [$x, $y]
            : [$y, $x];
    }

    /**
     * @param  array{kind: PersonIdentityFindingKind, a: PersonIdentityRecord, b: ?PersonIdentityRecord}  $found
     * @return array<string, mixed>
     */
    private static function details(array $found): array
    {
        return [
            'explanation' => $found['kind']->explanation(),
            'consequence' => $found['kind']->consequence(),
            'repair' => $found['kind']->repair(),
            'reason' => PersonIdentityMatcher::reason($found['kind']),
            'a' => $found['a']->toArray(),
            'b' => $found['b']?->toArray(),
        ];
    }
}
