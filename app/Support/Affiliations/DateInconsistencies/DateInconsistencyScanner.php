<?php

declare(strict_types=1);

namespace App\Support\Affiliations\DateInconsistencies;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationDateInconsistency as Inconsistency;
use App\Models\AffiliationDateInconsistencyEvent as Event;
use App\Models\User;
use App\Support\Affiliations\DateCorrection\AffiliationDateCorrector;
use App\Support\Affiliations\DateCorrection\DateCorrectionSubject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Mantiene la lista de inconsistencias al día con lo que detecta {@see DateInconsistencyDetector}.
 *
 * Por afiliación y tipo:
 * - Nueva → ABIERTA (evento DETECTADA).
 * - ABIERTA que sigue → se actualizan su detalle y su última detección.
 * - NO_ES_INCONSISTENCIA → sigue cerrada mientras sus datos sean los mismos; si cambiaron, se reabre.
 * - REPARADA o RESUELTA que reaparece → se reabre (evento REABIERTA).
 * - ABIERTA que ya no se detecta → RESUELTA (evento RESUELTA_SOLA).
 *
 * Nada se borra: la historia de cada inconsistencia queda en sus eventos.
 */
final class DateInconsistencyScanner
{
    public const LOCK_KEY = 'affiliation-date-inconsistencies:scan';

    /**
     * Revisa una afiliación y sincroniza sus inconsistencias.
     *
     * @return array{opened: int, updated: int, reopened: int, resolved: int}
     */
    public static function scanAffiliation(Model $affiliation, ?User $actor = null): array
    {
        $subject = DateCorrectionSubject::for($affiliation);
        $counts = ['opened' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0];
        $isActive = strtoupper(trim((string) $affiliation->status)) === AffiliationDateCorrector::ACTIVE_STATUS;
        $findings = $isActive ? DateInconsistencyDetector::inspect($affiliation) : [];
        $byKind = [];

        foreach ($findings as $finding) {
            $byKind[$finding['kind']] = $finding;
        }

        DB::transaction(function () use ($affiliation, $subject, $byKind, $isActive, $actor, &$counts): void {
            $existing = Inconsistency::query()
                ->where('affiliation_type', $subject->type)
                ->where('affiliation_id', $affiliation->getKey())
                ->lockForUpdate()
                ->get()
                ->keyBy('kind');
            $now = now();

            foreach ($byKind as $kind => $finding) {
                $row = $existing->get($kind);
                $values = [
                    'affiliation_code' => (string) $affiliation->code,
                    'severity' => $finding['severity'],
                    'details' => self::details($finding),
                    'fingerprint' => $finding['fingerprint'],
                    'last_detected_at' => $now,
                ];

                if (! $row instanceof Inconsistency) {
                    $row = Inconsistency::query()->create([
                        ...$values,
                        'affiliation_type' => $subject->type,
                        'affiliation_id' => (int) $affiliation->getKey(),
                        'kind' => $kind,
                        'status' => Inconsistency::STATUS_OPEN,
                        'first_detected_at' => $now,
                    ]);
                    self::event($row, Event::DETECTED, null, ['explanation' => $finding['explanation']], $actor);
                    $counts['opened']++;

                    continue;
                }

                if ($row->status === Inconsistency::STATUS_DISMISSED && $row->dismissed_fingerprint === $finding['fingerprint']) {
                    $row->forceFill(['last_detected_at' => $now])->save();

                    continue;
                }

                if ($row->status !== Inconsistency::STATUS_OPEN) {
                    $reason = match ($row->status) {
                        Inconsistency::STATUS_DISMISSED => 'Los datos cambiaron desde que se marcó como no inconsistencia.',
                        Inconsistency::STATUS_REPAIRED => 'Se volvió a detectar después de repararla.',
                        default => 'Se volvió a detectar.',
                    };

                    $row->forceFill([
                        ...$values,
                        'status' => Inconsistency::STATUS_OPEN,
                        'dismissed_fingerprint' => null,
                        'resolved_at' => null,
                        'resolved_by' => null,
                        'resolved_by_name' => null,
                        'resolution_note' => null,
                    ])->save();
                    self::event($row, Event::REOPENED, $reason, ['explanation' => $finding['explanation']], $actor);
                    $counts['reopened']++;

                    continue;
                }

                $row->forceFill($values)->save();
                $counts['updated']++;
            }

            foreach ($existing as $kind => $row) {
                if (isset($byKind[$kind]) || $row->status !== Inconsistency::STATUS_OPEN) {
                    continue;
                }

                $note = $isActive ? 'Ya no se detecta: los datos de la afiliación quedaron coherentes.' : 'La afiliación ya no está activa.';

                $row->forceFill([
                    'status' => Inconsistency::STATUS_RESOLVED,
                    'resolved_at' => $now,
                    'resolution_note' => $note,
                ])->save();
                self::event($row, Event::RESOLVED, $note, null, $actor);
                $counts['resolved']++;
            }
        });

        return $counts;
    }

