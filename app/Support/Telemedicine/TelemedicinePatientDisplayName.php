<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class TelemedicinePatientDisplayName
{
    /**
     * Resultado de Schema::hasTable() por tabla. El esquema no cambia dentro de
     * una misma petición y cada consulta a information_schema es cara.
     *
     * @var array<string, bool>
     */
    private static array $tableExistsCache = [];

    /**
     * Nombre ya resuelto por afiliado, cacheado por petición. La clave incluye
     * las referencias de afiliación y el documento, de modo que si el paciente
     * se reasocia a otra afiliación la clave cambia y no se sirve un valor viejo.
     *
     * @var array<string, string>
     */
    private static array $affiliateNameCache = [];

    /**
     * Vacía las cachés estáticas. Necesario en jobs largos que recorren muchos
     * pacientes dentro de un mismo proceso.
     */
    public static function flushCache(): void
    {
        self::$tableExistsCache = [];
        self::$affiliateNameCache = [];
    }

    public static function fromAffiliate(Affiliate $affiliate): string
    {
        return self::clean($affiliate->full_name);
    }

    public static function fromAffiliateCorporate(AffiliateCorporate $affiliate): string
    {
        $firstName = self::clean($affiliate->first_name);
        $lastName = self::clean($affiliate->last_name);

        if ($lastName === '') {
            return $firstName;
        }

        if ($firstName === '') {
            return $lastName;
        }

        return trim($firstName.' '.$lastName);
    }

    public static function fromPatient(TelemedicinePatient $patient): string
    {
        $resolved = self::resolveFromAffiliate($patient);

        if ($resolved !== '') {
            return $resolved;
        }

        return self::clean($patient->full_name);
    }

    public static function fromPatientOrFallback(?TelemedicinePatient $patient, mixed $fallback = null): string
    {
        if ($patient instanceof TelemedicinePatient) {
            $name = self::fromPatient($patient);
            if ($name !== '') {
                return $name;
            }
        }

        return self::clean($fallback);
    }

    /**
     * @param  array<string, mixed>  $patient
     */
    public static function fromPatientArray(array $patient): string
    {
        $model = new TelemedicinePatient;
        $model->forceFill([
            'id' => $patient['id'] ?? null,
            'full_name' => $patient['full_name'] ?? null,
            'nro_identificacion' => $patient['nro_identificacion'] ?? null,
            'afilliation_id' => $patient['afilliation_id'] ?? null,
            'afilliation_corporate_id' => $patient['afilliation_corporate_id'] ?? null,
            'type_affiliation' => $patient['type_affiliation'] ?? null,
        ]);
        $model->exists = filled($patient['id'] ?? null);

        return self::fromPatient($model);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $clinicalData
     */
    public static function fromContext(array $context, array $clinicalData = []): string
    {
        $patientId = (int) ($clinicalData['telemedicine_patient_id'] ?? $context['telemedicine_patient_id'] ?? 0);

        if ($patientId > 0) {
            try {
                $patient = TelemedicinePatient::query()->find($patientId);
            } catch (Throwable) {
                $patient = null;
            }

            if ($patient instanceof TelemedicinePatient) {
                $name = self::fromPatient($patient);
                if ($name !== '') {
                    return $name;
                }
            }
        }

        return self::clean($clinicalData['full_name'] ?? $context['full_name'] ?? null);
    }

    public static function forCoordination(?OperationCoordinationService $coordination): string
    {
        if (! $coordination instanceof OperationCoordinationService) {
            return '—';
        }

        if ($coordination->exists && ! $coordination->relationLoaded('telemedicinePatient')) {
            try {
                $coordination->loadMissing('telemedicinePatient');
            } catch (Throwable) {
                // Si la relación no puede cargarse, se usa el nombre persistido en la coordinación.
            }
        }

        $patient = $coordination->relationLoaded('telemedicinePatient')
            ? $coordination->telemedicinePatient
            : null;

        $name = self::fromPatientOrFallback($patient, $coordination->patient);

        return $name !== '' ? $name : '—';
    }

    private static function resolveFromAffiliate(TelemedicinePatient $patient): string
    {
        $cacheKey = self::affiliateCacheKey($patient);

        if (array_key_exists($cacheKey, self::$affiliateNameCache)) {
            return self::$affiliateNameCache[$cacheKey];
        }

        return self::$affiliateNameCache[$cacheKey] = self::resolveFromAffiliateUncached($patient);
    }

    private static function affiliateCacheKey(TelemedicinePatient $patient): string
    {
        return implode('|', [
            (string) ($patient->getKey() ?? ''),
            (string) ($patient->afilliation_corporate_id ?? ''),
            (string) ($patient->afilliation_id ?? ''),
            (string) ($patient->type_affiliation ?? ''),
            (string) ($patient->nro_identificacion ?? ''),
        ]);
    }

    private static function resolveFromAffiliateUncached(TelemedicinePatient $patient): string
    {
        if (filled($patient->afilliation_corporate_id)) {
            $corporate = self::findCorporateAffiliate($patient);
            if ($corporate instanceof AffiliateCorporate) {
                return self::fromAffiliateCorporate($corporate);
            }
        }

        if (filled($patient->afilliation_id)) {
            $individual = self::findIndividualAffiliate($patient);
            if ($individual instanceof Affiliate) {
                return self::fromAffiliate($individual);
            }
        }

        $type = mb_strtoupper(trim((string) ($patient->type_affiliation ?? '')));

        if ($type === 'CORPORATIVO') {
            $corporate = self::findCorporateAffiliate($patient);
            if ($corporate instanceof AffiliateCorporate) {
                return self::fromAffiliateCorporate($corporate);
            }
        }

        if ($type === 'INDIVIDUAL') {
            $individual = self::findIndividualAffiliate($patient);
            if ($individual instanceof Affiliate) {
                return self::fromAffiliate($individual);
            }
        }

        return '';
    }

    private static function findIndividualAffiliate(TelemedicinePatient $patient): ?Affiliate
    {
        if (! self::tableExists('affiliates')) {
            return null;
        }

        try {
            $fast = self::exactDocumentLookup(
                Affiliate::query()->select(['id', 'full_name', 'nro_identificacion']),
                'affiliation_id',
                $patient->afilliation_id,
                $patient->nro_identificacion,
            );

            if ($fast !== null) {
                return $fast;
            }
        } catch (Throwable) {
            // Si la vía rápida falla se cae a la búsqueda tolerante de abajo.
        }

        $query = Affiliate::query()->select(['id', 'full_name', 'nro_identificacion']);

        if (! self::constrainAffiliateLookup($query, 'affiliation_id', $patient->afilliation_id, $patient->nro_identificacion)) {
            return null;
        }

        try {
            return self::firstMatchingDocument($query->limit(100)->get(), $patient->nro_identificacion);
        } catch (Throwable) {
            return null;
        }
    }

    private static function findCorporateAffiliate(TelemedicinePatient $patient): ?AffiliateCorporate
    {
        if (! self::tableExists('affiliate_corporates')) {
            return null;
        }

        try {
            $fast = self::exactDocumentLookup(
                AffiliateCorporate::query()->select(['id', 'first_name', 'last_name', 'nro_identificacion']),
                'affiliation_corporate_id',
                $patient->afilliation_corporate_id,
                $patient->nro_identificacion,
            );

            if ($fast !== null) {
                return $fast;
            }
        } catch (Throwable) {
            // Si la vía rápida falla se cae a la búsqueda tolerante de abajo.
        }

        $query = AffiliateCorporate::query()->select(['id', 'first_name', 'last_name', 'nro_identificacion']);

        if (! self::constrainAffiliateLookup($query, 'affiliation_corporate_id', $patient->afilliation_corporate_id, $patient->nro_identificacion)) {
            return null;
        }

        try {
            return self::firstMatchingDocument($query->limit(100)->get(), $patient->nro_identificacion);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Vía rápida: busca el documento por igualdad, que sí usa los índices de
     * `nro_identificacion`. La búsqueda tolerante original lleva un
     * `REPLACE(...)` sobre la columna, que obliga a MySQL a recorrer la tabla
     * entera; se reserva como respaldo para cuando la igualdad no encuentra nada.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function exactDocumentLookup($query, string $affiliationColumn, mixed $affiliationId, mixed $document): mixed
    {
        $raw = trim((string) $document);
        $normalized = TelemedicinePatientIdentity::normalizeDocument(
            is_string($document) || $document === null ? $document : (string) $document
        );

        $values = array_values(array_unique(array_filter([$raw, $normalized], fn (string $v): bool => $v !== '')));

        if ($values === []) {
            return null;
        }

        if (filled($affiliationId)) {
            $query->where($affiliationColumn, $affiliationId);
        }

        $candidates = $query->whereIn('nro_identificacion', $values)->limit(100)->get();

        return self::firstMatchingDocument($candidates, $document);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    private static function constrainAffiliateLookup($query, string $affiliationColumn, mixed $affiliationId, mixed $document): bool
    {
        $raw = trim((string) $document);
        $normalized = TelemedicinePatientIdentity::normalizeDocument(
            is_string($document) || $document === null ? $document : (string) $document
        );

        if (filled($affiliationId)) {
            $query->where($affiliationColumn, $affiliationId);
        }

        if ($raw !== '' || $normalized !== '') {
            $digits = preg_replace('/\D+/', '', $raw !== '' ? $raw : $normalized) ?? '';

            $query->where(function ($inner) use ($raw, $normalized, $digits): void {
                if ($raw !== '') {
                    $inner->where('nro_identificacion', $raw);
                }

                if ($normalized !== '' && $normalized !== $raw) {
                    $inner->orWhere('nro_identificacion', $normalized);
                }

                if ($digits !== '') {
                    $inner->orWhereRaw(
                        "REPLACE(REPLACE(REPLACE(REPLACE(UPPER(COALESCE(nro_identificacion, '')), ' ', ''), '.', ''), '-', ''), 'V', '') = ?",
                        [$digits]
                    );
                }
            });

            return true;
        }

        return filled($affiliationId);
    }

    /**
     * @template TModel of Affiliate|AffiliateCorporate
     *
     * @param  \Illuminate\Support\Collection<int, TModel>  $candidates
     * @return TModel|null
     */
    private static function firstMatchingDocument($candidates, mixed $document): mixed
    {
        foreach ($candidates as $candidate) {
            if (self::documentsLikelyMatch($candidate->nro_identificacion ?? null, $document)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function documentsLikelyMatch(mixed $left, mixed $right): bool
    {
        if (TelemedicinePatientIdentity::documentsMatch(
            is_string($left) || $left === null ? $left : (string) $left,
            is_string($right) || $right === null ? $right : (string) $right,
        )) {
            return true;
        }

        $leftDigits = preg_replace('/\D+/', '', (string) $left) ?? '';
        $rightDigits = preg_replace('/\D+/', '', (string) $right) ?? '';

        return $leftDigits !== '' && $leftDigits === $rightDigits;
    }

    private static function tableExists(string $table): bool
    {
        if (array_key_exists($table, self::$tableExistsCache)) {
            return self::$tableExistsCache[$table];
        }

        try {
            return self::$tableExistsCache[$table] = Schema::hasTable($table);
        } catch (Throwable) {
            return self::$tableExistsCache[$table] = false;
        }
    }

    private static function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }
}
