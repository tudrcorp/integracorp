<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Models\AffiliateCorporate;
use App\Models\AffiliateCorporateIlsVoucher;
use App\Models\AffiliationCorporate;
use App\Models\BenefitCoverage;
use App\Models\Coverage;
use App\Models\PlanGeneratorColumn;
use App\Support\SecurityAudit;
use Carbon\Carbon;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * Vouchers ILS por beneficio y cobertura de los afiliados corporativos.
 *
 * Solo llevan voucher los beneficios con **tope en USD** en la cobertura del
 * afiliado (`benefit_coverages.limit`). El analista nunca elige beneficio ni
 * cobertura: salen del plan y la cobertura de los afiliados seleccionados, que
 * deben compartir **una sola** cobertura. Así no hay forma de cargar el voucher
 * de un beneficio en una cobertura que no le corresponde.
 *
 * Cada bloque (beneficio) tiene su propio número, vigencia y comprobante. Un
 * bloque vacío no toca nada; uno con datos reemplaza el voucher que ese
 * beneficio ya tuviera para los afiliados seleccionados.
 */
final class CorporateAffiliateIlsVoucherManager
{
    public const DOCUMENT_DISK = 'public';

    public const DOCUMENT_DIRECTORY = 'vauches';

    /** @var list<string> */
    public const NON_ELIGIBLE_STATUSES = ['INACTIVO', 'EXCLUIDO'];

    /**
     * @var array<string, list<array{key: string, benefit_coverage_id: int, plan_id: int, benefit_id: int, coverage_id: int, benefit: string, limit: float}>>
     */
    private static array $eligibleCache = [];

    /** @var array<int, string> */
    private static array $coverageLabelCache = [];

    public static function flush(): void
    {
        self::$eligibleCache = [];
        self::$coverageLabelCache = [];
    }

    /**
     * Beneficios con tope en USD de la cobertura, ordenados por nombre.
     *
     * @return list<array{key: string, benefit_coverage_id: int, plan_id: int, benefit_id: int, coverage_id: int, benefit: string, limit: float}>
     */
    public static function eligibleFor(?int $planId, ?int $coverageId): array
    {
        if ($planId === null || $planId <= 0 || $coverageId === null || $coverageId <= 0) {
            return [];
        }

        $cacheKey = $planId.'|'.$coverageId;

        if (isset(self::$eligibleCache[$cacheKey])) {
            return self::$eligibleCache[$cacheKey];
        }

        $rows = BenefitCoverage::query()
            ->where('plan_id', $planId)
            ->where('coverage_id', $coverageId)
            ->whereNotNull('limit')
            ->where('limit', '>', 0)
            ->with('benefit:id,description')
            ->orderBy('id')
            ->get(['id', 'plan_id', 'benefit_id', 'coverage_id', 'limit']);

        $eligible = [];

        foreach ($rows as $row) {
            $benefitId = (int) $row->benefit_id;

            // Una fila duplicada del mismo beneficio no abre un segundo voucher.
            if ($benefitId <= 0 || isset($eligible[$benefitId])) {
                continue;
            }

            $eligible[$benefitId] = [
                'key' => self::blockKey($benefitId),
                'benefit_coverage_id' => (int) $row->getKey(),
                'plan_id' => $planId,
                'benefit_id' => $benefitId,
                'coverage_id' => $coverageId,
                'benefit' => trim((string) ($row->benefit?->description ?? 'Beneficio #'.$benefitId)),
                'limit' => round((float) $row->limit, 2),
            ];
        }

        $eligible = array_values($eligible);
        usort($eligible, static fn (array $a, array $b): int => strcmp($a['benefit'], $b['benefit']));

        return self::$eligibleCache[$cacheKey] = $eligible;
    }

