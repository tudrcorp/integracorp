<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates\Population;

use App\Models\AffiliateCorporate;
use App\Models\AffiliationCorporate;
use App\Models\CorporatePopulationUpdate;
use App\Models\CorporatePopulationUpdateItem as Item;

/**
 * Vuelve a clasificar UNA fila de la carga con las mismas reglas del análisis,
 * después de que el analista corrigió una ficha o tomó una decisión sobre ella.
 *
 * No escribe afiliados: solo la fila de la carga y el resumen. Debe llamarse
 * dentro de la transacción que ya bloqueó la carga.
 */
final class PopulationItemReevaluator
{
    /** Claves de `details` que son decisiones o bitácora del analista: sobreviven a la reevaluación. */
    private const KEPT_DETAILS = ['corrections', 'accepted', 'confirmed_same_person', 'reviewed', 'not_same_as', 'duplicate_resolution', 'freed_from_item', 'original_id', 'first_row'];

    /** Mínimos del cruce (ver {@see PopulationMatcher}). */
    private const DOCUMENT_NAME_SIMILARITY = 0.6;

    private const BIRTH_NAME_SIMILARITY = 0.8;

    public static function reevaluate(Item $item, CorporatePopulationUpdate $update): Item
    {
        $person = is_array($item->file_data) ? $item->file_data : null;

        if ($person === null || in_array($item->action, [Item::ACTION_DUPLICATE_FILE, Item::ACTION_INVALID, Item::ACTION_BLOCKED_OPEN_CASE], true)) {
            self::refreshObserved($item);
            $item->save();

            return $item;
        }

        $owner = AffiliationCorporate::query()->findOrFail($update->affiliation_corporate_id);
        $affiliate = $item->affiliate_corporate_id !== null
            ? AffiliateCorporate::query()->find((int) $item->affiliate_corporate_id)
            : null;

        $kept = array_intersect_key((array) $item->details, array_flip(self::KEPT_DETAILS));

        $proposal = $affiliate instanceof AffiliateCorporate
            ? self::paired($owner, $item, $person, $affiliate, $kept)
            : self::unpaired($owner, $update, $item, $person, $kept);

        $item->forceFill([
            'action' => $proposal['action'],
            'selectable' => $proposal['selectable'],
            'selected' => $proposal['selected'],
            'affiliate_corporate_id' => $proposal['affiliate_corporate_id'],
            'match_via' => $proposal['match_via'],
            'reason' => $proposal['reason'],
            'details' => $proposal['details'] === [] ? null : $proposal['details'],
        ])->save();

        return $item;
    }

    /**
     * Cómo se confirma que una fila y una ficha son la misma persona, o null si
     * las reglas no alcanzan. La palabra del analista también confirma.
     *
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $details
     */
    public static function confirmation(array $person, AffiliateCorporate $affiliate, array $details = []): ?string
    {
        $fileKey = (string) ($person['doc_key'] ?? PopulationPerson::documentKey((string) ($person['document'] ?? '')));
        $baseKey = PopulationPerson::documentKey((string) $affiliate->nro_identificacion);
        $similarity = PopulationPerson::similarity((string) ($person['first_name'] ?? ''), (string) ($person['last_name'] ?? ''), trim($affiliate->first_name.' '.$affiliate->last_name));
        $birth = $person['birth_date'] ?? null;
        $sameBirth = $birth !== null && $birth === PopulationPerson::storedDate((string) $affiliate->birth_date);

        return match (true) {
            PopulationPerson::isIdentifyingDocument($fileKey) && $fileKey === $baseKey && ($similarity >= self::DOCUMENT_NAME_SIMILARITY || $sameBirth) => 'cedula',
            $sameBirth && $similarity >= self::BIRTH_NAME_SIMILARITY => 'nombre_y_fecha',
            (bool) ($details['confirmed_same_person'] ?? false) => 'analista',
            default => null,
        };
    }

    /**
     * Recalcula los contadores del resumen a partir de las filas guardadas.
     */
    public static function refreshSummary(CorporatePopulationUpdate $update): void
    {
        $rows = Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->selectRaw('action, selected, count(*) as total')
            ->groupBy('action', 'selected')
            ->get();

        $counts = [];
        $selected = [];
        foreach ($rows as $row) {
            $counts[$row->action] = ($counts[$row->action] ?? 0) + (int) $row->total;
            if ((bool) $row->selected) {
                $selected[$row->action] = ($selected[$row->action] ?? 0) + (int) $row->total;
            }
        }

        $summary = (array) $update->summary;
        $summary['counts'] = $counts;
        $summary['selected'] = $selected;
        $summary['match_with_differences'] = Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->where('action', Item::ACTION_MATCH)
            ->whereJsonLength('details->differences', '>', 0)
            ->count();

        $update->forceFill(['summary' => $summary])->save();
    }

