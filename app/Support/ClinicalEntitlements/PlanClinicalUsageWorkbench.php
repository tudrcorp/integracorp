<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Enums\ClinicalQuotaScope;
use App\Enums\ClinicalServiceChannel;
use App\Enums\PlanPricingMode;
use App\Models\Benefit;
use App\Models\BenefitCoverage;
use App\Models\BenefitPlan;
use App\Models\Coverage;
use App\Models\Plan;
use App\Models\PlanBenefitClinicalSetting;
use App\Models\TelemedicineServiceList;
use App\Models\User;
use BackedEnum;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Mesa de trabajo del uso clínico de un plan (Negocios → Planes → Uso clínico).
 *
 * Todo lo que el analista arma en pantalla (copiar de otro plan, agregar un
 * beneficio del catálogo o uno nuevo) vive solo en el estado del formulario
 * hasta que pulsa «Guardar». `save()` escribe todo en una sola transacción:
 * crea los beneficios nuevos, los vincula al plan, persiste el mapa clínico y
 * deja la traza. Si algo falla no queda nada a medias.
 *
 * Forma de una fila del formulario:
 *
 * @phpstan-type ClinicalRow array{
 *     benefit_id: int|null,
 *     benefit_label: string,
 *     origin: string,
 *     copied_from: string|null,
 *     applies_clinically: bool,
 *     channel: string|null,
 *     telemedicine_service_list_id: int|null,
 *     service_id: int|null,
 *     quota_scope: string|null,
 *     quota: int|null,
 * }
 */
final class PlanClinicalUsageWorkbench
{
    /** El beneficio ya está vinculado al plan. */
    public const ORIGIN_PLAN = 'plan';

    /** Beneficio del catálogo que se vinculará al plan al guardar. */
    public const ORIGIN_LINK = 'link';

    /** Beneficio nuevo que se creará en el catálogo y se vinculará al guardar. */
    public const ORIGIN_CREATE = 'create';

    /** Copia: solo completa las filas pendientes; lo que ya está listo no se toca. */
    public const COPY_FILL_PENDING = 'fill_pending';

    /** Copia: reemplaza todas las filas que coinciden con el plan de origen. */
    public const COPY_OVERWRITE = 'overwrite';

    /** @var list<string> */
    private const CLINICAL_FIELDS = [
        'applies_clinically',
        'channel',
        'telemedicine_service_list_id',
        'service_id',
        'quota_scope',
        'quota',
    ];

    /** @var array<int, string>|null */
    private static ?array $serviceMacroNames = null;

    /**
     * Filas del formulario a partir de lo persistido (con los valores por
     * defecto del catálogo para los beneficios aún sin fila).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function hydrate(Plan $plan): array
    {
        $rows = [];

        foreach (PlanClinicalStructurePersistence::hydrate($plan) as $row) {
            $rows[self::newKey()] = $row + [
                'origin' => self::ORIGIN_PLAN,
                'copied_from' => null,
            ];
        }

        return $rows;
    }

    /**
     * Huella de lo que hay guardado. Si cambia entre que el analista abrió la
     * pantalla y pulsa «Guardar», otro usuario guardó en medio y no se pisa.
     */
    public static function fingerprint(Plan $plan): string
    {
        $settings = PlanBenefitClinicalSetting::query()
            ->where('plan_id', $plan->id)
            ->orderBy('benefit_id')
            ->get(['benefit_id', 'applies_clinically', 'channel', 'telemedicine_service_list_id', 'quota_scope', 'quota', 'updated_at'])
            ->map(static fn (PlanBenefitClinicalSetting $row): string => implode('|', [
                $row->benefit_id,
                (int) $row->applies_clinically,
                self::enumValue($row->channel),
                $row->telemedicine_service_list_id,
                self::enumValue($row->quota_scope),
                $row->quota,
                $row->updated_at?->format('Y-m-d H:i:s'),
            ]))
            ->implode(';');

        $benefits = BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->orderBy('benefit_id')
            ->pluck('benefit_id')
            ->implode(',');

        return sha1($settings.'#'.$benefits);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function rowIsComplete(array $row): bool
    {
        if (! filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return true;
        }

        $channel = ClinicalServiceChannel::fromStored($row['channel'] ?? null);
        if (! $channel instanceof ClinicalServiceChannel) {
            return false;
        }

        if ($channel->usesTelemedicineServiceList() && blank($row['telemedicine_service_list_id'] ?? null)) {
            return false;
        }

        $scope = ClinicalQuotaScope::fromStored($row['quota_scope'] ?? null);
        if (! $scope instanceof ClinicalQuotaScope) {
            return false;
        }

        return ! $scope->requiresQuota() || (int) ($row['quota'] ?? 0) >= 1;
    }

    /**
     * Qué le falta a una fila, en palabras del analista.
     *
     * @param  array<string, mixed>  $row
     */
    public static function rowMissingHint(array $row): ?string
    {
        if (self::rowIsComplete($row)) {
            return null;
        }

        $channel = ClinicalServiceChannel::fromStored($row['channel'] ?? null);
        if (! $channel instanceof ClinicalServiceChannel) {
            return 'Elija el servicio clínico';
        }

        if ($channel->usesTelemedicineServiceList() && blank($row['telemedicine_service_list_id'] ?? null)) {
            return 'Elija el servicio macro';
        }

        if (! ClinicalQuotaScope::fromStored($row['quota_scope'] ?? null) instanceof ClinicalQuotaScope) {
            return 'Elija cómo se cuenta el cupo';
        }

        return 'Indique la cantidad del cupo';
    }

    /**
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array{total: int, complete: int, pending: int, not_clinical: int, new: int}
     */
    public static function summarize(array $rows): array
    {
        $summary = ['total' => 0, 'complete' => 0, 'pending' => 0, 'not_clinical' => 0, 'new' => 0];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $summary['total']++;

            if (($row['origin'] ?? self::ORIGIN_PLAN) !== self::ORIGIN_PLAN) {
                $summary['new']++;
            }

            if (! filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
                $summary['not_clinical']++;
                $summary['complete']++;

                continue;
            }

            self::rowIsComplete($row) ? $summary['complete']++ : $summary['pending']++;
        }

        return $summary;
    }

