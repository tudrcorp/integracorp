<?php

declare(strict_types=1);

namespace App\Support\WhiteCompanies;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\User;
use App\Models\WhiteCompany;
use Illuminate\Container\Container;

/**
 * Resuelve a qué empresa aliada (marca blanca) pertenece un código comercial.
 *
 * La pertenencia no es plana: la empresa aliada se declara una sola vez sobre
 * la agencia MASTER (`users.white_company_id`) y toda su red hereda esa
 * propiedad subiendo por la jerarquía comercial (`agencies.owner_code`,
 * `agents.owner_code`). Una afiliación emitida por una agencia GENERAL que
 * cuelga de la MASTER aliada es, a todos los efectos, una venta de la aliada:
 * sus documentos, tarifas negociadas, reportes y conciliación de crédito deben
 * resolverse contra la empresa de la raíz.
 *
 * Los mapas se cargan de una sola consulta por proceso con TTL corto: el
 * resaltado de filas de las tablas de afiliaciones lo consulta fila por fila.
 */
final class WhiteCompanyOwnership
{
    private const MAX_DEPTH = 20;

    private const CACHE_TTL_SECONDS = 60;

    /** @var array<string, int> */
    private static array $directOwners = [];

    /** @var array<string, string> */
    private static array $parents = [];

    /** @var array<string, string> */
    private static array $canonicalCodes = [];

    /** @var array<int, bool> */
    private static array $existingCompanyIds = [];

    private static ?float $loadedAt = null;

    /** @var array<int, WhiteCompany|null> */
    private static array $companies = [];

    public static function flush(): void
    {
        self::$directOwners = [];
        self::$parents = [];
        self::$canonicalCodes = [];
        self::$existingCompanyIds = [];
        self::$companies = [];
        self::$loadedAt = null;
    }

    /**
     * Empresa aliada dueña del código, subiendo por la jerarquía comercial.
     */
    public static function companyIdForAgencyCode(?string $code): ?int
    {
        $normalized = self::normalize($code);

        if ($normalized === null) {
            return null;
        }

        self::ensureLoaded();

        $visited = [];
        $current = $normalized;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if (isset($visited[$current])) {
                return null;
            }

            $visited[$current] = true;

            if (isset(self::$directOwners[$current])) {
                return self::$directOwners[$current];
            }

            $parent = self::$parents[$current] ?? null;

            if ($parent === null) {
                return null;
            }

            $current = $parent;
        }