    /**
     * Revisa todas las afiliaciones activas (y cierra lo abierto de las que dejaron de estarlo).
     *
     * @return array{affiliations: int, opened: int, updated: int, reopened: int, resolved: int, failed: int}
     */
    public static function scanAll(): array
    {
        $lock = Cache::lock(self::LOCK_KEY, 1800);

        if (! $lock->get()) {
            throw new RuntimeException('Ya hay una revisión de inconsistencias en curso.');
        }

        $totals = ['affiliations' => 0, 'opened' => 0, 'updated' => 0, 'reopened' => 0, 'resolved' => 0, 'failed' => 0];

        try {
            foreach ([Affiliation::class, AffiliationCorporate::class] as $class) {
                $class::query()
                    ->where('status', AffiliationDateCorrector::ACTIVE_STATUS)
                    ->chunkById(100, function ($affiliations) use (&$totals): void {
                        foreach ($affiliations as $affiliation) {
                            self::accumulate($totals, $affiliation);
                        }
                    });
            }

            foreach (Inconsistency::query()->where('status', Inconsistency::STATUS_OPEN)->get() as $open) {
                $affiliation = DateCorrectionSubject::forType($open->affiliation_type)->modelClass()::query()->find($open->affiliation_id);

                if (! $affiliation instanceof Model || strtoupper(trim((string) $affiliation->status)) !== AffiliationDateCorrector::ACTIVE_STATUS) {
                    $affiliation instanceof Model
                        ? self::accumulate($totals, $affiliation)
                        : self::closeOrphan($open);
                }
            }
        } finally {
            $lock->release();
        }

        return $totals;
    }

    /**
     * @param  array{affiliations: int, opened: int, updated: int, reopened: int, resolved: int, failed: int}  $totals
     */
    private static function accumulate(array &$totals, Model $affiliation): void
    {
        try {
            $counts = self::scanAffiliation($affiliation);
            $totals['affiliations']++;

            foreach ($counts as $key => $value) {
                $totals[$key] += $value;
            }
        } catch (Throwable $exception) {
            $totals['failed']++;
            Log::warning('Inconsistencias de fechas: no se pudo revisar una afiliación', [
                'code' => $affiliation->code ?? null,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private static function closeOrphan(Inconsistency $open): void
    {
        $note = 'La afiliación ya no existe.';
        $open->forceFill(['status' => Inconsistency::STATUS_RESOLVED, 'resolved_at' => now(), 'resolution_note' => $note])->save();
        self::event($open, Event::RESOLVED, $note, null, null);
    }

    /**
     * @param  array<string, mixed>  $finding
     * @return array<string, mixed>
     */
    private static function details(array $finding): array
    {
        return [
            'title' => $finding['title'],
            'explanation' => $finding['explanation'],
            'consequence' => $finding['consequence'],
            'repair' => $finding['repair'],
            'repair_mode' => $finding['repair_mode'],
            'facts' => $finding['facts'],
            'suggestions' => $finding['suggestions'],
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function event(Inconsistency $inconsistency, string $action, ?string $note, ?array $payload, ?User $actor): void
    {
        Event::query()->create([
            'affiliation_date_inconsistency_id' => $inconsistency->getKey(),
            'action' => $action,
            'note' => $note,
            'payload' => $payload,
            'user_id' => $actor?->getKey(),
            'user_name' => $actor?->name ?? 'SISTEMA',
        ]);
    }
}