    /**
     * Planes desde los que se puede copiar: los que tienen el uso clínico
     * completo. Nunca el propio plan.
     *
     * @return array<int, string>
     */
    public static function copySourceOptions(Plan $target): array
    {
        $planIds = PlanBenefitClinicalSetting::query()
            ->where('plan_id', '!=', $target->id)
            ->distinct()
            ->pluck('plan_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if ($planIds === []) {
            return [];
        }

        $counts = BenefitPlan::query()
            ->whereIn('plan_id', $planIds)
            ->selectRaw('plan_id, COUNT(*) as total')
            ->groupBy('plan_id')
            ->pluck('total', 'plan_id');

        $options = [];

        foreach (Plan::query()->whereIn('id', $planIds)->orderBy('description')->get() as $plan) {
            if (! PlanClinicalCompleteness::isComplete($plan)) {
                continue;
            }

            $status = strtoupper((string) $plan->status) === 'INACTIVO' ? ' · inactivo' : '';
            $options[(int) $plan->id] = self::planName($plan).' · '.(int) ($counts[$plan->id] ?? 0).' beneficios'.$status;
        }

        return $options;
    }

    /**
     * Configuración clínica del plan de origen, por beneficio.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sourceRows(Plan $source): array
    {
        $labels = BenefitPlan::query()
            ->where('plan_id', $source->id)
            ->pluck('description', 'benefit_id');

        $settings = PlanBenefitClinicalSetting::query()->where('plan_id', $source->id)->get();
        $catalog = Benefit::query()
            ->whereIn('id', $settings->pluck('benefit_id')->all() ?: [0])
            ->pluck('description', 'id');

        $out = [];

        foreach ($settings as $setting) {
            $benefitId = (int) $setting->benefit_id;

            $out[$benefitId] = [
                'benefit_id' => $benefitId,
                'benefit_label' => trim((string) ($labels->get($benefitId) ?? $catalog->get($benefitId) ?? 'Beneficio #'.$benefitId)),
                'catalog_label' => $catalog->has($benefitId) ? trim((string) $catalog->get($benefitId)) : null,
                'applies_clinically' => (bool) $setting->applies_clinically,
                'channel' => self::enumValue($setting->channel),
                'telemedicine_service_list_id' => $setting->telemedicine_service_list_id !== null ? (int) $setting->telemedicine_service_list_id : null,
                'service_id' => $setting->service_id !== null ? (int) $setting->service_id : null,
                'quota_scope' => self::enumValue($setting->quota_scope),
                'quota' => $setting->quota !== null ? (int) $setting->quota : null,
            ];
        }

        return $out;
    }

    /**
     * Empareja las filas de este plan con los beneficios del plan de origen.
     *
     * Primero por id. Si no, por nombre equivalente (sin tildes, mayúsculas ni
     * espacios de más), mirando tanto el nombre que el beneficio tiene en el
     * plan como el del catálogo: el catálogo tiene la misma prestación cargada
     * dos veces con ids distintos (p. ej. LABORATORIOS #5 y #54), y cada plan
     * usa uno. Un emparejamiento por nombre solo vale si es único en ambos
     * lados; ante la duda no se empareja. La configuración siempre se escribe
     * sobre el beneficio propio de este plan: nunca se cruzan ids.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @param  array<int, array<string, mixed>>  $sourceRows
     * @return array{
     *     matches: array<array-key, array{source_id: int, by: string}>,
     *     unmatched_source: list<int>,
     *     ambiguous: array<array-key, true>,
     * }
     */
    public static function matchSourceRows(array $rows, array $sourceRows): array
    {
        $matches = [];
        $claimed = [];

        foreach ($rows as $key => $row) {
            $benefitId = (int) ($row['benefit_id'] ?? 0);
            if ($benefitId > 0 && isset($sourceRows[$benefitId])) {
                $matches[$key] = ['source_id' => $benefitId, 'by' => 'id'];
                $claimed[$benefitId] = true;
            }
        }

        $sourceAliases = self::aliasesFor(array_map('intval', array_keys($sourceRows)));
        $sourceIndex = [];
        foreach ($sourceRows as $sourceId => $sourceRow) {
            if (isset($claimed[$sourceId])) {
                continue;
            }

            foreach (self::nameKeysFor($sourceRow + ['aliases' => $sourceAliases[(int) $sourceId] ?? []]) as $nameKey) {
                $sourceIndex[$nameKey][$sourceId] = true;
            }
        }

        $catalogNames = self::catalogNamesFor($rows);
        $candidates = [];

        foreach ($rows as $key => $row) {
            if (isset($matches[$key])) {
                continue;
            }

            $benefitId = (int) ($row['benefit_id'] ?? 0);
            $keys = self::nameKeysFor($row + ['aliases' => $catalogNames[$benefitId] ?? []]);

            $found = [];
            foreach ($keys as $nameKey) {
                $found += $sourceIndex[$nameKey] ?? [];
            }

            if ($found !== []) {
                $candidates[$key] = array_map('intval', array_keys($found));
            }
        }

        $demand = [];
        foreach ($candidates as $ids) {
            foreach ($ids as $sourceId) {
                $demand[$sourceId] = ($demand[$sourceId] ?? 0) + 1;
            }
        }

        $ambiguous = [];
        foreach ($candidates as $key => $ids) {
            if (count($ids) === 1 && ($demand[$ids[0]] ?? 0) === 1) {
                $matches[$key] = ['source_id' => $ids[0], 'by' => 'name'];
                $claimed[$ids[0]] = true;

                continue;
            }

            $ambiguous[$key] = true;
            foreach ($ids as $sourceId) {
                $claimed[$sourceId] = true;
            }
        }

        $unmatched = [];
        foreach (array_keys($sourceRows) as $sourceId) {
            if (! isset($claimed[$sourceId])) {
                $unmatched[] = (int) $sourceId;
            }
        }

        return ['matches' => $matches, 'unmatched_source' => $unmatched, 'ambiguous' => $ambiguous];
    }

