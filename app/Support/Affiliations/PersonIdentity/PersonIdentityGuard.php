<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentityFindingKind;
use App\Enums\PersonIdentitySource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CompanyAssociate;
use App\Models\PersonIdentityFinding;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Candado: una persona no puede quedar vigente dos veces entre afiliados individuales, afiliados
 * corporativos y asociados de Nuevos Negocios, ni guardarse con el nombre cargado dos veces.
 *
 * Lo invoca el observer de los tres modelos al crear, y al cambiar nombre, cédula, nacimiento,
 * estatus o afiliación; las inserciones por lote llaman a {@see self::assertCorporateRowsAreUnique()}.
 * Si encuentra un choque lanza {@see DuplicatePersonIdentityException} y no se guarda nada (los
 * paneles corren cada formulario y acción en una transacción).
 *
 * Un par que el analista marcó como «no es la misma persona» en el detector no bloquea, mientras
 * los datos de ambos sigan siendo los que revisó.
 */
final class PersonIdentityGuard
{
    public const CANDIDATE_LIMIT = 50;

    private static bool $repeatedNameAllowed = false;

    /**
     * Ejecuta `$callback` aceptando un nombre cargado dos veces. Solo para revertir una corrección
     * del detector: deshacer debe devolver el dato tal como estaba. La regla de persona duplicada
     * sigue activa.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function allowingRepeatedName(callable $callback): mixed
    {
        $previous = self::$repeatedNameAllowed;
        self::$repeatedNameAllowed = true;

        try {
            return $callback();
        } finally {
            self::$repeatedNameAllowed = $previous;
        }
    }

    /**
     * @param  Model|null  $subject  el modelo con todas sus columnas de identidad (ver {@see self::completeSubject()})
     */
    public static function assertModelIsValid(Model $model, ?Model $subject = null): void
    {
        if (! PersonIdentityIndex::isAvailable($model)) {
            return;
        }

        $source = PersonIdentitySource::fromModel($model);

        if ($model->exists && ! $model->isDirty($source->watchedColumns())) {
            return;
        }

        $subject ??= self::completeSubject($model);
        $record = PersonIdentityRecord::fromModel($subject);

        if (! $record->isActive()) {
            return;
        }

        if (! self::$repeatedNameAllowed && self::nameChanged($model, $source) && self::hasRepeatedName($subject)) {
            throw DuplicatePersonIdentityException::repeatedName($record);
        }

        $conflicts = self::conflictsFor($record);

        if ($conflicts !== []) {
            throw new DuplicatePersonIdentityException($conflicts);
        }
    }

    /**
     * Para el `insert()` por lotes de la creación corporativa: revisa cada fila contra la base y
     * contra las demás filas del mismo lote, y devuelve las filas con sus claves de identidad.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function assertCorporateRowsAreUnique(array $rows, AffiliationCorporate $parent): array
    {
        if (! PersonIdentityIndex::isAvailable()) {
            return $rows;
        }

        $conflicts = [];
        $seen = [];
        $prepared = [];

        foreach ($rows as $row) {
            $probe = new AffiliateCorporate;
            $probe->forceFill([
                'first_name' => $row['first_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'nro_identificacion' => $row['nro_identificacion'] ?? null,
                'birth_date' => $row['birth_date'] ?? null,
                'status' => $row['status'] ?? null,
                'affiliation_corporate_id' => $parent->getKey(),
            ]);

            $record = PersonIdentityRecord::fromModel($probe, $parent);
            $prepared[] = PersonIdentityIndex::withCorporateKeys($row);

            if (! $record->isActive()) {
                continue;
            }

            if (self::hasRepeatedName($probe)) {
                throw DuplicatePersonIdentityException::repeatedName($record);
            }

            foreach ($seen as $previous) {
                $kind = PersonIdentityMatcher::match($record, $previous);

                if ($kind !== null) {
                    $conflicts[] = ['candidate' => $record, 'existing' => $previous, 'kind' => $kind];
                }
            }

            foreach (self::conflictsFor($record) as $conflict) {
                $conflicts[] = $conflict;
            }

            $seen[] = $record;
        }

        if ($conflicts !== []) {
            throw new DuplicatePersonIdentityException($conflicts);
        }

        return $prepared;
    }

    /**
     * Registros vigentes que son la misma persona que `$candidate`.
     *
     * @return list<array{candidate: PersonIdentityRecord, existing: PersonIdentityRecord, kind: PersonIdentityFindingKind}>
     */
    public static function conflictsFor(PersonIdentityRecord $candidate): array
    {
        $hasNameAndBirth = $candidate->nameKey !== null && $candidate->birthDateKey !== null;

        if ($candidate->nameKey === null || ($candidate->documentKey === null && ! $hasNameAndBirth)) {
            return [];
        }

        $conflicts = [];

        foreach (PersonIdentitySource::cases() as $source) {
            foreach (self::candidates($source, $candidate) as $existing) {
                if ($existing->sameRecordAs($candidate) || ! $existing->isActive() || ! $existing->overlapsInTime($candidate)) {
                    continue;
                }

                $kind = PersonIdentityMatcher::match($candidate, $existing);

                if ($kind === null || self::wasDismissed($kind, $candidate, $existing)) {
                    continue;
                }

                $conflicts[] = ['candidate' => $candidate, 'existing' => $existing, 'kind' => $kind];
            }
        }

        return $conflicts;
    }

