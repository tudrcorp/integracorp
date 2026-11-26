<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Filament\Administration\Resources\AffiliationCorporates\AffiliationCorporateResource as AdministrationAffiliationCorporateResource;
use App\Filament\Administration\Resources\Affiliations\AffiliationResource as AdministrationAffiliationResource;
use App\Filament\Administration\Resources\Agents\AgentResource as AdministrationAgentResource;
use App\Filament\Business\Resources\AffiliationCorporates\AffiliationCorporateResource as BusinessAffiliationCorporateResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource as BusinessAffiliationResource;
use App\Filament\Business\Resources\Agents\AgentResource as BusinessAgentResource;
use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource as OperationsAffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource as OperationsAffiliateResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\Agent;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Busca una ficha ya existente por el teléfono, con coincidencia exacta.
 * Una sola consulta al abrir el caso. No recorre la tabla con comodines.
 */
final class CrmInboxDirectory
{
    /**
     * @return list<string>
     */
    public static function variants(string $phone): array
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return [];
        }

        $variants = [$digits, '+'.$digits];

        if (str_starts_with($digits, '58') && strlen($digits) > 2) {
            $national = substr($digits, 2);
            $variants[] = $national;
            $variants[] = '0'.$national;
        }

        if (str_starts_with($digits, '0') && strlen($digits) > 1) {
            $variants[] = '58'.substr($digits, 1);
        }

        return array_values(array_unique($variants));
    }

    /**
     * @return array{kind: 'afiliado'|'afiliado_corporativo'|'agente', id: int, affiliation_id: ?int, name: string}|null
     */
    public static function match(string $phone): ?array
    {
        $variants = self::variants($phone);

        if ($variants === []) {
            return null;
        }

        if (Schema::hasTable('affiliates')) {
            $affiliate = Affiliate::query()
                ->whereIn('phone', $variants)
                ->orderByDesc('id')
                ->first(['id', 'affiliation_id', 'full_name']);

            if ($affiliate !== null) {
                $name = trim((string) $affiliate->full_name);

                return [
                    'kind' => 'afiliado',
                    'id' => (int) $affiliate->id,
                    'affiliation_id' => $affiliate->affiliation_id !== null ? (int) $affiliate->affiliation_id : null,
                    'name' => $name !== '' ? $name : 'Afiliado',
                ];
            }
        }

        $corporate = self::corporate('phone', $variants);

        if ($corporate !== null) {
            return $corporate;
        }

        if (Schema::hasTable('agents')) {
            $agent = Agent::query()
                ->whereIn('phone', $variants)
                ->orderByDesc('id')
                ->first(['id', 'name']);

            if ($agent !== null) {
                $name = trim((string) $agent->name);

                return [
                    'kind' => 'agente',
                    'id' => (int) $agent->id,
                    'affiliation_id' => null,
                    'name' => $name !== '' ? $name : 'Agente',
                ];
            }
        }

        return null;
    }

    /**
     * Formas en que una cédula puede estar guardada: solo dígitos, o con la letra delante.
     *
     * @return list<string>
     */
    public static function documentVariants(string $document): array
    {
        $digits = preg_replace('/\D+/', '', $document) ?? '';

        if (strlen($digits) < 6 || strlen($digits) > 9) {
            return [];
        }

        $variants = [$digits];

        foreach (['V', 'E'] as $letter) {
            $variants[] = $letter.$digits;
            $variants[] = $letter.'-'.$digits;
        }

        return $variants;
    }

    /**
     * Busca por la cédula que el cliente escribió en el chat. Coincidencia exacta sobre columnas con índice.
     *
     * @return array{kind: 'afiliado'|'afiliado_corporativo'|'agente', id: int, affiliation_id: ?int, name: string}|null
     */
    public static function matchDocument(string $document): ?array
    {
        $variants = self::documentVariants($document);

        if ($variants === []) {
            return null;
        }

        if (Schema::hasColumn('affiliates', 'nro_identificacion')) {
            $affiliate = Affiliate::query()
                ->whereIn('nro_identificacion', $variants)
                ->orderByDesc('id')
                ->first(['id', 'affiliation_id', 'full_name']);

            if ($affiliate !== null) {
                $name = trim((string) $affiliate->full_name);

                return [
                    'kind' => 'afiliado',
                    'id' => (int) $affiliate->id,
                    'affiliation_id' => $affiliate->affiliation_id !== null ? (int) $affiliate->affiliation_id : null,
                    'name' => $name !== '' ? $name : 'Afiliado',
                ];
            }
        }

        $corporate = self::corporate('nro_identificacion', $variants);

        if ($corporate !== null) {
            return $corporate;
        }

        if (Schema::hasColumn('agents', 'ci')) {
            $agent = Agent::query()
                ->whereIn('ci', $variants)
                ->orderByDesc('id')
                ->first(['id', 'name']);

            if ($agent !== null) {
                $name = trim((string) $agent->name);

                return [
                    'kind' => 'agente',
                    'id' => (int) $agent->id,
                    'affiliation_id' => null,
                    'name' => $name !== '' ? $name : 'Agente',
                ];
            }
        }

        return null;
    }

    /**
     * Afiliado de una afiliación corporativa (no excluido) por teléfono o cédula.
     *
     * @param  list<string>  $variants
     * @return array{kind: 'afiliado_corporativo', id: int, affiliation_id: ?int, name: string}|null
     */
    private static function corporate(string $column, array $variants): ?array
    {
        if (! Schema::hasTable('affiliate_corporates') || ! Schema::hasColumn('affiliate_corporates', $column)) {
            return null;
        }

        $affiliate = AffiliateCorporate::query()
            ->whereIn($column, $variants)
            ->orderByRaw("CASE WHEN status = 'EXCLUIDO' THEN 1 ELSE 0 END")
            ->orderByDesc('id')
            ->first(['id', 'affiliation_corporate_id', 'first_name', 'last_name']);

        if ($affiliate === null) {
            return null;
        }

        $name = trim(trim((string) $affiliate->first_name).' '.trim(str_replace('...', '', (string) $affiliate->last_name)));

        return [
            'kind' => 'afiliado_corporativo',
            'id' => (int) $affiliate->id,
            'affiliation_id' => $affiliate->affiliation_corporate_id !== null ? (int) $affiliate->affiliation_corporate_id : null,
            'name' => $name !== '' ? $name : 'Afiliado corporativo',
        ];
    }

    /**
     * @param  array{kind: 'afiliado'|'afiliado_corporativo'|'agente', id: int, affiliation_id: ?int, name: string}  $match
     */
    public static function url(array $match, string $panel): ?string
    {
        try {
            if ($match['kind'] === 'afiliado_corporativo') {
                if ($panel === 'operations') {
                    return OperationsAffiliateCorporateResource::getUrl('view', ['record' => $match['id']], panel: 'operations');
                }

                if ($match['affiliation_id'] === null) {
                    return null;
                }

                $resource = $panel === 'administration'
                    ? AdministrationAffiliationCorporateResource::class
                    : BusinessAffiliationCorporateResource::class;

                return $resource::getUrl('view', ['record' => $match['affiliation_id']], panel: $panel === 'administration' ? 'administration' : 'business');
            }

            if ($match['kind'] === 'afiliado') {
                if ($panel === 'operations') {
                    return OperationsAffiliateResource::getUrl('view', ['record' => $match['id']], panel: 'operations');
                }

                if ($match['affiliation_id'] === null) {
                    return null;
                }

                $resource = $panel === 'administration'
                    ? AdministrationAffiliationResource::class
                    : BusinessAffiliationResource::class;
                $panelId = $panel === 'administration' ? 'administration' : 'business';

                return $resource::getUrl('view', ['record' => $match['affiliation_id']], panel: $panelId);
            }

            if ($panel === 'operations') {
                return null;
            }

            $resource = $panel === 'administration'
                ? AdministrationAgentResource::class
                : BusinessAgentResource::class;
            $panelId = $panel === 'administration' ? 'administration' : 'business';

            return $resource::getUrl('view', ['record' => $match['id']], panel: $panelId);
        } catch (Throwable) {
            return null;
        }
    }
}