    /**
     * Qué haría la copia sin aplicarla todavía.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array{
     *     apply: list<array{label: string, from: string, to: string, was_complete: bool, by_name: bool, source_label: string}>,
     *     keep: list<array{label: string, reason: string}>,
     *     extra: array<int, string>,
     *     by_name: int,
     * }
     */
    public static function previewCopy(array $rows, Plan $source, string $mode): array
    {
        $sourceRows = self::sourceRows($source);
        $matching = self::matchSourceRows($rows, $sourceRows);
        $apply = [];
        $keep = [];
        $byName = 0;

        foreach ($rows as $key => $row) {
            $label = (string) ($row['benefit_label'] ?? 'Beneficio');
            $hit = $matching['matches'][$key] ?? null;

            if ($hit === null) {
                $keep[] = ['label' => $label, 'reason' => isset($matching['ambiguous'][$key])
                    ? 'Hay más de un beneficio parecido en el plan de origen: configúrelo a mano'
                    : 'El plan de origen no tiene este beneficio'];

                continue;
            }

            $match = $sourceRows[$hit['source_id']];
            $complete = self::rowIsComplete($row);

            if ($mode === self::COPY_FILL_PENDING && $complete) {
                $keep[] = ['label' => $label, 'reason' => 'Ya está listo: no se toca'];

                continue;
            }

            if (self::sameClinicalConfig($row, $match)) {
                $keep[] = ['label' => $label, 'reason' => 'Ya tiene la misma configuración'];

                continue;
            }

            $isByName = $hit['by'] === 'name';
            $byName += $isByName ? 1 : 0;

            $apply[] = [
                'label' => $label,
                'from' => $complete ? self::describe($row) : 'Pendiente',
                'to' => self::describe($match),
                'was_complete' => $complete,
                'by_name' => $isByName,
                'source_label' => (string) $match['benefit_label'].' (#'.$hit['source_id'].')',
            ];
        }

        $extra = [];
        foreach ($matching['unmatched_source'] as $sourceId) {
            $match = $sourceRows[$sourceId];
            $extra[$sourceId] = $match['benefit_label'].' — '.self::describe($match);
        }

        asort($extra);

        return ['apply' => $apply, 'keep' => $keep, 'extra' => $extra, 'by_name' => $byName];
    }

