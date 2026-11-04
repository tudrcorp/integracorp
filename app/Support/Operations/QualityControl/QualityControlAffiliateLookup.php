<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use Illuminate\Database\Eloquent\Model;

/**
 * Busca al afiliado de una encuesta por su cédula: individuales en `affiliates`,
 * corporativos en `affiliate_corporates`.
 *
 * Compara por la cédula normalizada (`identity_document_key`, indexada, la misma del
 * candado de personas duplicadas): «V-14.582.677» y «14582677» son la misma persona.
 * Si la normalización no da clave (cédulas muy cortas), compara el texto tal cual.
 * Con varias coincidencias gana la ACTIVA y, entre iguales, la más reciente.
 */
final class QualityControlAffiliateLookup
{
    private const CHUNK = 500;

    /**
     * @return array{id: int, name: string, phone: string|null, status: string|null, affiliation_code: string|null}|null
     */
    public static function find(QualityControlAffiliateType $type, mixed $document): ?array
    {
        $raw = trim((string) $document);

        if ($raw === '') {
            return null;
        }

        return self::findMany($type, [$raw])[$raw] ?? null;
    }

    /**
     * @param  list<string>  $documents
     * @return array<string, array{id: int, name: string, phone: string|null, status: string|null, affiliation_code: string|null}>
     *                                                                                                                             Coincidencias por el texto de cédula recibido.
     */
    public static function findMany(QualityControlAffiliateType $type, array $documents): array
    {
        $keysByRaw = [];
        $rawWithoutKey = [];

        foreach (array_unique(array_map(fn (string $doc): string => trim($doc), $documents)) as $raw) {
            if ($raw === '') {
                continue;
            }

            $key = QualityControlSurveyKeys::documentKey($raw);

            if ($key !== null) {
                $keysByRaw[$raw] = $key;
            } else {
                $rawWithoutKey[] = $raw;
            }
        }

        $byKey = [];
        foreach (array_chunk(array_values(array_unique($keysByRaw)), self::CHUNK) as $chunk) {
            foreach (self::query($type)->whereIn('identity_document_key', $chunk)->get() as $affiliate) {
                self::keepBest($byKey, (string) $affiliate->getAttribute('identity_document_key'), $affiliate);
            }
        }

        $byRaw = [];
        foreach (array_chunk($rawWithoutKey, self::CHUNK) as $chunk) {
            foreach (self::query($type)->whereIn('nro_identificacion', $chunk)->get() as $affiliate) {
                self::keepBest($byRaw, trim((string) $affiliate->getAttribute('nro_identificacion')), $affiliate);
            }
        }

        $matches = [];
        foreach ($keysByRaw as $raw => $key) {
            if (isset($byKey[$key])) {
                $matches[$raw] = self::present($type, $byKey[$key]);
            }
        }

        foreach ($rawWithoutKey as $raw) {
            if (isset($byRaw[$raw])) {
                $matches[$raw] = self::present($type, $byRaw[$raw]);
            }
        }

        return $matches;
    }

    /**
     * Nombre visible del afiliado enlazado a una encuesta.
     */
    public static function nameOf(Model $affiliate): string
    {
        if ($affiliate instanceof AffiliateCorporate) {
            return trim(preg_replace('/\s+/u', ' ', (string) $affiliate->first_name.' '.(string) $affiliate->last_name) ?? '');
        }

        return trim((string) $affiliate->getAttribute('full_name'));
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Affiliate>|\Illuminate\Database\Eloquent\Builder<AffiliateCorporate>
     */
    private static function query(QualityControlAffiliateType $type): \Illuminate\Database\Eloquent\Builder
    {
        return $type === QualityControlAffiliateType::Individual
            ? Affiliate::query()
                ->select(['id', 'full_name', 'phone', 'status', 'nro_identificacion', 'identity_document_key', 'affiliation_id'])
                ->with('affiliation:id,code')
            : AffiliateCorporate::query()
                ->select(['id', 'first_name', 'last_name', 'phone', 'status', 'nro_identificacion', 'identity_document_key', 'affiliation_corporate_id'])
                ->with('affiliationCorporate:id,code');
    }

    /**
     * @param  array<string, Model>  $bucket
     */
    private static function keepBest(array &$bucket, string $index, Model $candidate): void
    {
        $current = $bucket[$index] ?? null;

        if ($current === null) {
            $bucket[$index] = $candidate;

            return;
        }

        $currentActive = strtoupper((string) $current->getAttribute('status')) === 'ACTIVO';
        $candidateActive = strtoupper((string) $candidate->getAttribute('status')) === 'ACTIVO';

        if ($candidateActive !== $currentActive) {
            if ($candidateActive) {
                $bucket[$index] = $candidate;
            }

            return;
        }

        if ((int) $candidate->getKey() > (int) $current->getKey()) {
            $bucket[$index] = $candidate;
        }
    }

    /**
     * @return array{id: int, name: string, phone: string|null, status: string|null, affiliation_code: string|null}
     */
    private static function present(QualityControlAffiliateType $type, Model $affiliate): array
    {
        $affiliation = $type === QualityControlAffiliateType::Individual
            ? $affiliate->getRelationValue('affiliation')
            : $affiliate->getRelationValue('affiliationCorporate');

        $phone = trim((string) $affiliate->getAttribute('phone'));
        $status = trim((string) $affiliate->getAttribute('status'));

        return [
            'id' => (int) $affiliate->getKey(),
            'name' => self::nameOf($affiliate),
            'phone' => $phone !== '' ? $phone : null,
            'status' => $status !== '' ? $status : null,
            'affiliation_code' => filled($affiliation?->code) ? (string) $affiliation->code : null,
        ];
    }
}
