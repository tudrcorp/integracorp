<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Filament\Administration\Resources\Affiliations\AffiliationResource as AdministrationAffiliationResource;
use App\Filament\Administration\Resources\Agents\AgentResource as AdministrationAgentResource;
use App\Filament\Business\Resources\Affiliations\AffiliationResource as BusinessAffiliationResource;
use App\Filament\Business\Resources\Agents\AgentResource as BusinessAgentResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource as OperationsAffiliateResource;
use App\Models\Affiliate;
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
     * @return array{kind: 'afiliado'|'agente', id: int, affiliation_id: ?int, name: string}|null
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
     * @return array{kind: 'afiliado'|'agente', id: int, affiliation_id: ?int, name: string}|null
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
     * @param  array{kind: 'afiliado'|'agente', id: int, affiliation_id: ?int, name: string}  $match
     */
    public static function url(array $match, string $panel): ?string
    {
        try {
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