        return null;
    }

    public static function forAgencyCode(?string $code): ?WhiteCompany
    {
        return self::company(self::companyIdForAgencyCode($code));
    }

    /**
     * Empresa aliada de una afiliación: vínculo congelado en el registro,
     * usuario de la agencia emisora y, por último, jerarquía comercial.
     */
    public static function companyIdForAffiliation(Affiliation|AffiliationCorporate $record): ?int
    {
        $direct = self::normalizeCompanyId($record->getAttribute('white_company_id'));

        if ($direct !== null && self::companyExists($direct)) {
            return $direct;
        }

        if ($record instanceof Affiliation && $record->relationLoaded('whiteCompanyUser')) {
            $fromUser = self::normalizeCompanyId($record->getRelation('whiteCompanyUser')?->white_company_id);

            if ($fromUser !== null && self::companyExists($fromUser)) {
                return $fromUser;
            }
        }

        $code = $record->getAttribute('code_agency');

        return self::companyIdForAgencyCode(is_string($code) ? $code : null);
    }

    public static function forAffiliation(Affiliation|AffiliationCorporate $record): ?WhiteCompany
    {
        return self::company(self::companyIdForAffiliation($record));
    }

    /**
     * Si la afiliación está vinculada a una empresa aliada, aunque el registro
     * al que apunta ya no exista. Se usa para marcar filas y para decidir el
     * envío del webhook, donde el vínculo importa más que la ficha.
     */
    public static function isAllied(Affiliation|AffiliationCorporate $record): bool
    {
        if (filled($record->getAttribute('white_company_id'))) {
            return true;
        }

        if ($record instanceof Affiliation
            && $record->relationLoaded('whiteCompanyUser')
            && filled($record->getRelation('whiteCompanyUser')?->white_company_id)
        ) {
            return true;
        }

        $code = $record->getAttribute('code_agency');

        return self::companyIdForAgencyCode(is_string($code) ? $code : null) !== null;
    }

    /**
     * Códigos comerciales de la empresa aliada: la raíz declarada y toda su
     * descendencia por `owner_code`.
     *
     * @return list<string>
     */
    public static function agencyCodesFor(WhiteCompany|int|null $company): array
    {
        $companyId = $company instanceof WhiteCompany
            ? self::normalizeCompanyId($company->getKey())
            : self::normalizeCompanyId($company);

        if ($companyId === null) {
            return [];
        }

        self::ensureLoaded();

        $pending = [];

        foreach (self::$directOwners as $code => $owner) {
            if ($owner === $companyId) {
                $pending[] = $code;
            }
        }

        $children = self::childrenMap();
        $collected = [];

        while ($pending !== []) {
            $code = array_shift($pending);

            if (isset($collected[$code])) {
                continue;
            }

            $collected[$code] = true;

            foreach ($children[$code] ?? [] as $child) {
                if (! isset($collected[$child])) {
                    $pending[] = $child;
                }
            }
        }

        $codes = [];

        foreach (array_keys($collected) as $code) {
            $codes[] = self::$canonicalCodes[$code] ?? $code;
        }

        sort($codes);

        return $codes;
    }

    private static function company(?int $companyId): ?WhiteCompany
    {
        if ($companyId === null) {
            return null;
        }

        if (array_key_exists($companyId, self::$companies)) {
            return self::$companies[$companyId];
        }

        if (! self::databaseAvailable()) {
            return null;
        }

        return self::$companies[$companyId] = WhiteCompany::query()->find($companyId);
    }

    private static function companyExists(int $companyId): bool
    {
        self::ensureLoaded();

        return isset(self::$existingCompanyIds[$companyId]);
    }

    /**
     * @return array<string, list<string>>
     */
    private static function childrenMap(): array
    {
        $children = [];

        foreach (self::$parents as $code => $parent) {
            $children[$parent][] = $code;
        }

        return $children;
    }

    private static function ensureLoaded(): void
    {
        if (self::$loadedAt !== null && (microtime(true) - self::$loadedAt) < self::CACHE_TTL_SECONDS) {
            return;
        }

        self::$directOwners = [];
        self::$parents = [];
        self::$canonicalCodes = [];
        self::$existingCompanyIds = [];
        self::$companies = [];
        self::$loadedAt = microtime(true);

        if (! self::databaseAvailable()) {
            return;
        }

        foreach (WhiteCompany::query()->pluck('id') as $id) {
            $companyId = self::normalizeCompanyId($id);

            if ($companyId !== null) {
                self::$existingCompanyIds[$companyId] = true;
            }
        }

        foreach (User::query()
            ->whereNotNull('white_company_id')
            ->whereNotNull('code_agency')
            ->orderBy('id')
            ->get(['code_agency', 'white_company_id']) as $user) {
            self::rememberOwner($user->code_agency, $user->white_company_id);
        }

        foreach (Agency::query()
            ->orderBy('id')
            ->get(['code', 'owner_code', 'white_company_id']) as $agency) {
            self::rememberOwner($agency->code, $agency->white_company_id);
            self::rememberParent($agency->code, $agency->owner_code);
        }

        foreach (Agent::query()
            ->whereNotNull('code_agent')
            ->orderBy('id')
            ->get(['code_agent', 'code_agency', 'owner_code', 'white_company_id']) as $agent) {
            self::rememberOwner($agent->code_agent, $agent->white_company_id);
            self::rememberParent($agent->code_agent, $agent->owner_code ?: $agent->code_agency);
        }
    }

    private static function rememberOwner(mixed $code, mixed $companyId): void
    {
        $normalized = self::normalize(is_string($code) ? $code : null);
        $company = self::normalizeCompanyId($companyId);

        if ($normalized === null || $company === null || ! isset(self::$existingCompanyIds[$company])) {
            return;
        }

        self::$directOwners[$normalized] ??= $company;
    }

    private static function rememberParent(mixed $code, mixed $parentCode): void
    {
        $normalized = self::normalize(is_string($code) ? $code : null);
        $parent = self::normalize(is_string($parentCode) ? $parentCode : null);

        if ($normalized === null || $parent === null || $normalized === $parent) {
            return;
        }

        self::$parents[$normalized] ??= $parent;
    }

    private static function normalize(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $trimmed = trim($code);

        if ($trimmed === '') {
            return null;
        }

        $normalized = mb_strtoupper($trimmed);
        self::$canonicalCodes[$normalized] ??= $trimmed;

        return $normalized;
    }

    private static function normalizeCompanyId(mixed $value): ?int
    {
        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $id = (int) $value;

        return $id > 0 ? $id : null;
    }

    /**
     * Los tests unitarios de este repositorio corren sin contenedor: sin
     * conexión disponible se resuelve solo con lo que trae el registro.
     */
    private static function databaseAvailable(): bool
    {
        return Container::getInstance()->bound('db');
    }
}