    /**
     * Aplica la copia sobre el estado del formulario. No escribe en la base:
     * el analista revisa y guarda.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @param  list<int|string>  $extraBenefitIds  beneficios del origen que este plan no tiene y el analista marcó
     * @return array{
     *     rows: array<array-key, array<string, mixed>>,
     *     applied: list<string>,
     *     added: list<string>,
     *     matched_by_name: list<array{source_benefit_id: int, target_benefit_id: int, benefit: string}>,
     * }
     */
    public static function applyCopy(array $rows, Plan $source, string $mode, array $extraBenefitIds = []): array
    {
        $sourceRows = self::sourceRows($source);
        $matching = self::matchSourceRows($rows, $sourceRows);
        $sourceName = self::planName($source);
        $applied = [];
        $added = [];
        $byName = [];

        foreach ($rows as $key => $row) {
            $hit = $matching['matches'][$key] ?? null;
            if ($hit === null) {
                continue;
            }

            $match = $sourceRows[$hit['source_id']];

            if ($mode === self::COPY_FILL_PENDING && self::rowIsComplete($row)) {
                continue;
            }

            if (self::sameClinicalConfig($row, $match)) {
                continue;
            }

            foreach (self::CLINICAL_FIELDS as $field) {
                $rows[$key][$field] = $match[$field];
            }

            $rows[$key]['copied_from'] = $sourceName;
            $applied[] = (string) ($row['benefit_label'] ?? 'Beneficio');

            if ($hit['by'] === 'name') {
                $byName[] = [
                    'source_benefit_id' => $hit['source_id'],
                    'target_benefit_id' => (int) ($row['benefit_id'] ?? 0),
                    'benefit' => (string) ($row['benefit_label'] ?? ''),
                ];
            }
        }

        $allowedExtra = array_flip($matching['unmatched_source']);

        foreach (array_unique(array_map('intval', $extraBenefitIds)) as $benefitId) {
            if (! isset($allowedExtra[$benefitId])) {
                continue;
            }

            $benefit = Benefit::query()->find($benefitId);
            if (! $benefit instanceof Benefit) {
                continue;
            }

            $match = $sourceRows[$benefitId];
            $rows[self::newKey()] = [
                'benefit_id' => $benefitId,
                'benefit_label' => (string) $match['benefit_label'],
                'origin' => self::ORIGIN_LINK,
                'copied_from' => $sourceName,
            ] + array_intersect_key($match, array_flip(self::CLINICAL_FIELDS));

            unset($allowedExtra[$benefitId]);
            $added[] = (string) $match['benefit_label'];
        }

        return ['rows' => $rows, 'applied' => $applied, 'added' => $added, 'matched_by_name' => $byName];
    }