    /**
     * Nombre de la cobertura como la conoce el analista: el encabezado de la
     * columna del plan generado («PLAN IDEAL 3K») o, si no viene de ahí, su
     * monto.
     */
    public static function coverageLabel(int $coverageId): string
    {
        if (isset(self::$coverageLabelCache[$coverageId])) {
            return self::$coverageLabelCache[$coverageId];
        }

        $header = PlanGeneratorColumn::query()
            ->where('coverage_id', $coverageId)
            ->whereNotNull('header_label')
            ->orderByDesc('id')
            ->value('header_label');

        if (filled($header)) {
            return self::$coverageLabelCache[$coverageId] = trim((string) $header);
        }

        $price = Coverage::query()->whereKey($coverageId)->value('price');

        return self::$coverageLabelCache[$coverageId] = $price !== null
            ? 'Cobertura '.self::money((float) $price)
            : 'Cobertura #'.$coverageId;
    }

    public static function blockKey(int $benefitId): string
    {
        return 'b'.$benefitId;
    }

    /**
     * Valida que la selección se pueda cargar en un solo modal.
     *
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     * @return array{plan_id: int, coverage_id: int, coverage_label: string, affiliates: Collection<int, AffiliateCorporate>, eligible: list<array{key: string, benefit_coverage_id: int, plan_id: int, benefit_id: int, coverage_id: int, benefit: string, limit: float}>}
     *
     * @throws InvalidArgumentException con el motivo, para mostrarlo tal cual
     */
    public static function resolveSelection(Collection $affiliates): array
    {
        if ($affiliates->isEmpty()) {
            throw new InvalidArgumentException('Seleccione al menos un afiliado.');
        }

        $inactive = $affiliates->filter(fn (AffiliateCorporate $affiliate): bool => in_array(
            strtoupper(trim((string) $affiliate->status)),
            self::NON_ELIGIBLE_STATUSES,
            true,
        ));

        if ($inactive->isNotEmpty()) {
            throw new InvalidArgumentException($inactive->count().' afiliado(s) están inactivos o excluidos ('
                .self::namesLine($inactive).'). Quítelos de la selección.');
        }

        $withoutCoverage = $affiliates->filter(fn (AffiliateCorporate $affiliate): bool => (int) $affiliate->plan_id <= 0
            || (int) $affiliate->coverage_id <= 0);

        if ($withoutCoverage->isNotEmpty()) {
            throw new InvalidArgumentException($withoutCoverage->count().' afiliado(s) no tienen plan o cobertura asignada ('
                .self::namesLine($withoutCoverage).'). Use «Sincronizar con la afiliación» antes de cargar vouchers.');
        }

        $groups = $affiliates->groupBy(fn (AffiliateCorporate $affiliate): string => (int) $affiliate->plan_id.'|'.(int) $affiliate->coverage_id);

        if ($groups->count() > 1) {
            $detail = $groups
                ->map(fn (Collection $group): string => self::coverageLabel((int) $group->first()->coverage_id).' ('.$group->count().')')
                ->implode(', ');

            throw new InvalidArgumentException('La selección mezcla coberturas: '.$detail
                .'. Seleccione afiliados de una sola cobertura para no cruzar vouchers.');
        }

        /** @var AffiliateCorporate $first */
        $first = $affiliates->first();
        $planId = (int) $first->plan_id;
        $coverageId = (int) $first->coverage_id;
        $coverageLabel = self::coverageLabel($coverageId);
        $eligible = self::eligibleFor($planId, $coverageId);

        if ($eligible === []) {
            throw new InvalidArgumentException('La cobertura '.$coverageLabel
                .' no tiene beneficios con límite en USD: no hay vouchers ILS que cargar.');
        }

        return [
            'plan_id' => $planId,
            'coverage_id' => $coverageId,
            'coverage_label' => $coverageLabel,
            'affiliates' => $affiliates->values(),
            'eligible' => $eligible,
        ];
    }