    /**
     * Vuelve a tomar la foto de la ficha (huella, datos y diferencias) sin reclasificar.
     */
    public static function refreshObserved(Item $item): void
    {
        if ($item->affiliate_corporate_id === null) {
            return;
        }

        $affiliate = AffiliateCorporate::query()->find((int) $item->affiliate_corporate_id);
        if ($affiliate instanceof AffiliateCorporate) {
            $item->details = PopulationUpdateAnalyzer::observedDetails((array) $item->details, $affiliate, is_array($item->file_data) ? $item->file_data : null);
        }
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $kept
     * @return array{action: string, selectable: bool, selected: bool, affiliate_corporate_id: int|null, match_via: string|null, reason: string|null, details: array<string, mixed>}
     */
    private static function paired(AffiliationCorporate $owner, Item $item, array $person, AffiliateCorporate $affiliate, array $kept): array
    {
        $details = PopulationUpdateAnalyzer::observedDetails($kept, $affiliate, $person);
        $via = self::confirmation($person, $affiliate, $kept);
        $name = trim($affiliate->first_name.' '.$affiliate->last_name);

        if ($via === null) {
            $pending = array_map(PopulationFieldComparison::label(...), (array) ($details['differences'] ?? []));

            return self::proposal(Item::ACTION_REVIEW, false, false, (int) $affiliate->getKey(), $item->match_via, 'Todavía no se confirma que sea «'.$name.'»: no cuadran '.mb_strtolower(implode(', ', $pending) ?: 'los datos de identidad').'.', $details);
        }

        $countable = in_array(strtoupper(trim((string) $affiliate->status)), PopulationMatcher::COUNTABLE_STATUSES, true);

        if ((bool) ($person['excluded'] ?? false)) {
            if (! $countable) {
                return self::proposal(Item::ACTION_MATCH, false, false, (int) $affiliate->getKey(), $via, 'Marcado de baja en el archivo y ya inactivo en la base.', $details);
            }

            $items = PopulationUpdateAnalyzer::blockOpenActivity($owner, [self::proposal(Item::ACTION_DEACTIVATE, true, true, (int) $affiliate->getKey(), $via, 'El archivo lo marca de baja.', $details)], collect([$affiliate]));

            return $items[0];
        }

        $baseKey = PopulationPerson::documentKey((string) $affiliate->nro_identificacion);
        $fileKey = (string) ($person['doc_key'] ?? '');
        $fixTo = ! ($person['minor_document'] ?? false) && PopulationPerson::isIdentifyingDocument($fileKey) && $fileKey !== $baseKey
            ? PopulationMatcher::documentFix($person, ['id' => (int) $affiliate->getKey(), 'doc_key' => $baseKey], self::documentOwners($owner))
            : null;

        if (! $countable) {
            return self::proposal(Item::ACTION_REACTIVATE, true, true, (int) $affiliate->getKey(), $via, 'Está en el archivo y en la base figura '.$affiliate->status.'.', $fixTo !== null ? $details + ['fix_document_to' => $fixTo] : $details);
        }

        if ($fixTo !== null) {
            return self::proposal(Item::ACTION_FIX_DOCUMENT, true, true, (int) $affiliate->getKey(), $via, 'Cédula en la base: «'.$affiliate->nro_identificacion.'». En el archivo: «'.($person['document'] ?? '').'».', $details + ['fix_document_to' => $fixTo]);
        }

        return self::proposal(Item::ACTION_MATCH, false, false, (int) $affiliate->getKey(), $via, null, $details);
    }

    /**
     * Fila sin ficha: es un alta, con las mismas validaciones del análisis
     * (plan por edad y persona vigente en otra afiliación).
     *
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $kept
     * @return array{action: string, selectable: bool, selected: bool, affiliate_corporate_id: int|null, match_via: string|null, reason: string|null, details: array<string, mixed>}
     */
    private static function unpaired(AffiliationCorporate $owner, CorporatePopulationUpdate $update, Item $item, array $person, array $kept): array
    {
        if ((bool) ($person['excluded'] ?? false)) {
            return self::proposal(Item::ACTION_INVALID, false, false, null, null, 'El archivo lo marca de baja y no existe en la base: no se hace nada.', $kept);
        }

        $draft = [
            'action' => Item::ACTION_ADD,
            'selectable' => true,
            'selected' => true,
            'file_row' => $item->file_row,
            'file_data' => $person,
            'affiliate_corporate_id' => null,
            'match_via' => null,
            'reason' => 'No existe en la afiliación.',
            'details' => $kept,
        ];

        $leaving = Item::query()
            ->where('corporate_population_update_id', $update->getKey())
            ->whereKeyNot($item->getKey())
            ->whereIn('action', [Item::ACTION_DEACTIVATE, Item::ACTION_DUPLICATE_BASE])
            ->whereNotNull('affiliate_corporate_id')
            ->pluck('affiliate_corporate_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $items = PopulationUpdateAnalyzer::resolvePlans($owner, [$draft]);
        $items = PopulationUpdateAnalyzer::blockPeopleActiveElsewhere($owner, $items, $leaving);
        $result = $items[0];

        return self::proposal($result['action'], (bool) $result['selectable'], (bool) $result['selected'], null, null, $result['reason'], (array) $result['details']);
    }

    /**
     * Cédulas reales de la afiliación con sus dueños (para no proponer una cédula ya usada).
     *
     * @return array<string, list<int>>
     */
    private static function documentOwners(AffiliationCorporate $owner): array
    {
        $owners = [];
        AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->get(['id', 'nro_identificacion'])
            ->each(static function (AffiliateCorporate $row) use (&$owners): void {
                $key = PopulationPerson::documentKey((string) $row->nro_identificacion);
                if (PopulationPerson::isIdentifyingDocument($key)) {
                    $owners[$key][] = (int) $row->id;
                }
            });

        return $owners;
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array{action: string, selectable: bool, selected: bool, affiliate_corporate_id: int|null, match_via: string|null, reason: string|null, details: array<string, mixed>}
     */
    private static function proposal(string $action, bool $selectable, bool $selected, ?int $affiliateId, ?string $via, ?string $reason, array $details): array
    {
        return [
            'action' => $action,
            'selectable' => $selectable,
            'selected' => $selected,
            'affiliate_corporate_id' => $affiliateId,
            'match_via' => $via,
            'reason' => $reason,
            'details' => $details,
        ];
    }
}