    /**
     * Clave de comparación de nombres: sin tildes, en mayúsculas y sin
     * espacios de más. «IMAGENOLOGíA» e «IMAGENOLOGÍA» dan lo mismo.
     */
    public static function nameKey(?string $name): string
    {
        $ascii = Str::ascii((string) $name);

        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $ascii)));
    }

    /**
     * Nombres (en clave de comparación) de lo que el plan ya tiene en pantalla,
     * tanto el nombre en el plan como el del catálogo.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array<string, true>
     */
    private static function presentNameKeys(array $rows): array
    {
        $catalogNames = self::catalogNamesFor($rows);
        $keys = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $benefitId = (int) ($row['benefit_id'] ?? 0);
            foreach (self::nameKeysFor($row + ['aliases' => $catalogNames[$benefitId] ?? []]) as $nameKey) {
                $keys[$nameKey] = true;
            }
        }

        return $keys;
    }

    /**
     * Un beneficio que entra al plan (vinculado o nuevo) no puede ser la misma
     * prestación que otro que el plan ya tiene con otro id del catálogo: el
     * plan quedaría con el beneficio dos veces y el médico vería dos cupos.
     *
     * @param  array<array-key, mixed>  $rows
     */
    private static function assertNoEquivalentDuplicates(array $rows): void
    {
        $rows = array_filter($rows, 'is_array');
        $catalogNames = self::catalogNamesFor($rows);
        $owners = [];

        foreach ($rows as $key => $row) {
            $benefitId = (int) ($row['benefit_id'] ?? 0);
            foreach (self::nameKeysFor($row + ['aliases' => $catalogNames[$benefitId] ?? []]) as $nameKey) {
                $owners[$nameKey][$key] = $row;
            }
        }

        foreach ($owners as $sameName) {
            if (count($sameName) < 2) {
                continue;
            }

            $ids = array_unique(array_map(static fn (array $row): string => (string) ($row['benefit_id'] ?? 'nuevo'), $sameName));
            $hasNew = array_filter($sameName, static fn (array $row): bool => ($row['origin'] ?? self::ORIGIN_PLAN) !== self::ORIGIN_PLAN) !== [];

            if (count($ids) < 2 || ! $hasNew) {
                continue;
            }

            $labels = array_map(
                static fn (array $row): string => '«'.trim((string) ($row['benefit_label'] ?? '')).'»'.(filled($row['benefit_id'] ?? null) ? ' (#'.$row['benefit_id'].')' : ' (nuevo)'),
                array_values($sameName),
            );

            throw new InvalidArgumentException(
                'El plan quedaría con el mismo beneficio dos veces: '.implode(' y ', $labels).'. Quite la fila nueva (botón de la papelera) y guarde de nuevo.'
            );
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    private static function nameKeysFor(array $row): array
    {
        $keys = [];

        $names = [$row['benefit_label'] ?? null, $row['catalog_label'] ?? null, ...array_values((array) ($row['aliases'] ?? []))];

        foreach ($names as $name) {
            $nameKey = self::nameKey(is_string($name) ? $name : null);
            if ($nameKey !== '') {
                $keys[$nameKey] = true;
            }
        }

        return array_keys($keys);
    }

    /**
     * @param  array<array-key, mixed>  $rows
     * @return array<int, list<string>>
     */
    private static function catalogNamesFor(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $benefitId = is_array($row) ? (int) ($row['benefit_id'] ?? 0) : 0;
            if ($benefitId > 0) {
                $ids[] = $benefitId;
            }
        }

        return self::aliasesFor($ids);
    }

    /**
     * Todos los nombres con que se conoce cada beneficio: el del catálogo y el
     * que lleva en cada plan donde está. Así se reconoce que el #5 «LABORATORIOS
     * CON FINES DIAGNÓSTICOS» es la misma prestación que el #54, porque en el
     * PLAN ESPECIAL el #5 se llama «LABORATORIOS A DOMICILIO…».
     *
     * @param  list<int>  $ids
     * @return array<int, list<string>>
     */
    private static function aliasesFor(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
        if ($ids === []) {
            return [];
        }

        $aliases = [];

        foreach (Benefit::query()->whereIn('id', $ids)->get(['id', 'description']) as $benefit) {
            $aliases[(int) $benefit->id][] = (string) $benefit->description;
        }

        foreach (BenefitPlan::query()->whereIn('benefit_id', $ids)->get(['benefit_id', 'description']) as $link) {
            $aliases[(int) $link->benefit_id][] = (string) $link->description;
        }

        return array_map(static fn (array $names): array => array_values(array_unique($names)), $aliases);
    }

    /**
     * Beneficios activos del catálogo que el plan aún no tiene (ni en pantalla).
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array<int, string>
     */
    public static function catalogBenefitOptions(array $rows): array
    {
        $present = [];
        foreach ($rows as $row) {
            $benefitId = (int) ($row['benefit_id'] ?? 0);
            if ($benefitId > 0) {
                $present[] = $benefitId;
            }
        }

        $presentKeys = self::presentNameKeys($rows);

        return Benefit::query()
            ->where('status', 'ACTIVO')
            ->when($present !== [], static fn ($query) => $query->whereNotIn('id', $present))
            ->orderBy('description')
            ->get(['id', 'code', 'description'])
            ->pipe(static function ($benefits) use ($presentKeys) {
                $aliases = self::aliasesFor($benefits->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());

                return $benefits->reject(static function (Benefit $benefit) use ($presentKeys, $aliases): bool {
                    foreach (self::nameKeysFor(['aliases' => $aliases[(int) $benefit->id] ?? [(string) $benefit->description]]) as $nameKey) {
                        if (isset($presentKeys[$nameKey])) {
                            return true;
                        }
                    }

                    return false;
                });
            })
            ->mapWithKeys(static fn (Benefit $benefit): array => [
                (int) $benefit->id => trim((string) $benefit->description).(filled($benefit->code) ? ' ('.$benefit->code.')' : ''),
            ])
            ->all();
    }

    /**
     * Fila nueva para un beneficio del catálogo, con su configuración clínica
     * por defecto si el catálogo la tiene.
     *
     * @return array<string, mixed>
     */
    public static function rowForCatalogBenefit(Benefit $benefit): array
    {
        $default = $benefit->clinicalSetting;

        return [
            'benefit_id' => (int) $benefit->id,
            'benefit_label' => trim((string) $benefit->description),
            'origin' => self::ORIGIN_LINK,
            'copied_from' => null,
            'applies_clinically' => $default?->applies_clinically ?? true,
            'channel' => self::enumValue($default?->channel),
            'telemedicine_service_list_id' => $default?->telemedicine_service_list_id,
            'service_id' => $default?->service_id,
            'quota_scope' => self::enumValue($default?->quota_scope),
            'quota' => $default?->quota,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function rowForNewBenefit(string $description): array
    {
        return [
            'benefit_id' => null,
            'benefit_label' => self::normalizeBenefitDescription($description),
            'origin' => self::ORIGIN_CREATE,
            'copied_from' => null,
            'applies_clinically' => true,
            'channel' => null,
            'telemedicine_service_list_id' => null,
            'service_id' => null,
            'quota_scope' => null,
            'quota' => null,
        ];
    }

    public static function normalizeBenefitDescription(string $description): string
    {
        return mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $description)));
    }

    public static function findCatalogBenefitByDescription(string $description): ?Benefit
    {
        $normalized = self::normalizeBenefitDescription($description);
        if ($normalized === '') {
            return null;
        }

        return Benefit::query()
            ->whereRaw('UPPER(TRIM(description)) = ?', [$normalized])
            ->orderByRaw("CASE WHEN status = 'ACTIVO' THEN 0 ELSE 1 END")
            ->first();
    }

    /**
     * Guarda todo lo armado en pantalla en una sola transacción.
     *
     * @param  array<array-key, array<string, mixed>>  $rows
     * @return array{changed: int, linked: list<string>, created: list<string>}
     *
     * @throws PlanClinicalUsageConflictException si otro usuario guardó mientras tanto
     */
    public static function save(Plan $plan, array $rows, string $expectedFingerprint, User $actor, ?int $copiedFromPlanId = null): array
    {
        return DB::transaction(static function () use ($plan, $rows, $expectedFingerprint, $actor, $copiedFromPlanId): array {
            Plan::query()->whereKey($plan->id)->lockForUpdate()->first();

            if (! hash_equals(self::fingerprint($plan), $expectedFingerprint)) {
                throw new PlanClinicalUsageConflictException(
                    'Otro usuario guardó cambios en el uso clínico de este plan mientras usted trabajaba. Recargue la pantalla para ver lo último y vuelva a aplicar sus cambios.'
                );
            }

            self::assertNoEquivalentDuplicates($rows);

            $actorName = (string) $actor->name;
            $before = self::sourceRows($plan);
            $mode = $plan->pricingMode();
            $linked = [];
            $created = [];
            $resolved = [];
            $seen = [];

            foreach ($rows as $row) {
                if (! is_array($row)) {
                    continue;
                }

                $origin = (string) ($row['origin'] ?? self::ORIGIN_PLAN);
                $benefitId = (int) ($row['benefit_id'] ?? 0);

                if ($origin === self::ORIGIN_CREATE) {
                    $benefit = self::createCatalogBenefit($plan, (string) ($row['benefit_label'] ?? ''), $actor);
                    $benefitId = (int) $benefit->id;
                    $created[] = trim((string) $benefit->description);
                }

                if ($benefitId < 1) {
                    continue;
                }

                if (isset($seen[$benefitId])) {
                    throw new InvalidArgumentException('El beneficio «'.($row['benefit_label'] ?? '#'.$benefitId).'» aparece dos veces. Quite la fila repetida antes de guardar.');
                }

                $seen[$benefitId] = true;

                if ($origin !== self::ORIGIN_PLAN && self::linkBenefitToPlan($plan, $benefitId, $mode, $actor)) {
                    $linked[] = trim((string) ($row['benefit_label'] ?? ''));
                }

                $row['benefit_id'] = $benefitId;
                $resolved[] = $row;

                if ($origin === self::ORIGIN_CREATE) {
                    PlanClinicalStructurePersistence::persistBenefitDefault($benefitId, $row, $actorName);
                }
            }

            $missing = array_diff(PlanClinicalCompleteness::assignedBenefitIds($plan), array_keys($seen));
            if ($missing !== []) {
                throw new InvalidArgumentException('La pantalla no trae todos los beneficios del plan (faltan '.count($missing).'). Recargue para ver la estructura vigente.');
            }

            PlanClinicalStructurePersistence::persist($plan, $resolved, $actorName);

            $after = self::sourceRows($plan);
            $changes = self::diff($before, $after);

            $source = $copiedFromPlanId !== null ? Plan::query()->find($copiedFromPlanId) : null;
            $description = $changes === [] && $linked === [] && $created === []
                ? 'Guardado sin cambios en el mapa clínico.'
                : 'Mapa clínico guardado: '.count($changes).' beneficio(s) con cambios'
                    .($linked !== [] ? ', '.count($linked).' vinculado(s)' : '')
                    .($created !== [] ? ', '.count($created).' creado(s) en el catálogo' : '')
                    .($source instanceof Plan ? '. Partió de una copia de '.self::planName($source).'.' : '.');

            PlanClinicalUsageTrace::record(
                PlanClinicalUsageTrace::MAP_SAVED,
                (int) $plan->id,
                $description,
                [
                    'benefits' => $changes,
                    'linked' => $linked,
                    'created' => $created,
                    'complete' => PlanClinicalCompleteness::isComplete($plan),
                ],
                $source instanceof Plan ? (int) $source->id : null,
                $actor,
            );

            return ['changed' => count($changes), 'linked' => $linked, 'created' => $created];
        });
    }

    /**
     * Crea un servicio macro (nivel 1 de la lista de telemedicina). Si ya existe
     * uno con el mismo nombre, lo devuelve en vez de duplicarlo.
     *
     * @return array{service: TelemedicineServiceList, created: bool}
     */
    public static function createServiceMacro(string $name, ?string $description, User $actor, ?int $planId = null): array
    {
        $normalized = mb_strtoupper(trim((string) preg_replace('/\s+/u', ' ', $name)));
        if ($normalized === '') {
            throw new InvalidArgumentException('Escriba el nombre del servicio macro.');
        }

        return DB::transaction(static function () use ($normalized, $description, $actor, $planId): array {
            $existing = TelemedicineServiceList::query()
                ->where('level', '1')
                ->whereRaw('UPPER(TRIM(name)) = ?', [$normalized])
                ->lockForUpdate()
                ->first();

            if ($existing instanceof TelemedicineServiceList) {
                return ['service' => $existing, 'created' => false];
            }

            $service = new TelemedicineServiceList;
            $service->forceFill([
                'name' => $normalized,
                'description' => filled($description) ? trim((string) $description) : Str::title(mb_strtolower($normalized)),
                'level' => '1',
            ])->save();

            self::$serviceMacroNames = null;

            PlanClinicalUsageTrace::record(
                PlanClinicalUsageTrace::SERVICE_MACRO_CREATED,
                $planId,
                'Servicio macro «'.$normalized.'» creado. Ya aparece en el select de la consulta de todos los planes que lo usen.',
                ['telemedicine_service_list_id' => (int) $service->id, 'name' => $normalized],
                null,
                $actor,
            );

            return ['service' => $service, 'created' => true];
        });
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function describe(array $row): string
    {
        if (! filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN)) {
            return 'No aplica en consulta';
        }

        $channel = ClinicalServiceChannel::fromStored($row['channel'] ?? null);
        $scope = ClinicalQuotaScope::fromStored($row['quota_scope'] ?? null);

        $parts = [];
        $parts[] = $channel?->shortLabel() ?? 'Sin servicio';

        if ($channel?->usesTelemedicineServiceList() && filled($row['telemedicine_service_list_id'] ?? null)) {
            $parts[] = (string) (self::serviceMacroNames()[(int) $row['telemedicine_service_list_id']] ?? '#'.$row['telemedicine_service_list_id']);
        }

        if ($scope instanceof ClinicalQuotaScope) {
            $parts[] = $scope->requiresQuota()
                ? (int) ($row['quota'] ?? 0).' · '.mb_strtolower($scope->label())
                : $scope->label();
        } else {
            $parts[] = 'sin cupo';
        }

        return implode(' · ', $parts);
    }

    /**
     * @return array<int, string>
     */
    public static function serviceMacroNames(): array
    {
        return self::$serviceMacroNames ??= TelemedicineServiceList::query()
            ->where('level', '1')
            ->orderBy('name')
            ->pluck('name', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();
    }

    public static function planName(Plan $plan): string
    {
        return filled($plan->description) ? (string) $plan->description : (string) $plan->code;
    }

    public static function newKey(): string
    {
        return (string) Str::uuid();
    }

    private static function createCatalogBenefit(Plan $plan, string $description, User $actor): Benefit
    {
        $normalized = self::normalizeBenefitDescription($description);
        if ($normalized === '') {
            throw new InvalidArgumentException('Un beneficio nuevo no tiene nombre. Escríbalo o quite la fila.');
        }

        $existing = self::findCatalogBenefitByDescription($normalized);
        if ($existing instanceof Benefit) {
            return $existing;
        }

        $benefit = Benefit::query()->create([
            'code' => 'TDEC-BN-'.str_pad((string) (((int) Benefit::query()->lockForUpdate()->max('id')) + 1), 4, '0', STR_PAD_LEFT),
            'description' => $normalized,
            'status' => 'ACTIVO',
            'created_by' => (string) $actor->name,
            'updated_by' => (string) $actor->name,
            'is_upgrade' => false,
        ]);

        PlanClinicalUsageTrace::record(
            PlanClinicalUsageTrace::BENEFIT_CREATED,
            (int) $plan->id,
            'Beneficio «'.$normalized.'» creado en el catálogo ('.$benefit->code.') desde el uso clínico.',
            ['benefit_id' => (int) $benefit->id, 'code' => $benefit->code],
            null,
            $actor,
        );

        return $benefit;
    }

    /**
     * Vincula el beneficio al plan sin tocar tarifas. En un plan por coberturas
     * deja el costo límite en NULL (sin límite) en cada cobertura del plan,
     * igual que el asistente cuando la celda queda vacía.
     */
    private static function linkBenefitToPlan(Plan $plan, int $benefitId, PlanPricingMode $mode, User $actor): bool
    {
        $benefit = Benefit::query()->find($benefitId);
        if (! $benefit instanceof Benefit) {
            throw new InvalidArgumentException('El beneficio #'.$benefitId.' ya no existe en el catálogo.');
        }

        $alreadyLinked = BenefitPlan::query()
            ->where('plan_id', $plan->id)
            ->where('benefit_id', $benefitId)
            ->exists();

        if ($alreadyLinked) {
            return false;
        }

        BenefitPlan::query()->create([
            'plan_id' => $plan->id,
            'benefit_id' => $benefitId,
            'description' => (string) $benefit->description,
        ]);

        $coverageIds = [];

        if ($mode === PlanPricingMode::Coberturas) {
            foreach (Coverage::query()->where('plan_id', $plan->id)->get(['id', 'price']) as $coverage) {
                BenefitCoverage::query()->firstOrCreate(
                    [
                        'plan_id' => $plan->id,
                        'benefit_id' => $benefitId,
                        'coverage_id' => (int) $coverage->id,
                    ],
                    [
                        'limit' => null,
                        'benefit_description' => (string) $benefit->description,
                        'coverage_price' => (string) $coverage->price,
                        'price' => '1',
                    ],
                );

                $coverageIds[] = (int) $coverage->id;
            }
        }

        PlanClinicalUsageTrace::record(
            PlanClinicalUsageTrace::BENEFIT_LINKED,
            (int) $plan->id,
            'Beneficio «'.trim((string) $benefit->description).'» vinculado al plan'
                .($coverageIds !== [] ? ' sin costo límite en '.count($coverageIds).' cobertura(s)' : '')
                .'. Tarifas sin cambios.',
            ['benefit_id' => $benefitId, 'coverage_ids' => $coverageIds],
            null,
            $actor,
        );

        return true;
    }

    /**
     * @param  array<int, array<string, mixed>>  $before
     * @param  array<int, array<string, mixed>>  $after
     * @return list<array{benefit_id: int, benefit: string, before: string|null, after: string|null}>
     */
    private static function diff(array $before, array $after): array
    {
        $changes = [];

        foreach ($after + $before as $benefitId => $row) {
            $old = $before[$benefitId] ?? null;
            $new = $after[$benefitId] ?? null;

            if ($old !== null && $new !== null && self::sameClinicalConfig($old, $new)) {
                continue;
            }

            $changes[] = [
                'benefit_id' => (int) $benefitId,
                'benefit' => (string) ($row['benefit_label'] ?? '#'.$benefitId),
                'before' => $old !== null ? self::describe($old) : null,
                'after' => $new !== null ? self::describe($new) : null,
            ];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $a
     * @param  array<string, mixed>  $b
     */
    private static function sameClinicalConfig(array $a, array $b): bool
    {
        $normalize = static function (array $row): array {
            $applies = filter_var($row['applies_clinically'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $channel = $applies ? ClinicalServiceChannel::fromStored($row['channel'] ?? null) : null;
            $scope = $applies ? ClinicalQuotaScope::fromStored($row['quota_scope'] ?? null) : null;

            return [
                $applies,
                $channel?->value,
                $channel?->usesTelemedicineServiceList() && filled($row['telemedicine_service_list_id'] ?? null) ? (int) $row['telemedicine_service_list_id'] : null,
                $scope?->value,
                $scope?->requiresQuota() ? (int) ($row['quota'] ?? 0) : null,
            ];
        };

        return $normalize($a) === $normalize($b);
    }

    private static function enumValue(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        return filled($value) ? (string) $value : null;
    }
}