    /**
     * Valores iniciales del modal: un bloque se precarga solo cuando **todos**
     * los seleccionados tienen el mismo voucher para ese beneficio. Con uno
     * solo seleccionado, es su voucher; con varios distintos, queda vacío para
     * no sugerir que comparten algo que no comparten.
     *
     * @param  array{affiliates: Collection<int, AffiliateCorporate>, eligible: list<array{key: string, benefit_id: int, coverage_id: int}>}  $selection
     * @return array<string, array{voucher_code: string|null, date_init: string|null, date_end: string|null, number_days: int|null, document_path: string|null}>
     */
    public static function formDefaults(array $selection): array
    {
        $vouchers = self::vouchersFor($selection);
        $defaults = [];

        foreach ($selection['eligible'] as $item) {
            $found = $vouchers->get($item['benefit_id'], collect());
            $fingerprints = $found->map(fn (AffiliateCorporateIlsVoucher $voucher): string => implode('|', [
                $voucher->voucher_code,
                $voucher->date_init?->toDateString(),
                $voucher->date_end?->toDateString(),
                $voucher->document_path,
            ]))->unique();

            /** @var AffiliateCorporateIlsVoucher|null $shared */
            $shared = $found->count() === $selection['affiliates']->count() && $fingerprints->count() === 1
                ? $found->first()
                : null;

            $defaults[$item['key']] = [
                'voucher_code' => $shared?->voucher_code,
                'date_init' => $shared?->date_init?->toDateString(),
                'date_end' => $shared?->date_end?->toDateString(),
                'number_days' => $shared?->number_days,
                'document_path' => $shared?->document_path,
            ];
        }

        return $defaults;
    }

    /**
     * Cuántos de los seleccionados ya tienen voucher, por beneficio.
     *
     * @param  array{affiliates: Collection<int, AffiliateCorporate>, eligible: list<array{key: string, benefit_id: int, coverage_id: int}>}  $selection
     * @return array<string, int>
     */
    public static function existingCounts(array $selection): array
    {
        $vouchers = self::vouchersFor($selection);
        $counts = [];

        foreach ($selection['eligible'] as $item) {
            $counts[$item['key']] = $vouchers->get($item['benefit_id'], collect())->count();
        }

        return $counts;
    }

