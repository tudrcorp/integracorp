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
        try {
            return Schema::hasTable($table);
        } catch (Throwable) {
            return false;
        }
    }

    private static function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
    }
}