    /**
     * Un modelo guardado desde una consulta con pocas columnas (`get(['id', 'status'])`) no trae el
     * nombre ni la cédula: se completan con lo que hay en la base y encima lo que cambia ahora. Sin
     * esto, guardarlo borraría sus claves de identidad y el candado no vería a esa persona.
     */
    public static function completeSubject(Model $model): Model
    {
        $source = PersonIdentitySource::fromModel($model);
        $missing = array_diff($source->watchedColumns(), array_keys($model->getAttributes()));

        if (! $model->exists || $missing === []) {
            return $model;
        }

        $stored = $model->newQueryWithoutScopes()->whereKey($model->getKey())->first();

        if ($stored === null) {
            return $model;
        }

        return $stored->forceFill($model->getDirty());
    }

    public static function hasRepeatedName(Model $model): bool
    {
        return $model instanceof AffiliateCorporate
            ? PersonIdentityNormalizer::isRepeatedName((string) $model->first_name, (string) $model->last_name)
            : PersonIdentityNormalizer::isRepeatedName((string) $model->getAttribute('full_name'));
    }

    /**
     * @return list<PersonIdentityRecord>
     */
    private static function candidates(PersonIdentitySource $source, PersonIdentityRecord $candidate): array
    {
        $modelClass = $source->modelClass();

        /** @var Builder<Model> $query */
        $query = $modelClass::query()
            ->where(function (Builder $query) use ($candidate): void {
                if ($candidate->documentKey !== null) {
                    $query->orWhere(PersonIdentityIndex::DOCUMENT_COLUMN, $candidate->documentKey);
                }

                if ($candidate->nameKey !== null && $candidate->birthDateKey !== null) {
                    $query->orWhere(function (Builder $query) use ($candidate): void {
                        $query->where(PersonIdentityIndex::BIRTH_COLUMN, $candidate->birthDateKey)
                            ->where(PersonIdentityIndex::NAME_COLUMN, $candidate->nameKey);
                    });
                }
            })
            ->where(function (Builder $query): void {
                $query->whereNull('status')->orWhereNotIn('status', PersonIdentityRecord::INACTIVE_PERSON_STATUSES);
            })
            ->limit(self::CANDIDATE_LIMIT);

        if ($candidate->source === $source && $candidate->id !== null) {
            $query->whereKeyNot($candidate->id);
        }

        if (DB::transactionLevel() > 0) {
            $query->lockForUpdate();
        }

        $relation = match ($source) {
            PersonIdentitySource::Individual => 'affiliation:id,code,status,full_name_payer,full_name_ti',
            PersonIdentitySource::Corporate => 'affiliationCorporate:id,code,status,name_corporate',
            PersonIdentitySource::Associate => 'company:id,name',
        };

        return $query->with($relation)->get()
            ->map(static fn (Model $model): PersonIdentityRecord => PersonIdentityRecord::fromModel($model, match (true) {
                $model instanceof Affiliate => $model->affiliation,
                $model instanceof AffiliateCorporate => $model->affiliationCorporate,
                $model instanceof CompanyAssociate => $model->company,
                default => null,
            }))
            ->values()
            ->all();
    }

    private static function wasDismissed(PersonIdentityFindingKind $kind, PersonIdentityRecord $a, PersonIdentityRecord $b): bool
    {
        if ($a->id === null || $b->id === null) {
            return false;
        }

        $finding = PersonIdentityFinding::query()
            ->where('pair_key', PersonIdentityFinding::pairKey($kind, $a, $b))
            ->where('status', PersonIdentityFinding::STATUS_DISMISSED)
            ->first(['id', 'dismissed_fingerprint']);

        return $finding !== null && $finding->dismissed_fingerprint === PersonIdentityFinding::fingerprintFor($kind, $a, $b);
    }

    private static function nameChanged(Model $model, PersonIdentitySource $source): bool
    {
        if (! $model->exists) {
            return true;
        }

        return $model->isDirty($source === PersonIdentitySource::Corporate ? ['first_name', 'last_name'] : ['full_name']);
    }
}