    /**
     * Guarda los bloques con datos para los afiliados indicados, todo o nada.
     *
     * Se relee la selección desde la base y acotada a la afiliación: el
     * navegador no decide a quién se le escribe.
     *
     * @param  array<int, int|string>  $affiliateIds
     * @param  array<string, mixed>  $blocks  clave de bloque => campos del modal
     * @return array{benefits: list<string>, affiliates: int, vouchers: int}
     *
     * @throws InvalidArgumentException con el motivo, para mostrarlo tal cual
     */
    public static function save(AffiliationCorporate $owner, array $affiliateIds, array $blocks, ?int $userId): array
    {
        $ids = array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $affiliateIds),
            static fn (int $id): bool => $id > 0,
        )));

        $affiliates = AffiliateCorporate::query()
            ->where('affiliation_corporate_id', $owner->getKey())
            ->whereKey($ids)
            ->get();

        if ($ids === [] || $affiliates->count() !== count($ids)) {
            throw new InvalidArgumentException('Algunos afiliados seleccionados ya no pertenecen a esta afiliación. Recargue la tabla y vuelva a seleccionar.');
        }

        $selection = self::resolveSelection($affiliates);
        $toSave = [];

        foreach ($selection['eligible'] as $item) {
            $normalized = self::normalizeBlock($item, is_array($blocks[$item['key']] ?? null) ? $blocks[$item['key']] : []);

            if ($normalized !== null) {
                $toSave[] = [$item, $normalized];
            }
        }

        if ($toSave === []) {
            throw new InvalidArgumentException('No cargó ningún voucher. Complete al menos un beneficio.');
        }

        $now = now();
        $rows = [];

        foreach ($toSave as [$item, $voucher]) {
            foreach ($selection['affiliates'] as $affiliate) {
                $rows[] = [
                    'affiliate_corporate_id' => (int) $affiliate->getKey(),
                    'affiliation_corporate_id' => (int) $owner->getKey(),
                    'benefit_coverage_id' => $item['benefit_coverage_id'],
                    'plan_id' => $item['plan_id'],
                    'benefit_id' => $item['benefit_id'],
                    'coverage_id' => $item['coverage_id'],
                    'limit' => $item['limit'],
                    'voucher_code' => $voucher['voucher_code'],
                    'date_init' => $voucher['date_init'],
                    'date_end' => $voucher['date_end'],
                    'number_days' => $voucher['number_days'],
                    'document_path' => $voucher['document_path'],
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        DB::transaction(function () use ($rows): void {
            foreach (array_chunk($rows, 500) as $chunk) {
                AffiliateCorporateIlsVoucher::query()->upsert(
                    $chunk,
                    ['affiliate_corporate_id', 'benefit_id', 'coverage_id'],
                    ['affiliation_corporate_id', 'benefit_coverage_id', 'plan_id', 'limit', 'voucher_code', 'date_init', 'date_end', 'number_days', 'document_path', 'updated_by', 'updated_at'],
                );
            }
        });

        $benefits = array_map(static fn (array $pair): string => $pair[0]['benefit'], $toSave);

        SecurityAudit::log('AUDIT_BUSINESS_CORPORATE_AFFILIATE_ILS_VOUCHERS_SAVED', 'business.affiliation-corporates.affiliates.ils-vouchers', [
            'affiliation_corporate_id' => $owner->getKey(),
            'coverage_id' => $selection['coverage_id'],
            'affiliate_ids' => $selection['affiliates']->map(fn (AffiliateCorporate $affiliate): int => (int) $affiliate->getKey())->values()->all(),
            'vouchers' => array_map(static fn (array $pair): array => [
                'benefit_id' => $pair[0]['benefit_id'],
                'voucher_code' => $pair[1]['voucher_code'],
                'date_end' => $pair[1]['date_end'],
            ], $toSave),
        ]);

        return [
            'benefits' => $benefits,
            'affiliates' => $selection['affiliates']->count(),
            'vouchers' => count($rows),
        ];
    }

    /**
     * Estado del afiliado para la tabla: cuántos vouchers lleva de los que su
     * cobertura exige, y el detalle por beneficio. Usa `ilsVouchers` cargado.
     *
     * @return array{expected: int, loaded: int, lines: list<string>}
     */
    public static function statusFor(AffiliateCorporate $affiliate): array
    {
        $eligible = self::eligibleFor((int) $affiliate->plan_id, (int) $affiliate->coverage_id);
        $vouchers = $affiliate->relationLoaded('ilsVouchers')
            ? $affiliate->ilsVouchers
            : $affiliate->ilsVouchers()->get();

        $loaded = 0;
        $lines = [];

        foreach ($eligible as $item) {
            /** @var AffiliateCorporateIlsVoucher|null $voucher */
            $voucher = $vouchers->first(fn (AffiliateCorporateIlsVoucher $voucher): bool => $voucher->benefit_id === $item['benefit_id']
                && $voucher->coverage_id === $item['coverage_id']);

            if ($voucher === null) {
                $lines[] = '✗ '.$item['benefit'].' ('.self::money($item['limit']).'): pendiente';

                continue;
            }

            $loaded++;
            $lines[] = '✓ '.$item['benefit'].' ('.self::money($item['limit']).'): '.$voucher->voucher_code
                .' · hasta '.$voucher->date_end?->format('d/m/Y');
        }

        return ['expected' => count($eligible), 'loaded' => $loaded, 'lines' => $lines];
    }

    public static function money(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    /**
     * Normaliza un bloque del modal. Vacío por completo = null (no se toca).
     * Empezado pero incompleto = error con el nombre del beneficio, para que el
     * analista sepa exactamente qué le falta.
     *
     * @param  array{benefit: string}  $item
     * @param  array<string, mixed>  $block
     * @return array{voucher_code: string, date_init: string, date_end: string, number_days: int, document_path: string}|null
     */
    private static function normalizeBlock(array $item, array $block): ?array
    {
        $code = trim((string) ($block['voucher_code'] ?? ''));
        $document = $block['document_path'] ?? null;
        $document = is_array($document) ? Arr::first($document) : $document;
        $document = is_string($document) ? trim($document) : '';
        $dateInit = self::parseDate($block['date_init'] ?? null);
        $dateEnd = self::parseDate($block['date_end'] ?? null);

        $started = $code !== '' || $document !== '' || filled($block['date_init'] ?? null) || filled($block['date_end'] ?? null);

        if (! $started) {
            return null;
        }

        $missing = [];

        if ($code === '') {
            $missing[] = 'el número de voucher';
        }

        if ($dateInit === null) {
            $missing[] = 'la fecha desde';
        }

        if ($dateEnd === null) {
            $missing[] = 'la fecha hasta';
        }

        if ($document === '') {
            $missing[] = 'el comprobante';
        }

        if ($missing !== []) {
            throw new InvalidArgumentException($item['benefit'].': falta '.implode(', ', $missing)
                .'. Complete el bloque o déjelo vacío por completo.');
        }

        if (mb_strlen($code) > 100) {
            throw new InvalidArgumentException($item['benefit'].': el número de voucher no puede pasar de 100 caracteres.');
        }

        if ($dateEnd->lt($dateInit)) {
            throw new InvalidArgumentException($item['benefit'].': la fecha hasta no puede ser anterior a la fecha desde.');
        }

        if (str_contains($document, '..') || ! Storage::disk(self::DOCUMENT_DISK)->exists($document)) {
            throw new InvalidArgumentException($item['benefit'].': el comprobante no se encontró. Vuelva a adjuntarlo.');
        }

        return [
            'voucher_code' => $code,
            'date_init' => $dateInit->toDateString(),
            'date_end' => $dateEnd->toDateString(),
            'number_days' => (int) $dateInit->diffInDays($dateEnd),
            'document_path' => $document,
        ];
    }

    /**
     * Vouchers existentes de los seleccionados para su cobertura, por beneficio.
     *
     * @param  array{affiliates: Collection<int, AffiliateCorporate>, eligible: list<array{coverage_id: int}>}  $selection
     * @return Collection<int, Collection<int, AffiliateCorporateIlsVoucher>>
     */
    private static function vouchersFor(array $selection): Collection
    {
        $coverageId = $selection['eligible'][0]['coverage_id'] ?? 0;

        return AffiliateCorporateIlsVoucher::query()
            ->whereIn('affiliate_corporate_id', $selection['affiliates']->map(fn (AffiliateCorporate $affiliate): int => (int) $affiliate->getKey())->values()->all())
            ->where('coverage_id', $coverageId)
            ->get()
            ->groupBy('benefit_id');
    }

    private static function parseDate(mixed $value): ?Carbon
    {
        if (blank($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->startOfDay();
        }

        $value = trim((string) $value);

        foreach (['Y-m-d', 'd/m/Y', 'Y-m-d H:i:s'] as $format) {
            try {
                $parsed = Carbon::createFromFormat($format, $value);

                if ($parsed !== false && $parsed->format($format) === $value) {
                    return $parsed->startOfDay();
                }
            } catch (\Throwable) {
            }
        }

        return null;
    }

    /**
     * @param  Collection<int, AffiliateCorporate>  $affiliates
     */
    private static function namesLine(Collection $affiliates): string
    {
        $names = $affiliates->take(3)
            ->map(fn (AffiliateCorporate $affiliate): string => trim($affiliate->last_name.' '.$affiliate->first_name))
            ->implode(', ');

        return $names.($affiliates->count() > 3 ? ', …' : '');
    }
}
